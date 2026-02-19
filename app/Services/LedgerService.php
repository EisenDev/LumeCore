<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\VaultAsset;
use App\Models\Wallet;
use Database\Seeders\SystemSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * LedgerService handles all financial transactions in the LUME platform.
 *
 * FINANCIAL INTEGRITY RULES:
 * - All money movements MUST be wrapped in DB::transaction()
 * - Always use lockForUpdate() to prevent race conditions
 * - All monetary values use decimal(15,2)
 * - Every transaction must have a unique reference_number
 * - Transactions are immutable (never update or delete)
 * 
 * AUDIT FEES:
 * - Document Audit: 1 Credit (PDFs, images, text files)
 * - Project Audit: 5 Credits (websites, codebases - more complex processing)
 */
class LedgerService
{
    /**
     * Audit fee constants.
     * These define the credit cost for each audit type.
     */
    public const AUDIT_FEES = [
        'document' => 1.0,  // Simple document audit
        'project' => 10.0,  // Complex project/website audit
        'sync' => 10.0,     // Sync scan (Web vs Repo)
        'rescan' => 10.0,   // Re-scan of an existing project/sync
        'pentest' => 250.0, // High-Value Deep Penetration Testing
    ];

    public const CHAT_FEE = 0.50; // Fee per AI Chat Message
    public const SYSTEM_PROCESSING_FEE = 0.20; // Processing fee for system-level failures

    /**
     * Get the credit cost for a specific audit type.
     */
    public static function getAuditFee(string $auditType): float
    {
        return self::AUDIT_FEES[$auditType] ?? self::AUDIT_FEES['document'];
    }

    /**
     * Check if user has enough credits for an audit type.
     */
    public function hasCreditsForAudit(User $user, string $auditType): bool
    {
        // 1. Check for Active Subscription Quota
        $subscription = $user->activeSubscription;
        if ($subscription && $subscription->hasQuota($auditType)) {
            return true;
        }

        // 2. Fallback to Wallet Credits
        $requiredCredits = self::getAuditFee($auditType);
        return $this->getCredits($user) >= $requiredCredits;
    }

    /**
     * Consume credits for an audit and return the amount consumed.
     * Returns the amount consumed, or 0 if insufficient credits.
     */
    public function consumeAuditCredits(User $user, string $auditType, string $assetName = 'Asset'): float
    {
        // 1. Check Subscription Quota first
        $subscription = $user->activeSubscription;
        if ($subscription && $subscription->hasQuota($auditType)) {
            $subscription->incrementUsage($auditType);
            Log::info("LedgerService: Used subscription quota for {$auditType} consumption on {$assetName}");
            return 0.0; // Quota used, no credits consumed
        }

        // 2. Fallback to Wallet Credits
        $creditCost = self::getAuditFee($auditType);
        $description = "LUME {$auditType} Audit: {$assetName}";
        
        $consumed = $this->consumeCredit($user, $creditCost, $description);
        
        return $consumed ? $creditCost : 0.0;
    }

    /**
     * Lock credits at the start of an audit.
     * Use this for the "Charge Upfront" model.
     */
    public function lockAuditCredits(User $user, string $auditType, string $assetName = 'Asset'): float
    {
        // 1. Check Subscription Quota
        $subscription = $user->activeSubscription;
        if ($subscription && $subscription->hasQuota($auditType)) {
            $subscription->incrementUsage($auditType);
            Log::info("LedgerService: Used subscription quota for {$auditType} on {$assetName}");
            return 0.0; // No credits consumed, quota used
        }

        // 2. Fallback to Credits
        $creditCost = self::getAuditFee($auditType);
        $description = "LUME Audit Lock: {$assetName}";
        
        $consumed = $this->consumeCredit($user, $creditCost, $description);
        
        return $consumed ? $creditCost : 0.0;
    }

