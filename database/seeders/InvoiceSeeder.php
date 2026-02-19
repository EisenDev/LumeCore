<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = \App\Models\User::where('email', 'arjayescabas102@gmail.com')->first();
        if (!$user) return;

        // Clear existing to avoid duplicates
        \App\Models\Invoice::where('user_id', $user->id)->delete();

        // 1. Agency Invoice
        \App\Models\Invoice::create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-2026-001',
            'plan_type' => 'agency',
            'amount' => 59.00,
            'status' => 'Paid',
            'billing_date' => '2026-02-02 10:00:00',
            'reference_id' => 'LUME-SUB-QIVMIZ',
        ]);

        // 2. Developer Invoice
        \App\Models\Invoice::create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-2026-002',
            'plan_type' => 'developer',
            'amount' => 19.00,
            'status' => 'Paid',
            'billing_date' => '2026-02-03 12:00:00',
            'reference_id' => 'LUME-SUB-' . strtoupper(\Illuminate\Support\Str::random(6)),
        ]);
    }
}
