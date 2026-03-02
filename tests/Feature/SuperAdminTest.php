<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // Provides initial data from DatabaseSeeder
        $this->admin = User::where('email', 'admin@admin.com')->first();
    }

    public function test_admin_can_list_stores()
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/stores');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        $this->assertNotEmpty($response->json());
    }

    public function test_admin_can_create_store()
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/stores', [
            'name'                  => 'New Test Store',
            'owner_name'            => 'John Doe',
            'email'                 => 'johndoe@test.com',
            'phone'                 => '0555555555',
            'address'               => 'Test Address',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('store.name', 'New Test Store');

        $this->assertDatabaseHas('stores', ['email' => 'johndoe@test.com']);
        $this->assertDatabaseHas('users', ['email' => 'johndoe@test.com', 'role' => 'store_owner']);
    }

    public function test_admin_can_deactivate_and_activate_store()
    {
        $store = Store::first();

        // Deactivate
        $response = $this->actingAs($this->admin)->postJson("/api/admin/stores/{$store->id}/deactivate");
        $response->assertStatus(200);
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'is_active' => false]);

        // Activate
        $response = $this->actingAs($this->admin)->postJson("/api/admin/stores/{$store->id}/activate");
        $response->assertStatus(200);
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'is_active' => true]);
    }

    public function test_store_owner_cannot_access_admin_endpoints()
    {
        $owner = User::where('email', 'store@ayad.com')->first();

        $response = $this->actingAs($owner)->getJson('/api/admin/stores');

        // 403 Forbidden because of Role middleware
        $response->assertStatus(403);
    }
}
