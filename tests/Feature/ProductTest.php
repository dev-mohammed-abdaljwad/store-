<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::where('email', 'store@ayad.com')->first();
        $this->admin = User::where('email', 'admin@admin.com')->first();
    }

    public function test_store_owner_can_list_categories()
    {
        $response = $this->actingAs($this->owner)->getJson('/api/store/categories');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        // $this->seed() inserts 'أسمدة ومبيدات'
        $this->assertEquals('أسمدة ومبيدات', $response->json()[0]['name']);
    }

    public function test_store_owner_can_create_category()
    {
        $response = $this->actingAs($this->owner)->postJson('/api/store/categories', [
            'name' => 'معدات زراعية',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'معدات زراعية');

        $this->assertDatabaseHas('categories', ['name' => 'معدات زراعية']);
    }

    public function test_store_owner_can_delete_category_if_empty()
    {
        $category = Category::create(['name' => 'فارغ']);
        $response = $this->actingAs($this->owner)->deleteJson("/api/store/categories/{$category->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_store_owner_cannot_delete_category_if_has_products()
    {
        $category = Category::first(); // this one has products from seeder
        $response = $this->actingAs($this->owner)->deleteJson("/api/store/categories/{$category->id}");

        // ValidationException returns 422
        $response->assertStatus(422);
    }

    public function test_store_owner_can_list_products()
    {
        $response = $this->actingAs($this->owner)->getJson('/api/store/products');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        // the DB Seeder creates 2 products, and they get updated from invoices, so their current_stock won't be 0
        $this->assertGreaterThanOrEqual(2, count($response->json()));
        $this->assertArrayHasKey('current_stock', $response->json()[0]); // verifies the map format
    }

    public function test_store_owner_can_create_product()
    {
        $category = Category::first();
        $response = $this->actingAs($this->owner)->postJson('/api/store/products', [
            'category_id'         => $category->id,
            'name'                => 'بذور طماطم',
            'sku'                 => 'SEED-001',
            'unit'                => 'كجم',
            'purchase_price'      => 10,
            'sale_price'          => 15,
            'low_stock_threshold' => 5,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'بذور طماطم');

        $this->assertDatabaseHas('products', ['sku' => 'SEED-001']);
    }

    public function test_store_owner_can_update_product()
    {
        $product = Product::first();
        $response = $this->actingAs($this->owner)->putJson("/api/store/products/{$product->id}", [
            'category_id' => $product->category_id,
            'name'        => 'Updated Name',
            'unit'        => 'لتر',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Updated Name');
    }

    public function test_store_owner_can_delete_product()
    {
        $product = Product::latest('id')->first();
        $response = $this->actingAs($this->owner)->deleteJson("/api/store/products/{$product->id}");

        $response->assertStatus(204);
        // because soft deletes
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
