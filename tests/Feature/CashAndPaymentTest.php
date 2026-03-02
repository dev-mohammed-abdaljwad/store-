<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        // Since seed already sets up opening balance, we will test the exceptions or success flows.
        $this->seed();
        $this->owner = User::where('email', 'store@ayad.com')->first();
    }

    public function test_cannot_set_opening_balance_twice()
    {
        $response = $this->actingAs($this->owner)->postJson('/api/store/cash/opening-balance', [
            'amount' => 1000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('opening_balance');
    }

    public function test_can_get_current_balance()
    {
        $response = $this->actingAs($this->owner)->getJson('/api/store/cash/balance');

        $response->assertStatus(200);
        $this->assertArrayHasKey('balance', $response->json());
        // Seeder maths:
        // Opening = 50000
        // Purchase paid = 5000 (OUT)
        // Sale paid = 1000 (IN)
        // Customer Payment = 500 (IN)
        // Expected: 50000 - 5000 + 1000 + 500 = 46500.
        $this->assertEquals(46500, collect($response->json('balance'))->first() ?? $response->json('balance'));
    }

    public function test_can_get_daily_report()
    {
        $date = today()->toDateString();
        $response = $this->actingAs($this->owner)->getJson("/api/store/cash/daily-report?date={$date}");

        $response->assertStatus(200);
        $this->assertArrayHasKey('net', $response->json());
        $this->assertArrayHasKey('transactions', $response->json());
        $this->assertGreaterThan(0, count($response->json('transactions')));
    }

    public function test_can_collect_payment_from_customer()
    {
        $customer = Customer::first();

        $response = $this->actingAs($this->owner)->postJson('/api/store/payments/customer', [
            'party_id' => $customer->id,
            'amount' => 500,
            'notes' => 'Direct payment',
        ]);

        $response->assertStatus(200);

        // Balance should increase by 500
        $balanceResponse = $this->actingAs($this->owner)->getJson('/api/store/cash/balance');
        $this->assertEquals(47000, $balanceResponse->json('balance'));
    }

    public function test_can_pay_supplier()
    {
        $supplier = Supplier::first();

        $response = $this->actingAs($this->owner)->postJson('/api/store/payments/supplier', [
            'party_id' => $supplier->id,
            'amount' => 2000,
            'notes' => 'Pay to supplier',
        ]);

        $response->assertStatus(200);

        // Balance should decrease by 2000
        $balanceResponse = $this->actingAs($this->owner)->getJson('/api/store/cash/balance');
        $this->assertEquals(44500, $balanceResponse->json('balance'));
    }
}
