<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoreSettingsController;
use App\Http\Controllers\Api\SuperAdmin\CashController;
use App\Http\Controllers\Api\SuperAdmin\CustomerController;
use App\Http\Controllers\Api\SuperAdmin\InventoryController;
use App\Http\Controllers\Api\SuperAdmin\PaymentController;
use App\Http\Controllers\Api\SuperAdmin\ProductController;
use App\Http\Controllers\Api\SuperAdmin\AttachmentController;
use App\Http\Controllers\Api\SuperAdmin\PurchaseInvoiceController;
use App\Http\Controllers\Api\SuperAdmin\SalesInvoiceController;
use App\Http\Controllers\Api\SuperAdmin\StoreController;
use App\Http\Controllers\Api\SuperAdmin\SupplierController;
use Illuminate\Support\Facades\Route;

//
Route::post('/auth/login', [AuthController::class, 'login']);
/**
 * admin routes
 */
Route::middleware(['auth:sanctum', 'role:super_admin'])
    ->prefix('admin')
    ->group(function () {

        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // إدارة المتاجر
        Route::get('/stores',                  [StoreController::class, 'index']);
        Route::post('/stores',                 [StoreController::class, 'store']);
        Route::post('/stores/{id}/activate',   [StoreController::class, 'activate']);
        Route::post('/stores/{id}/deactivate', [StoreController::class, 'deactivate']);
    });
Route::middleware(['auth:sanctum', 'role:store_owner', 'store.active'])
    ->prefix('store')
    ->group(function () {

        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // ── إعدادات المتجر ───────────────────────────────────────
        Route::get('/settings',                  [StoreSettingsController::class, 'show']);
        Route::put('/settings',                  [StoreSettingsController::class, 'update']);
        Route::post('/settings/logo',            [StoreSettingsController::class, 'uploadLogo']);
        Route::delete('/settings/logo',          [StoreSettingsController::class, 'deleteLogo']);
        Route::put('/settings/password',         [StoreSettingsController::class, 'changePassword']);

        // ── العملاء ──────────────────────────────────────────────
        Route::get('/customers',                      [CustomerController::class, 'index']);
        Route::post('/customers',                     [CustomerController::class, 'store']);
        Route::get('/customers/{id}',                 [CustomerController::class, 'show']);
        Route::put('/customers/{id}',                 [CustomerController::class, 'update']);
        Route::delete('/customers/{id}',              [CustomerController::class, 'destroy']);
        Route::get('/customers/{id}/statement',       [CustomerController::class, 'statement']);

        // ── الموردون ──────────────────────────────────────────────
        Route::get('/suppliers',                      [SupplierController::class, 'index']);
        Route::post('/suppliers',                     [SupplierController::class, 'store']);
        Route::get('/suppliers/{id}',                 [SupplierController::class, 'show']);
        Route::put('/suppliers/{id}',                 [SupplierController::class, 'update']);
        Route::delete('/suppliers/{id}',              [SupplierController::class, 'destroy']);
        Route::get('/suppliers/{id}/statement',       [SupplierController::class, 'statement']);

        // ── التصنيفات ─────────────────────────────────────────────
        Route::get('/categories',                     [ProductController::class, 'categories']);
        Route::get('/categories/summary',             [ProductController::class, 'categoriesSummary']);
        Route::post('/categories',                    [ProductController::class, 'storeCategory']);
        Route::delete('/categories/{id}',             [ProductController::class, 'destroyCategory']);

        // ── المنتجات ──────────────────────────────────────────────
        Route::get('/products',                       [ProductController::class, 'index']);
        Route::post('/products',                      [ProductController::class, 'store']);
        Route::put('/products/{id}',                  [ProductController::class, 'update']);
        Route::delete('/products/{id}',               [ProductController::class, 'destroy']);
        Route::post('/products/{productId}/variants', [ProductController::class, 'storeVariant']);
        Route::put('/products/{productId}/variants/{variantId}', [ProductController::class, 'updateVariant']);
        Route::delete('/products/{productId}/variants/{variantId}', [ProductController::class, 'destroyVariant']);
        Route::get('/products/dropdown',              [ProductController::class, 'dropdown']);
        Route::get('/inventory',                      [InventoryController::class, 'inventory']);

        // ── فواتير البيع ──────────────────────────────────────────
        Route::get('/sales-invoices',                 [SalesInvoiceController::class, 'index']);
        Route::post('/sales-invoices',                [SalesInvoiceController::class, 'store']);
        Route::get('/sales-invoices/{id}',            [SalesInvoiceController::class, 'show']);
        Route::post('/sales-invoices/{id}/cancel',    [SalesInvoiceController::class, 'cancel']);

        // ── فواتير الشراء ─────────────────────────────────────────
        Route::get('/purchase-invoices',              [PurchaseInvoiceController::class, 'index']);
        Route::post('/purchase-invoices',             [PurchaseInvoiceController::class, 'store']);
        Route::get('/purchase-invoices/{id}',         [PurchaseInvoiceController::class, 'show']);
        Route::post('/purchase-invoices/{id}/cancel', [PurchaseInvoiceController::class, 'cancel']);
        Route::post('/purchase-invoices/{id}/attachment', [AttachmentController::class, 'upload']);
        Route::get('/purchase-invoices/{id}/attachment', [AttachmentController::class, 'view'])
            ->name('store.purchase-invoices.attachment.view');
        Route::delete('/purchase-invoices/{id}/attachment', [AttachmentController::class, 'delete']);

        // ── المدفوعات ─────────────────────────────────────────────
        Route::post('/payments/customer',             [PaymentController::class, 'collectFromCustomer']);
        Route::post('/payments/supplier',             [PaymentController::class, 'payToSupplier']);

        // ── الكاش ─────────────────────────────────────────────────
        Route::post('/cash/opening-balance',          [CashController::class, 'openingBalance']);
        Route::get('/cash/balance',                   [CashController::class, 'currentBalance']);
        Route::get('/cash/daily-report',              [CashController::class, 'dailyReport']);
    });
