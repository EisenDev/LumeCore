<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $user = User::firstOrNew(['email' => 'lume@gmail.com']);
            $user->name = 'Lume Admin';
            $user->password = Hash::make('password');
            $user->is_admin = true;
            $user->email_verified_at = now();
            $user->save();
            $user->refresh();

            if ($user->wallet) {
                $user->wallet->update(['credits' => 100.00]);
            } else {
                $user->wallet()->create([
                    'balance' => 0,
                    'credits' => 100.00,
                    'currency' => 'USD'
                ]);
            }
        });
    }
}
