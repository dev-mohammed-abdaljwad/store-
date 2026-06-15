<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PurchaseInvoice;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->owner = User::where('email', 'store@ayad.com')->first();
    }

    public function test_can_search_sales_return_by_return_number_and_invoice_number()
    {
        $customer = Customer::first();
        $variant = ProductVariant::first();

        $invoiceResponse = $this->actingAs($this->owner)->postJson('/api/store/sales-invoices', [
            'customer_id' => $customer->id,
            'paid_amount' => 0,
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $invoiceResponse->assertStatus(201);
        $invoice = SalesInvoice::findOrFail($invoiceResponse->json('invoice.id'));

        $returnResponse = $this->actingAs($this->owner)->postJson('/api/store/sales-returns', [
            'customer_id' => $customer->id,
            'sales_invoice_id' => $invoice->id,
            'refund_amount' => 0,
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $returnResponse->assertStatus(201);
        $returnData = $returnResponse->json('return');

        $searchByReturnNumber = $this->actingAs($this->owner)->getJson('/api/store/sales-returns?search=' . $returnData['return_number']);
        $searchByReturnNumber->assertStatus(200)
            ->assertJsonPath('0.return_number', $returnData['return_number']);

        $searchByInvoiceNumber = $this->actingAs($this->owner)->getJson('/api/store/sales-returns?search=' . $invoice->invoice_number);
        $searchByInvoiceNumber->assertStatus(200)
            ->assertJsonPath('0.invoice_number', $invoice->invoice_number);
    }

    public function test_can_search_purchase_return_by_return_number_and_invoice_number()
    {
        $supplier = Supplier::first();
        $variant = ProductVariant::first();

        $invoiceResponse = $this->actingAs($this->owner)->postJson('/api/store/purchase-invoices', [
            'supplier_id' => $supplier->id,
            'paid_amount' => 0,
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'ordered_quantity' => 1,
                    'received_quantity' => 1,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $invoiceResponse->assertStatus(201);
        $invoice = PurchaseInvoice::findOrFail($invoiceResponse->json('invoice.id'));

        $returnResponse = $this->actingAs($this->owner)->postJson('/api/store/purchase-returns', [
            'supplier_id' => $supplier->id,
            'purchase_invoice_id' => $invoice->id,
            'refund_amount' => 0,
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $returnResponse->assertStatus(201);
        $returnData = $returnResponse->json('return');

        $searchByReturnNumber = $this->actingAs($this->owner)->getJson('/api/store/purchase-returns?search=' . $returnData['return_number']);
        $searchByReturnNumber->assertStatus(200)
            ->assertJsonPath('0.return_number', $returnData['return_number']);

        $searchByInvoiceNumber = $this->actingAs($this->owner)->getJson('/api/store/purchase-returns?search=' . $invoice->invoice_number);
        $searchByInvoiceNumber->assertStatus(200)
            ->assertJsonPath('0.invoice_number', $invoice->invoice_number);
    }
}