    /**
     * Refund credits OR quota for a failed audit.
     * @param float|null $amount Optional custom amount to refund. If null, refunds full audit fee.
     */
    public function refundAuditCredits(User $user, string $auditType, string $assetName = 'Asset', ?float $amount = null): int
    {
        // 1. Check if user has active subscription (used quota)
        $subscription = $user->activeSubscription;
        if ($subscription) {
            // Refund by decrementing usage (giving quota back)
            $subscription->decrementUsage($auditType);
            Log::info("Refunded {$auditType} quota to user {$user->id} for {$assetName}");
            return 0; // No credit balance changed
        }
        
        // 2. Otherwise, refund credits
        $creditToRefund = $amount ?? self::getAuditFee($auditType);
        $description = "Refund: {$auditType} audit failed for {$assetName}";
        
        return $this->refundCredits($user, $creditToRefund, $description);
    }

    /**
     * Refund credits for a system failure, deducting a processing fee.
     * Rules:
     * - Only applied when system (not AI) caused the failure.
     * - Processing fee covers resource consumption before crash.
     */
    public function refundSystemFailure(User $user, string $auditType, string $assetName): float
    {
        $originalFee = self::getAuditFee($auditType);
        $refundAmount = max(0, $originalFee - self::SYSTEM_PROCESSING_FEE);
        
        $description = "Partial Refund (System Exception): {$assetName} (Fee: " . self::SYSTEM_PROCESSING_FEE . ")";
        
        $this->refundCredits($user, $refundAmount, $description);
        
        return $refundAmount;
    }

