<?php

namespace Database\Seeders;

use App\Domain\Store\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StoreOwnerUserSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::query()->updateOrCreate(
            ['email' => 'ahmed@agristore.com'],
            [
                'name' => 'Ahmed salah Demo Store',
                'owner_name' => 'Ahmed Salah',
                'phone' => '01000000000',
                'address' => 'Main Branch',
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'ahmed@agristore.com'],
            [
                'store_id' => $store->id,
                'name' => 'Ahmed Salah',
                'password' => Hash::make('password'),
                'role' => UserRole::STORE_OWNER,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
