<?php

// ══════════════════════════════════════════════════════════════════
// StoreService.php — للـ Super Admin فقط
// ══════════════════════════════════════════════════════════════════

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StoreService
{
    /**
     * إنشاء متجر جديد مع صاحبه.
     */
    public function createStore(array $data): Store
    {
        return DB::transaction(function () use ($data) {

            $store = Store::create([
                'name'       => $data['name'],
                'owner_name' => $data['owner_name'],
                'email'      => $data['email'],
                'phone'      => $data['phone'] ?? null,
                'address'    => $data['address'] ?? null,
                'is_active'  => true,
            ]);

            User::create([
                'store_id' => $store->id,
                'name'     => $data['owner_name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
                'role'     => 'store_owner',
                'is_active'=> true,
            ]);

            return $store;
        });
    }

    public function activate(int $storeId): void
    {
        Store::findOrFail($storeId)->activate();
    }

    public function deactivate(int $storeId): void
    {
        Store::findOrFail($storeId)->deactivate(); // ينهي الجلسات تلقائياً
    }

    public function getAllStores(): array
    {
        return Store::with('owner')
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(fn(Store $s) => [
                        'id'         => $s->id,
                        'name'       => $s->name,
                        'email'      => $s->email,
                        'owner_name' => $s->owner_name,
                        'is_active'  => $s->is_active,
                        'created_at' => $s->created_at->format('Y-m-d'),
                    ])
                    ->toArray();
    }
}