    /**
     * Consume credits for a chat message.
     */
    public function consumeChatCredit(User $user): float
    {
        $description = "LUME Chat Intelligence Fee";
        $consumed = $this->consumeCredit($user, self::CHAT_FEE, $description);
        return $consumed ? self::CHAT_FEE : 0.0;
    }
    /**
     * Record an asset upload in the ledger.
     * Creates an audit trail link between the file and the financial system.
     *
     * @param VaultAsset $asset The uploaded asset to record
     * @return Transaction The created transaction record
     */
    public function recordAssetUpload(VaultAsset $asset): Transaction
    {
        return DB::transaction(function () use ($asset) {
            // Get the user's wallet with a lock to prevent race conditions
            $wallet = Wallet::where('user_id', $asset->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Create the audit trail transaction
            // Amount is 0.00 for uploads - this creates the link between file and ledger
            $transaction = Transaction::create([
                'wallet_id' => $wallet->id,
                'asset_id' => $asset->id,
                'type' => 'credit', // Upload is a "credit" of an asset to the vault
                'amount' => 0.00,
                'description' => "Asset uploaded: {$asset->file_name}",
                'reference_number' => $this->generateReferenceNumber('UPLOAD'),
                'metadata' => [
                    'file_name' => $asset->file_name,
                    'file_size' => $asset->file_size,
                    'mime_type' => $asset->mime_type,
                    'action' => 'upload',
                ],
            ]);

            return $transaction;
        });
    }

    /**
     * Payout funds for an asset from System wallet to User wallet.
     * Creates a double-entry transaction: debit System, credit User.
     *
     * @param VaultAsset $asset The asset being paid out for
     * @param float $amount The amount to pay out
     * @return array{debit: Transaction, credit: Transaction} Both transaction records
     * @throws \Exception If insufficient system balance
     */
    /**
     * Collect audit fee from User wallet to System wallet.
     * Creates a double-entry transaction: debit User, credit System.
     *
     * @param VaultAsset $asset The asset being audited
     * @param float $amount The fee amount
     * @return array{debit: Transaction, credit: Transaction} Both transaction records
     * @throws \Exception If insufficient user balance
     */
    public function collectAuditFee(VaultAsset $asset, float $amount): array
    {
        return DB::transaction(function () use ($asset, $amount) {
            // Find the User's wallet (the payer)
            $userWallet = Wallet::where('user_id', $asset->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Find the System User's wallet (the receiver)
            $systemUser = User::where('email', SystemSeeder::SYSTEM_EMAIL)->firstOrFail();
            $systemWallet = Wallet::where('user_id', $systemUser->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Verify User has sufficient balance
            if (bccomp($userWallet->balance, $amount, 2) < 0) {
                throw new \Exception('Insufficient balance for audit fee');
            }

            // Generate a shared reference for this fee pair
            $feeReference = $this->generateReferenceNumber('FEE');

            // DEBIT: User wallet (outgoing)
            $userWallet->balance = bcsub($userWallet->balance, $amount, 2);
            $userWallet->save();

            $debitTransaction = Transaction::create([
                'wallet_id' => $userWallet->id,
                'asset_id' => $asset->id,
                'type' => 'debit',
                'amount' => $amount,
                'description' => "Audit Fee Collected: {$asset->file_name}",
                'reference_number' => "{$feeReference}-DR",
                'metadata' => [
                    'action' => 'audit_fee',
                    'recipient_user_id' => $systemUser->id,
                    'asset_id' => $asset->id,
                    'fee_reference' => $feeReference,
                    'type' => 'Revenue',
                ],
            ]);

            // CREDIT: System wallet (incoming)
            $systemWallet->balance = bcadd($systemWallet->balance, $amount, 2);
            $systemWallet->save();

            $creditTransaction = Transaction::create([
                'wallet_id' => $systemWallet->id,
                'asset_id' => $asset->id,
                'type' => 'credit',
                'amount' => $amount,
                'description' => "Audit Fee Received: {$asset->file_name}",
                'reference_number' => "{$feeReference}-CR",
                'metadata' => [
                    'action' => 'audit_fee',
                    'sender_user_id' => $asset->user_id,
                    'asset_id' => $asset->id,
                    'fee_reference' => $feeReference,
                    'type' => 'Revenue',
                ],
            ]);

            return [
                'debit' => $debitTransaction,
                'credit' => $creditTransaction,
            ];
        });
    }

    /**
     * Credit funds to a user's wallet.
     *
     * @param Wallet $wallet The wallet to credit
     * @param float $amount The amount to credit
     * @param string $description Transaction description
     * @param VaultAsset|null $asset Optional linked asset
     * @param array $metadata Additional metadata
     * @return Transaction The created transaction record
     */
    public function credit(
        Wallet $wallet,
        float $amount,
        string $description,
        ?VaultAsset $asset = null,
        array $metadata = []
    ): Transaction {
        return DB::transaction(function () use ($wallet, $amount, $description, $asset, $metadata) {
            // Lock the wallet for update
            $wallet = Wallet::where('id', $wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Update wallet balance
            $wallet->balance = bcadd($wallet->balance, $amount, 2);
            $wallet->save();

            // Create transaction record
            return Transaction::create([
                'wallet_id' => $wallet->id,
                'asset_id' => $asset?->id,
                'type' => 'credit',
                'amount' => $amount,
                'description' => $description,
                'reference_number' => $this->generateReferenceNumber('CR'),
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Debit funds from a user's wallet.
     *
     * @param Wallet $wallet The wallet to debit
     * @param float $amount The amount to debit
     * @param string $description Transaction description
     * @param VaultAsset|null $asset Optional linked asset
     * @param array $metadata Additional metadata
     * @return Transaction The created transaction record
     * @throws \Exception If insufficient balance
     */
    public function debit(
        Wallet $wallet,
        float $amount,
        string $description,
        ?VaultAsset $asset = null,
        array $metadata = []
    ): Transaction {
        return DB::transaction(function () use ($wallet, $amount, $description, $asset, $metadata) {
            // Lock the wallet for update
            $wallet = Wallet::where('id', $wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Verify sufficient balance
            if (bccomp($wallet->balance, $amount, 2) < 0) {
                throw new \Exception('Insufficient balance');
            }

            // Update wallet balance
            $wallet->balance = bcsub($wallet->balance, $amount, 2);
            $wallet->save();

            // Create transaction record
            return Transaction::create([
                'wallet_id' => $wallet->id,
                'asset_id' => $asset?->id,
                'type' => 'debit',
                'amount' => $amount,
                'description' => $description,
                'reference_number' => $this->generateReferenceNumber('DR'),
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Purchase credits by converting balance to credits.
     * Debits the user's balance and adds credits.
     *
     * @param User $user The user purchasing credits
     * @param int $amount Number of credits to purchase
     * @param float $pricePerCredit Price per credit (default $1.00)
     * @return Transaction The transaction record
     * @throws \Exception If insufficient balance
     */
    public function purchaseCredits(User $user, int $amount, float $pricePerCredit = 1.00): Transaction
    {
        return DB::transaction(function () use ($user, $amount, $pricePerCredit) {
            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $totalCost = bcmul((string) $amount, (string) $pricePerCredit, 2);

            // Verify sufficient balance
            if (bccomp($wallet->balance, $totalCost, 2) < 0) {
                throw new \Exception('Insufficient balance to purchase credits');
            }

            // Debit balance
            $wallet->balance = bcsub($wallet->balance, $totalCost, 2);
            // Credit... credits
            $wallet->credits = $wallet->credits + $amount;
            $wallet->save();

            return Transaction::create([
                'wallet_id' => $wallet->id,
                'asset_id' => null,
                'type' => 'debit',
                'amount' => (float) $totalCost,
                'description' => "Purchased {$amount} audit credits",
                'reference_number' => $this->generateReferenceNumber('CREDITS'),
                'metadata' => [
                    'action' => 'purchase_credits',
                    'credits_purchased' => $amount,
                    'price_per_credit' => $pricePerCredit,
                ],
            ]);
        });
    }

    /**
     * Consume credits for an AI audit.
     * Returns true if credits were consumed, false if insufficient credits.
     *
     * @param User $user The user consuming credits
     * @param float $amount The amount of credits to consume
     * @param string $description The transaction description
     * @return bool Whether the credits were successfully consumed
     */
    public function consumeCredit(User $user, float $amount = 1.0, string $description = 'AI Audit credit consumed'): bool
    {
        return DB::transaction(function () use ($user, $amount, $description) {
            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($wallet->credits < $amount) {
                return false;
            }

            $wallet->credits = $wallet->credits - $amount;
            $wallet->save();

            Transaction::create([
                'wallet_id' => $wallet->id,
                'asset_id' => null,
                'type' => 'debit',
                'amount' => 0.00, // Credit consumption, not money
                'description' => $description,
                'reference_number' => $this->generateReferenceNumber('CR-USE'),
                'metadata' => [
                    'action' => 'consume_credit',
                    'credits_amount' => $amount,
                ],
            ]);

            return true;
        });
    }

    /**
     * Check if user has credits available.
     *
     * @param User $user The user to check
     * @return int Number of credits available
     */
    public function getCredits(User $user): int
    {
        $wallet = Wallet::where('user_id', $user->id)->first();
        return $wallet?->credits ?? 0;
    }

    /**
     * Add test credits to a user's wallet (for testing only).
     * WARNING: This bypasses payment - use only in development.
     *
     * @param User $user The user to add credits to
     * @param int $amount Number of credits to add
     * @return int New credit balance
     */
    public function addTestCredits(User $user, int $amount): int
    {
        return DB::transaction(function () use ($user, $amount) {
            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->credits = $wallet->credits + $amount;
            $wallet->save();

            Transaction::create([
                'wallet_id' => $wallet->id,
                'asset_id' => null,
                'type' => 'credit',
                'amount' => 0.00, // Test credits - no money involved
                'description' => "Test credits added: {$amount}",
                'reference_number' => $this->generateReferenceNumber('TEST'),
                'metadata' => [
                    'action' => 'test_credits',
                    'credits_added' => $amount,
                ],
            ]);

            return $wallet->credits;
        });
    }

    /**
     * Refund credits to a user's wallet.
     * Used when an audit fails after credits were consumed.
     *
     * @param User $user The user to refund credits to
     * @param float $amount The amount of credits to refund
     * @param string $description The transaction description
     * @return int New credit balance
     */
    public function refundCredits(User $user, float $amount, string $description = 'Credit refund'): int
    {
        return DB::transaction(function () use ($user, $amount, $description) {
            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->credits = $wallet->credits + $amount;
            $wallet->save();

            Transaction::create([
                'wallet_id' => $wallet->id,
                'asset_id' => null,
                'type' => 'credit',
                'amount' => 0.00, // Credit refund - no money involved
                'description' => $description,
                'reference_number' => $this->generateReferenceNumber('REFUND'),
                'metadata' => [
                    'action' => 'credit_refund',
                    'credits_refunded' => $amount,
                ],
            ]);

            return $wallet->credits;
        });
    }

    /**
     * Generate a unique reference number for transactions.
     *
     * @param string $prefix The prefix for the reference (e.g., 'CR', 'DR', 'UPLOAD')
     * @return string Unique reference number
     */
    protected function generateReferenceNumber(string $prefix): string
    {
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(Str::random(6));

        return "{$prefix}-{$timestamp}-{$random}";
    }
}

