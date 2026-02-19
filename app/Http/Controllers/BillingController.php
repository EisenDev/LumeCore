<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Subscription;
use App\Models\ScanActivity;
use App\Models\Invoice;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    /**
     * Display the billing and usage page.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Eager load wallet
        $user->load('wallet');
        
        // Fetch subscription - explicitly order by created_at
        $subscription = Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'trialing'])
            ->orderBy('created_at', 'desc')
            ->first();

        if ($subscription) {
            $subscription->plan_name = $subscription->plan_type === 'agency' ? 'Agency Plan' : 'CI/CD Plan';
        }

        // Fetch usage logs (Scan Activity)
        $usageLogs = ScanActivity::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'user' => $log->user ? $log->user->name : 'System',
                    'type' => $log->type,
                    'display_name' => $log->display_name,
                    'status' => $log->sync_status ?: $log->docu_and_urls_status,
                    'date' => $log->created_at->format('M d, Y H:i'),
                ];
            });

        // Fetch real invoices
        $invoices = Invoice::where('user_id', $user->id)
            ->orderBy('billing_date', 'desc')
            ->get()
            ->map(function($inv) {
                return [
                    'id' => $inv->invoice_number,
                    'date' => $inv->billing_date->format('Y-m-d'),
                    'plan' => ucfirst($inv->plan_type),
                    'amount' => $inv->amount,
                    'status' => $inv->status
                ];
            });

        return Inertia::render('Billing/Index', [
            'wallet' => $user->wallet,
            'subscription' => $subscription,
            'invoices' => $invoices,
            'usageLogs' => $usageLogs
        ]);
    }

    /**
     * View/Download invoice.
     */
    public function downloadInvoice(Request $request, $id)
    {
        $user = $request->user();
        
        $invoiceRecord = Invoice::where('user_id', $user->id)
            ->where('invoice_number', $id)
            ->firstOrFail();
        
        $invoice = [
            'id' => $invoiceRecord->invoice_number,
            'date' => $invoiceRecord->billing_date->format('F d, Y'),
            'plan' => $invoiceRecord->plan_type,
            'amount' => (float) $invoiceRecord->amount,
            'reference' => $invoiceRecord->reference_id,
        ];

        return view('invoices.show', [
            'invoice' => $invoice,
            'user' => $user
        ]);
    }

    /**
     * Toggle auto-renew for current subscription.
     */
    public function toggleAutoRenew(Request $request)
    {
        $request->validate(['auto_renew' => 'required|boolean']);
        $user = $request->user();
        
        \App\Models\Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['auto_renew' => $request->auto_renew]);

        return back()->with('success', 'Auto-renewal preference updated.');
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(Request $request)
    {
        $user = $request->user();
        
        Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->update([
                'status' => 'cancelled',
            ]);

        return back()->with('success', 'Subscription cancelled successfully.');
    }

    /**
     * Subscribe to a plan.
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'plan_type' => 'required|string|in:developer,agency',
        ]);

        $user = $request->user();
        $planType = $validated['plan_type'];

        // Cancel existing active subscriptions
        Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->update([
                'status' => 'cancelled',
            ]);

        // Create new subscription record
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_type' => $planType,
            'status' => 'active',
            'ends_at' => now()->addMonth(),
            'daily_individual_scans_used' => 0,
            'daily_sync_scans_used' => 0,
            'monthly_pentests_used' => 0,
        ]);

        // Generate Invoice for the transaction
        $amount = ($planType === 'agency') ? 59.00 : 19.00;
        $count = Invoice::count() + 1;
        $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        Invoice::create([
            'user_id' => $user->id,
            'invoice_number' => $invoiceNumber,
            'plan_type' => $planType,
            'amount' => $amount,
            'status' => 'Paid',
            'billing_date' => now(),
            'reference_id' => 'LUME-SUB-' . strtoupper(Str::random(6)),
        ]);

        return back()->with('success', "Subscription to {$planType} successful!");
    }
}
