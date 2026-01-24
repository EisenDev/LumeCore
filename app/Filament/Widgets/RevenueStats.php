<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use App\Models\User;
use App\Models\VaultAsset;
use Database\Seeders\SystemSeeder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Get System User ID for revenue calculation
        $systemUser = User::where('email', SystemSeeder::SYSTEM_EMAIL)->first();

        // Total Platform Revenue: Sum of all credit transactions from System User
        // These are payouts made from the system to users
        $totalRevenue = 0.00;
        if ($systemUser && $systemUser->wallet) {
            $totalRevenue = Transaction::where('wallet_id', $systemUser->wallet->id)
                ->where('type', 'debit') // System debits = payouts to users
                ->sum('amount');
        }

        // Active Audits: Count of VaultAssets with 'verified' or 'flagged' status
        $activeAudits = VaultAsset::whereIn('status', ['verified', 'flagged'])->count();

        // Total Users: Count of all users (excluding system user)
        $totalUsers = User::where('email', '!=', SystemSeeder::SYSTEM_EMAIL)->count();

        return [
            Stat::make('Total Platform Revenue', '$' . number_format($totalRevenue, 2))
                ->description('Paid out to users')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Active Audits', $activeAudits)
                ->description('Verified & Flagged assets')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('primary'),

            Stat::make('Total Users', $totalUsers)
                ->description('Registered accounts')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }
}
