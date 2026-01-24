<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SystemSeeder extends Seeder
{
    /**
     * The System User email - platform's vault and credit source.
     */
    public const SYSTEM_EMAIL = 'arjayescabas102@gmail.com';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the System User (idempotent - won't duplicate)
        // UserObserver will automatically create a wallet for this user
        $systemUser = User::firstOrCreate(
            ['email' => self::SYSTEM_EMAIL],
            [
                'name' => 'LUME Vault System',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Get the wallet (created by UserObserver) and set initial balance
        $wallet = Wallet::where('user_id', $systemUser->id)->first();

        if ($wallet) {
            $wallet->update([
                'balance' => 10000.00,
                'currency' => 'USD',
            ]);
            $this->command->info("System Wallet updated: \${$wallet->balance} {$wallet->currency}");
        } else {
            // Fallback: create wallet manually if observer didn't fire
            $wallet = Wallet::create([
                'user_id' => $systemUser->id,
                'balance' => 10000.00,
                'currency' => 'USD',
            ]);
            $this->command->info("System Wallet created: \${$wallet->balance} {$wallet->currency}");
        }

        $this->command->info('System User created: ' . self::SYSTEM_EMAIL);
        $this->command->info('System Wallet ID: ' . $wallet->id);
    }
}
