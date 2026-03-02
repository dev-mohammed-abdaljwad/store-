<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::where('email', 'store@ayad.com')->first();
    }

    public function test_can_list_customers()
    {
        $response = $this->actingAs($this->owner)->getJson('/api/store/customers');
        $response->assertStatus(200);
        $this->assertNotEmpty($response->json());
    }

    public function test_can_create_customer()
    {
        $response = $this->actingAs($this->owner)->postJson('/api/store/customers', [
            'name'    => 'عميل جديد 33',
            'phone'   => '0511111111',
            'address' => 'Test Address',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'عميل جديد 33');

        $this->assertDatabaseHas('customers', ['name' => 'عميل جديد 33']);
    }

    public function test_can_view_customer_statement()
    {
        $customer = Customer::first();
        $response = $this->actingAs($this->owner)->getJson("/api/store/customers/{$customer->id}/statement");

        $response->assertStatus(200);
        $this->assertArrayHasKey('balance', $response->json());
        $this->assertArrayHasKey('statement', $response->json());
    }

    public function test_can_list_suppliers()
    {
        $response = $this->actingAs($this->owner)->getJson('/api/store/suppliers');
        $response->assertStatus(200);
        $this->assertNotEmpty($response->json());
    }

    public function test_can_create_supplier()
    {
        $response = $this->actingAs($this->owner)->postJson('/api/store/suppliers', [
            'name'    => 'مورد جديد 99',
            'phone'   => '0522222222',
            'address' => 'Supplier Address',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'مورد جديد 99');

        $this->assertDatabaseHas('suppliers', ['name' => 'مورد جديد 99']);
    }

    public function test_can_view_supplier_statement()
    {
        $supplier = Supplier::first();
        $response = $this->actingAs($this->owner)->getJson("/api/store/suppliers/{$supplier->id}/statement");

        $response->assertStatus(200);
        $this->assertArrayHasKey('balance', $response->json());
        $this->assertArrayHasKey('statement', $response->json());
    }
}
