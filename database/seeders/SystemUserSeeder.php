<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SystemUserSeeder extends Seeder
{
    /**
     * The System User email - used as the platform's credit source.
     */
    public const SYSTEM_EMAIL = 'system@lume.local';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the System User (idempotent)
        $systemUser = User::firstOrCreate(
            ['email' => self::SYSTEM_EMAIL],
            [
                'name' => 'LUME System',
                'password' => Hash::make(Str::random(64)), // Unguessable password
                'email_verified_at' => now(),
            ]
        );

        // Ensure the System User has a wallet with platform funds
        // Note: UserObserver creates wallet automatically, but we set initial balance here
        $wallet = Wallet::where('user_id', $systemUser->id)->first();

        if ($wallet) {
            // Set a large initial balance for platform credits
            $wallet->update([
                'balance' => 1000000000.00, // $1 billion as platform reserve
                'currency' => 'USD',
            ]);
        } else {
            // Create wallet manually if observer didn't run (e.g., user already existed)
            Wallet::create([
                'user_id' => $systemUser->id,
                'balance' => 1000000000.00,
                'currency' => 'USD',
            ]);
        }

        $this->command->info('System User created: ' . self::SYSTEM_EMAIL);
    }
}
