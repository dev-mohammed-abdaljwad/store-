<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // Runs DatabaseSeeder which creates admin and store owner
    }

    public function test_super_admin_can_login()
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'admin@admin.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_store_owner_can_login()
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'store@ayad.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_invalid_login_returns_401()
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'wrong@email.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401);
    }
}
