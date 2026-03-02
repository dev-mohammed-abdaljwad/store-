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
            ['email' => 'owner-store@ayad-store.test'],
            [
                'name' => 'Ayad Demo Store',
                'owner_name' => 'Store Owner',
                'phone' => '01000000000',
                'address' => 'Main Branch',
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'owner@ayad-store.test'],
            [
                'store_id' => $store->id,
                'name' => 'Store Owner',
                'password' => Hash::make('password'),
                'role' => UserRole::STORE_OWNER,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
