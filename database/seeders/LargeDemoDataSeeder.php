<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class LargeDemoDataSeeder extends Seeder
{
    private const PRODUCTS_COUNT = 100000;
    private const CUSTOMERS_COUNT = 10000;
    private const SUPPLIERS_COUNT = 1000;
    private const SALES_INVOICES_COUNT = 20000;
    private const PURCHASE_INVOICES_COUNT = 5000;
    private const PAYMENTS_COUNT = 20000;
    private const BATCH_SIZE = 1000;

    public function run(): void
    {
        $now = now();

        $this->truncateForFreshSeed();

        $superAdminId = $this->nextId('users');
        DB::table('users')->insert([
            'id' => $superAdminId,
            'is_active' => true,
            'store_id' => null,
            'name' => 'System Admin',
            'email' => 'admin@admin.com',
            'role' => 'super_admin',
            'email_verified_at' => $now,
            'password' => Hash::make('password'),
            'remember_token' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $storeId = $this->nextId('stores');
        DB::table('stores')->insert([
            'id' => $storeId,
            'name' => 'Ayad Group Store',
            'owner_name' => 'Store Owner',
            'email' => 'store@ayad.com',
            'phone' => '0500000000',
            'address' => 'Cairo',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $storeOwnerId = $superAdminId + 1;
        DB::table('users')->insert([
            'id' => $storeOwnerId,
            'is_active' => true,
            'store_id' => $storeId,
            'name' => 'Store Owner',
            'email' => 'owner@ayad.com',
            'role' => 'store_owner',
            'email_verified_at' => $now,
            'password' => Hash::make('password'),
            'remember_token' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('cash_transactions')->insert([
            'id' => $this->nextId('cash_transactions'),
            'store_id' => $storeId,
            'type' => 'opening_balance',
            'amount' => $this->money(5000000, 20000000),
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Opening balance',
            'transaction_date' => $now->toDateString(),
            'created_by' => $storeOwnerId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $categoryIds = $this->seedCategories($storeId, $now);
        [$productStartId, $productEndId] = $this->seedProducts($storeId, $categoryIds, $now);
        [$customerStartId, $customerEndId] = $this->seedCustomers($storeId, $now);
        [$supplierStartId, $supplierEndId] = $this->seedSuppliers($storeId, $now);

        $this->seedSalesInvoices(
            $storeId,
            $storeOwnerId,
            $customerStartId,
            $customerEndId,
            $productStartId,
            $productEndId,
            $now
        );

        $this->seedPurchaseInvoices(
            $storeId,
            $storeOwnerId,
            $supplierStartId,
            $supplierEndId,
            $productStartId,
            $productEndId,
            $now
        );

        $this->seedStandalonePayments(
            $storeId,
            $storeOwnerId,
            $customerStartId,
            $customerEndId,
            $supplierStartId,
            $supplierEndId,
            $now
        );
    }

    private function truncateForFreshSeed(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('personal_access_tokens')->truncate();
        DB::table('sessions')->truncate();
        DB::table('password_reset_tokens')->truncate();
        DB::table('cash_transactions')->truncate();
        DB::table('financial_transactions')->truncate();
        DB::table('stock_movements')->truncate();
        DB::table('purchase_invoice_items')->truncate();
        DB::table('purchase_invoices')->truncate();
        DB::table('sales_invoice_items')->truncate();
        DB::table('sales_invoices')->truncate();
        DB::table('products')->truncate();
        DB::table('suppliers')->truncate();
        DB::table('customers')->truncate();
        DB::table('categories')->truncate();
        DB::table('users')->truncate();
        DB::table('stores')->truncate();

        Schema::enableForeignKeyConstraints();
    }

    private function seedCategories(int $storeId, $now): array
    {
        $startId = $this->nextId('categories');
        $names = ['Fertilizers', 'Pesticides', 'Seeds', 'Tools'];
        $rows = [];

        foreach ($names as $index => $name) {
            $rows[] = [
                'id' => $startId + $index,
                'store_id' => $storeId,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
        }

        DB::table('categories')->insert($rows);

        return array_column($rows, 'id');
    }

    private function seedProducts(int $storeId, array $categoryIds, $now): array
    {
        $startId = $this->nextId('products');
        $units = ['piece', 'kg', 'liter', 'box'];
        $rows = [];

        for ($i = 0; $i < self::PRODUCTS_COUNT; $i++) {
            $id = $startId + $i;
            $purchasePrice = $this->money(500, 25000);
            $salePrice = $this->formatMoney($purchasePrice * (1 + (mt_rand(10, 45) / 100)));

            $rows[] = [
                'id' => $id,
                'store_id' => $storeId,
                'category_id' => $categoryIds[$i % count($categoryIds)],
                'name' => 'Product ' . $id,
                'sku' => 'SKU-' . str_pad((string) $id, 7, '0', STR_PAD_LEFT),
                'unit' => $units[$i % count($units)],
                'purchase_price' => $purchasePrice,
                'sale_price' => $salePrice,
                'low_stock_threshold' => mt_rand(2, 40),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            if (count($rows) >= self::BATCH_SIZE) {
                DB::table('products')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('products')->insert($rows);
        }

        return [$startId, $startId + self::PRODUCTS_COUNT - 1];
    }

    private function seedCustomers(int $storeId, $now): array
    {
        $startId = $this->nextId('customers');
        $rows = [];

        for ($i = 0; $i < self::CUSTOMERS_COUNT; $i++) {
            $id = $startId + $i;
            $rows[] = [
                'id' => $id,
                'store_id' => $storeId,
                'name' => 'Customer ' . $id,
                'phone' => '010' . str_pad((string) ($id % 100000000), 8, '0', STR_PAD_LEFT),
                'address' => 'Customer address ' . $id,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            if (count($rows) >= self::BATCH_SIZE) {
                DB::table('customers')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('customers')->insert($rows);
        }

        return [$startId, $startId + self::CUSTOMERS_COUNT - 1];
    }

    private function seedSuppliers(int $storeId, $now): array
    {
        $startId = $this->nextId('suppliers');
        $rows = [];

        for ($i = 0; $i < self::SUPPLIERS_COUNT; $i++) {
            $id = $startId + $i;
            $rows[] = [
                'id' => $id,
                'store_id' => $storeId,
                'name' => 'Supplier ' . $id,
                'phone' => '011' . str_pad((string) ($id % 100000000), 8, '0', STR_PAD_LEFT),
                'address' => 'Supplier address ' . $id,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            if (count($rows) >= self::BATCH_SIZE) {
                DB::table('suppliers')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('suppliers')->insert($rows);
        }

        return [$startId, $startId + self::SUPPLIERS_COUNT - 1];
    }

    private function seedSalesInvoices(
        int $storeId,
        int $createdBy,
        int $customerStartId,
        int $customerEndId,
        int $productStartId,
        int $productEndId,
        $now
    ): void {
        $nextInvoiceId = $this->nextId('sales_invoices');
        $nextItemId = $this->nextId('sales_invoice_items');
        $nextStockMovementId = $this->nextId('stock_movements');
        $nextFinancialTxId = $this->nextId('financial_transactions');
        $nextCashTxId = $this->nextId('cash_transactions');

        $invoiceRows = [];
        $itemRows = [];
        $stockRows = [];
        $financialRows = [];
        $cashRows = [];

        for ($i = 0; $i < self::SALES_INVOICES_COUNT; $i++) {
            $invoiceId = $nextInvoiceId++;
            $customerId = mt_rand($customerStartId, $customerEndId);
            $itemsCount = mt_rand(1, 4);
            $invoiceTotal = 0.0;

            for ($j = 0; $j < $itemsCount; $j++) {
                $quantity = $this->quantity(1, 8);
                $unitPrice = $this->money(500, 35000);
                $totalPrice = $this->formatMoney($quantity * $unitPrice);
                $productId = mt_rand($productStartId, $productEndId);

                $itemRows[] = [
                    'id' => $nextItemId++,
                    'invoice_id' => $invoiceId,
                    'product_id' => $productId,
                    'product_name' => 'Product ' . $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $stockRows[] = [
                    'id' => $nextStockMovementId++,
                    'store_id' => $storeId,
                    'product_id' => $productId,
                    'type' => 'out',
                    'quantity' => $quantity,
                    'reference_type' => 'sales_invoice',
                    'reference_id' => $invoiceId,
                    'notes' => null,
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $invoiceTotal += $totalPrice;
            }

            $invoiceTotal = $this->formatMoney($invoiceTotal);
            $paidRatio = mt_rand(15, 100) / 100;
            $paidAmount = $this->formatMoney($invoiceTotal * $paidRatio);
            $remainingAmount = $this->formatMoney($invoiceTotal - $paidAmount);

            $invoiceRows[] = [
                'id' => $invoiceId,
                'store_id' => $storeId,
                'invoice_number' => 'S-' . str_pad((string) $invoiceId, 10, '0', STR_PAD_LEFT),
                'customer_id' => $customerId,
                'total_amount' => $invoiceTotal,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'status' => 'confirmed',
                'notes' => null,
                'cancel_reason' => null,
                'cancelled_by' => null,
                'cancelled_at' => null,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            $financialRows[] = [
                'id' => $nextFinancialTxId++,
                'store_id' => $storeId,
                'party_type' => 'customer',
                'party_id' => $customerId,
                'type' => 'debit',
                'amount' => $invoiceTotal,
                'reference_type' => 'sales_invoice',
                'reference_id' => $invoiceId,
                'description' => null,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($paidAmount > 0) {
                $financialRows[] = [
                    'id' => $nextFinancialTxId++,
                    'store_id' => $storeId,
                    'party_type' => 'customer',
                    'party_id' => $customerId,
                    'type' => 'credit',
                    'amount' => $paidAmount,
                    'reference_type' => 'payment',
                    'reference_id' => $invoiceId,
                    'description' => 'Initial payment',
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $cashRows[] = [
                    'id' => $nextCashTxId++,
                    'store_id' => $storeId,
                    'type' => 'in',
                    'amount' => $paidAmount,
                    'reference_type' => 'sales_invoice',
                    'reference_id' => $invoiceId,
                    'description' => 'Initial payment for sales invoice',
                    'transaction_date' => $now->toDateString(),
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($invoiceRows) >= self::BATCH_SIZE) {
                $this->flushCommerceBatches($invoiceRows, $itemRows, $stockRows, $financialRows, $cashRows);
            }
        }

        $this->flushCommerceBatches($invoiceRows, $itemRows, $stockRows, $financialRows, $cashRows);
    }

    private function seedPurchaseInvoices(
        int $storeId,
        int $createdBy,
        int $supplierStartId,
        int $supplierEndId,
        int $productStartId,
        int $productEndId,
        $now
    ): void {
        $nextInvoiceId = $this->nextId('purchase_invoices');
        $nextItemId = $this->nextId('purchase_invoice_items');
        $nextStockMovementId = $this->nextId('stock_movements');
        $nextFinancialTxId = $this->nextId('financial_transactions');
        $nextCashTxId = $this->nextId('cash_transactions');

        $invoiceRows = [];
        $itemRows = [];
        $stockRows = [];
        $financialRows = [];
        $cashRows = [];

        for ($i = 0; $i < self::PURCHASE_INVOICES_COUNT; $i++) {
            $invoiceId = $nextInvoiceId++;
            $supplierId = mt_rand($supplierStartId, $supplierEndId);
            $itemsCount = mt_rand(1, 5);
            $invoiceTotal = 0.0;

            for ($j = 0; $j < $itemsCount; $j++) {
                $orderedQuantity = $this->quantity(2, 20);
                $receivedQuantity = $orderedQuantity;
                $unitPrice = $this->money(300, 22000);
                $totalPrice = $this->formatMoney($receivedQuantity * $unitPrice);
                $productId = mt_rand($productStartId, $productEndId);

                $itemRows[] = [
                    'id' => $nextItemId++,
                    'invoice_id' => $invoiceId,
                    'product_id' => $productId,
                    'product_name' => 'Product ' . $productId,
                    'ordered_quantity' => $orderedQuantity,
                    'received_quantity' => $receivedQuantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $stockRows[] = [
                    'id' => $nextStockMovementId++,
                    'store_id' => $storeId,
                    'product_id' => $productId,
                    'type' => 'in',
                    'quantity' => $receivedQuantity,
                    'reference_type' => 'purchase_invoice',
                    'reference_id' => $invoiceId,
                    'notes' => null,
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $invoiceTotal += $totalPrice;
            }

            $invoiceTotal = $this->formatMoney($invoiceTotal);
            $paidRatio = mt_rand(10, 90) / 100;
            $paidAmount = $this->formatMoney($invoiceTotal * $paidRatio);
            $remainingAmount = $this->formatMoney($invoiceTotal - $paidAmount);

            $invoiceRows[] = [
                'id' => $invoiceId,
                'store_id' => $storeId,
                'invoice_number' => 'P-' . str_pad((string) $invoiceId, 10, '0', STR_PAD_LEFT),
                'supplier_id' => $supplierId,
                'total_amount' => $invoiceTotal,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'status' => 'confirmed',
                'notes' => null,
                'cancel_reason' => null,
                'cancelled_by' => null,
                'cancelled_at' => null,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            $financialRows[] = [
                'id' => $nextFinancialTxId++,
                'store_id' => $storeId,
                'party_type' => 'supplier',
                'party_id' => $supplierId,
                'type' => 'credit',
                'amount' => $invoiceTotal,
                'reference_type' => 'purchase_invoice',
                'reference_id' => $invoiceId,
                'description' => null,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($paidAmount > 0) {
                $financialRows[] = [
                    'id' => $nextFinancialTxId++,
                    'store_id' => $storeId,
                    'party_type' => 'supplier',
                    'party_id' => $supplierId,
                    'type' => 'debit',
                    'amount' => $paidAmount,
                    'reference_type' => 'payment',
                    'reference_id' => $invoiceId,
                    'description' => 'Initial payment',
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $cashRows[] = [
                    'id' => $nextCashTxId++,
                    'store_id' => $storeId,
                    'type' => 'out',
                    'amount' => $paidAmount,
                    'reference_type' => 'purchase_invoice',
                    'reference_id' => $invoiceId,
                    'description' => 'Initial payment for purchase invoice',
                    'transaction_date' => $now->toDateString(),
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($invoiceRows) >= self::BATCH_SIZE) {
                $this->flushPurchaseBatches($invoiceRows, $itemRows, $stockRows, $financialRows, $cashRows);
            }
        }

        $this->flushPurchaseBatches($invoiceRows, $itemRows, $stockRows, $financialRows, $cashRows);
    }

    private function seedStandalonePayments(
        int $storeId,
        int $createdBy,
        int $customerStartId,
        int $customerEndId,
        int $supplierStartId,
        int $supplierEndId,
        $now
    ): void {
        $nextFinancialTxId = $this->nextId('financial_transactions');
        $nextCashTxId = $this->nextId('cash_transactions');

        $financialRows = [];
        $cashRows = [];

        for ($i = 1; $i <= self::PAYMENTS_COUNT; $i++) {
            $isCustomerPayment = mt_rand(0, 1) === 1;
            $amount = $this->money(100, 40000);

            if ($isCustomerPayment) {
                $partyType = 'customer';
                $partyId = mt_rand($customerStartId, $customerEndId);
                $financialType = 'credit';
                $cashType = 'in';
            } else {
                $partyType = 'supplier';
                $partyId = mt_rand($supplierStartId, $supplierEndId);
                $financialType = 'debit';
                $cashType = 'out';
            }

            $financialRows[] = [
                'id' => $nextFinancialTxId++,
                'store_id' => $storeId,
                'party_type' => $partyType,
                'party_id' => $partyId,
                'type' => $financialType,
                'amount' => $amount,
                'reference_type' => 'payment',
                'reference_id' => $i,
                'description' => 'Standalone payment',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $cashRows[] = [
                'id' => $nextCashTxId++,
                'store_id' => $storeId,
                'type' => $cashType,
                'amount' => $amount,
                'reference_type' => 'payment',
                'reference_id' => $i,
                'description' => 'Standalone payment',
                'transaction_date' => $now->toDateString(),
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($financialRows) >= self::BATCH_SIZE) {
                DB::table('financial_transactions')->insert($financialRows);
                DB::table('cash_transactions')->insert($cashRows);
                $financialRows = [];
                $cashRows = [];
            }
        }

        if ($financialRows !== []) {
            DB::table('financial_transactions')->insert($financialRows);
            DB::table('cash_transactions')->insert($cashRows);
        }
    }

    private function flushCommerceBatches(
        array &$invoiceRows,
        array &$itemRows,
        array &$stockRows,
        array &$financialRows,
        array &$cashRows
    ): void {
        if ($invoiceRows === []) {
            return;
        }

        DB::table('sales_invoices')->insert($invoiceRows);
        DB::table('sales_invoice_items')->insert($itemRows);
        DB::table('stock_movements')->insert($stockRows);
        DB::table('financial_transactions')->insert($financialRows);

        if ($cashRows !== []) {
            DB::table('cash_transactions')->insert($cashRows);
        }

        $invoiceRows = [];
        $itemRows = [];
        $stockRows = [];
        $financialRows = [];
        $cashRows = [];
    }

    private function flushPurchaseBatches(
        array &$invoiceRows,
        array &$itemRows,
        array &$stockRows,
        array &$financialRows,
        array &$cashRows
    ): void {
        if ($invoiceRows === []) {
            return;
        }

        DB::table('purchase_invoices')->insert($invoiceRows);
        DB::table('purchase_invoice_items')->insert($itemRows);
        DB::table('stock_movements')->insert($stockRows);
        DB::table('financial_transactions')->insert($financialRows);

        if ($cashRows !== []) {
            DB::table('cash_transactions')->insert($cashRows);
        }

        $invoiceRows = [];
        $itemRows = [];
        $stockRows = [];
        $financialRows = [];
        $cashRows = [];
    }

    private function quantity(int $min, int $max): string
    {
        return number_format((float) mt_rand($min * 1000, $max * 1000) / 1000, 3, '.', '');
    }

    private function money(int $minCents, int $maxCents): string
    {
        return number_format((float) mt_rand($minCents, $maxCents) / 100, 2, '.', '');
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function nextId(string $table): int
    {
        return ((int) DB::table($table)->max('id')) + 1;
    }
}
