<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::where('email', 'store@ayad.com')->first();
    }

    public function test_can_list_purchase_invoices()
    {
        $response = $this->actingAs($this->owner)->getJson('/api/store/purchase-invoices');
        $response->assertStatus(200);
        $this->assertNotEmpty($response->json());
        $this->assertArrayHasKey('invoice_number', $response->json()[0]);
    }

    public function test_can_create_purchase_invoice()
    {
        $supplier = Supplier::first();
        $product = Product::first();

        $response = $this->actingAs($this->owner)->postJson('/api/store/purchase-invoices', [
            'supplier_id' => $supplier->id,
            'paid_amount' => 10,
            'notes'       => 'Test Invoice',
            'items'       => [
                [
                    'product_id'        => $product->id,
                    'ordered_quantity'  => 50,
                    'received_quantity' => 50,
                    'unit_price'        => 80,
                ]
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('notes', 'Test Invoice')
            ->assertJsonPath('status', 'confirmed');

        $this->assertDatabaseHas('purchase_invoices', [
            'notes' => 'Test Invoice',
        ]);

        $this->assertDatabaseHas('purchase_invoice_items', [
            'product_id' => $product->id,
            'received_quantity' => 50,
        ]);
    }

    public function test_can_cancel_purchase_invoice()
    {
        // Seeder already created one invoice
        $invoice = PurchaseInvoice::first();

        $response = $this->actingAs($this->owner)->postJson("/api/store/purchase-invoices/{$invoice->id}/cancel", [
            'reason' => 'Mistake',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('cancel_reason', 'Mistake');

        $this->assertDatabaseHas('purchase_invoices', [
            'id' => $invoice->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_cancel_already_cancelled_invoice()
    {
        $invoice = PurchaseInvoice::first();
        // Cancel first
        $this->actingAs($this->owner)->postJson("/api/store/purchase-invoices/{$invoice->id}/cancel", ['reason' => 'First']);

        // Cancel again
        $response = $this->actingAs($this->owner)->postJson("/api/store/purchase-invoices/{$invoice->id}/cancel", ['reason' => 'Second']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invoice']);
    }

    public function test_can_delete_purchase_invoice_and_reuse_number()
    {
        $invoice = PurchaseInvoice::with('items')->first();

        $response = $this->actingAs($this->owner)->deleteJson("/api/store/purchase-invoices/{$invoice->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'تم حذف فاتورة الشراء بنجاح.');

        $this->assertDatabaseMissing('purchase_invoices', [
            'id' => $invoice->id,
        ]);

        $variantId = $invoice->items->first()->variant_id;

        $createResponse = $this->actingAs($this->owner)->postJson('/api/store/purchase-invoices', [
            'invoice_number' => $invoice->invoice_number,
            'supplier_id' => Supplier::first()->id,
            'paid_amount' => 0,
            'items' => [
                [
                    'variant_id' => $variantId,
                    'ordered_quantity' => 1,
                    'received_quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('invoice.invoice_number', $invoice->invoice_number);
    }

    public function test_can_search_purchase_invoice_by_number()
    {
        $invoice = PurchaseInvoice::first();

        $response = $this->actingAs($this->owner)->getJson('/api/store/purchase-invoices?search=' . $invoice->invoice_number);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.invoice_number', $invoice->invoice_number);
    }
}
