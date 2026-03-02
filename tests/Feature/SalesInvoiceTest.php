<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::where('email', 'store@ayad.com')->first();
    }

    public function test_can_list_sales_invoices()
    {
        $response = $this->actingAs($this->owner)->getJson('/api/store/sales-invoices');
        $response->assertStatus(200);
        $this->assertNotEmpty($response->json());
        $this->assertArrayHasKey('invoice_number', $response->json()[0]);
    }

    public function test_can_create_sales_invoice_with_stock()
    {
        $customer = Customer::first();
        $product = Product::first();

        // Seeder put 100 in stock, sold 20. Remaining 80.
        $response = $this->actingAs($this->owner)->postJson('/api/store/sales-invoices', [
            'customer_id' => $customer->id,
            'paid_amount' => 50,
            'notes'       => 'Some notes',
            'items'       => [
                [
                    'product_id' => $product->id,
                    'quantity'   => 10,  // we sell 10
                    'unit_price' => 150,
                ]
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('notes', 'Some notes')
            ->assertJsonPath('status', 'confirmed');

        $this->assertDatabaseHas('sales_invoices', [
            'notes' => 'Some notes',
        ]);
    }

    public function test_cannot_sell_without_enough_stock()
    {
        $customer = Customer::first();
        $product = Product::first(); // Stock is 80

        $response = $this->actingAs($this->owner)->postJson('/api/store/sales-invoices', [
            'customer_id' => $customer->id,
            'paid_amount' => 0,
            'items'       => [
                [
                    'product_id' => $product->id,
                    'quantity'   => 1000,  // Way too much
                    'unit_price' => 150,
                ]
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['stock']);
    }

    public function test_can_cancel_sales_invoice()
    {
        $invoice = SalesInvoice::first();

        $response = $this->actingAs($this->owner)->postJson("/api/store/sales-invoices/{$invoice->id}/cancel", [
            'reason' => 'Customer refunded',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'cancelled');

        $this->assertDatabaseHas('sales_invoices', [
            'id' => $invoice->id,
            'status' => 'cancelled',
        ]);
    }
}
