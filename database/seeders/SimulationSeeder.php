<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\StockMovement;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use App\Models\CashTransaction;
use App\Models\FinancialTransaction;
use App\Models\Payment;
use App\Domain\Store\Enums\InvoiceStatus;

class SimulationSeeder extends Seeder
{
    private int $storeId;
    private int $createdByUserId;

    public function run(): void
    {
        // ── 1. Store + Admin ──────────────────────────────────────────
        $store = Store::firstOrCreate([
            'email' => 'simulation@ayad-agro.com',
        ], [
            'name'       => 'مستلزمات الزراعة - عياد جروب',
            'owner_name' => 'عياد جروب',
            'slug'       => 'ayad-agro',
            'phone'      => '01001234567',
            'address'    => 'شارع الزراعة، القاهرة',
            'is_active'  => true,
        ]);

        $this->storeId = $store->id;

        $owner = User::firstOrCreate([
            'email' => 'admin@ayad.com',
        ], [
            'store_id' => $store->id,
            'name'     => 'أحمد صلاح',
            'password' => bcrypt('password123'),
            'role'     => 'store_owner',
            'is_active'=> true,
        ]);

        // Ensure owner is associated with this store
        if ($owner->store_id !== $store->id) {
            $owner->store_id = $store->id;
            $owner->save();
        }

        $this->createdByUserId = $owner->id;

        // ── 2. Categories ─────────────────────────────────────────────
        $categories = $this->seedCategories();

        // ── 3. Suppliers ──────────────────────────────────────────────
        $suppliers = $this->seedSuppliers();

        // ── 4. Customers ──────────────────────────────────────────────
        $customers = $this->seedCustomers();

        // ── 5. Products + Variants ────────────────────────────────────
        $this->seedProducts($categories);

        $this->command->info('✅ Simulation Seeder completed successfully!');
        $this->command->info("   Store ID: {$store->id}");
        $this->command->info("   Categories: " . count($categories));
        $this->command->info("   Suppliers: " . count($suppliers));
        $this->command->info("   Customers: " . count($customers));
        $this->command->info("   Products: " . Product::withoutGlobalScopes()->where('store_id', $store->id)->count());
        $this->command->info("   Variants: " . ProductVariant::withoutGlobalScopes()->where('store_id', $store->id)->count());
    }

    // ────────────────────────────────────────────────────────────────
    private function seedCategories(): array
    {
        $names = [
            'مبيدات حشرية',
            'مبيدات فطرية',
            'مبيدات أعشاب',
            'أسمدة كيماوية',
            'أسمدة عضوية',
            'منظمات نمو',
            'مطهرات تربة',
            'معدات ورشاشات',
        ];

        $categories = [];
        foreach ($names as $name) {
            $categories[$name] = Category::withoutGlobalScopes()->firstOrCreate([
                'store_id' => $this->storeId,
                'name'     => $name,
            ]);
        }
        return $categories;
    }

    // ────────────────────────────────────────────────────────────────
    private function seedSuppliers(): array
    {
        $data = [
            ['name' => 'شركة سينجنتا مصر',          'phone' => '0221345678', 'address' => 'القاهرة الجديدة', 'balance' => 15000],
            ['name' => 'بايير للمبيدات الزراعية',    'phone' => '0223456789', 'address' => 'المهندسين، الجيزة', 'balance' => 8500],
            ['name' => 'شركة الدلتا للمبيدات',       'phone' => '0403334455', 'address' => 'المنصورة، الدقهلية', 'balance' => 12000],
            ['name' => 'مستلزمات النيل الزراعية',    'phone' => '0224567890', 'address' => 'شبرا، القاهرة', 'balance' => 5000],
            ['name' => 'شركة الوادي للأسمدة',        'phone' => '0865556677', 'address' => 'أسيوط', 'balance' => 9800],
            ['name' => 'الشركة العربية للزراعة',     'phone' => '0453334422', 'address' => 'طنطا، الغربية', 'balance' => 3200],
            ['name' => 'مستودعات السلام الزراعية',   'phone' => '0554443311', 'address' => 'الإسكندرية', 'balance' => 7600],
            ['name' => 'شركة فرتيلايزر مصر',         'phone' => '0224441199', 'address' => 'مدينة نصر، القاهرة', 'balance' => 11200],
        ];

        $suppliers = [];
        foreach ($data as $row) {
            unset($row['balance']);
            $suppliers[] = Supplier::create(array_merge($row, ['store_id' => $this->storeId]));
        }
        // Create sample purchase invoices and payments for first few suppliers
        foreach (array_slice($suppliers, 0, 4) as $i => $supplier) {
            $invoiceDate = now()->subDays(8 - $i)->toDateString();
            $total = 800 + ($i * 400);
            $paid = $i % 2 === 1 ? ($total * 0.4) : 0; // alternate paid

            $invoice = PurchaseInvoice::create([
                'store_id'         => $this->storeId,
                'invoice_number'   => PurchaseInvoice::generateNumber($this->storeId),
                'invoice_date'     => $invoiceDate,
                'supplier_id'      => $supplier->id,
                'total_amount'     => $total,
                'paid_amount'      => $paid,
                'remaining_amount' => $total - $paid,
                'status'           => InvoiceStatus::CONFIRMED,
                'created_by'       => $this->createdByUserId,
            ]);

            if ($paid > 0) {
                CashTransaction::create([
                    'store_id'         => $this->storeId,
                    'type'             => 'out',
                    'amount'           => $paid,
                    'reference_type'   => 'purchase_invoice',
                    'reference_id'     => $invoice->id,
                    'description'      => "Payment for purchase {$invoice->invoice_number}",
                    'transaction_date' => $invoiceDate,
                    'created_by'       => $this->createdByUserId,
                ]);

                FinancialTransaction::create([
                    'store_id'       => $this->storeId,
                    'party_type'     => 'supplier',
                    'party_id'       => $supplier->id,
                    'type'           => 'debit',
                    'amount'         => $paid,
                    'reference_type' => 'purchase_invoice',
                    'reference_id'   => $invoice->id,
                    'description'    => "Payment applied",
                    'created_by'     => $this->createdByUserId,
                ]);
            }
        }
        return $suppliers;
    }

    // ────────────────────────────────────────────────────────────────
    private function seedCustomers(): array
    {
        $data = [
            ['name' => 'محمد عبد الرحمن',     'phone' => '01012345678', 'address' => 'بنها، القليوبية',      'balance' => 4500],
            ['name' => 'أحمد السيد إبراهيم',  'phone' => '01098765432', 'address' => 'المنصورة، الدقهلية',  'balance' => 12000],
            ['name' => 'حسن محمود علي',        'phone' => '01154321098', 'address' => 'الزقازيق، الشرقية',   'balance' => 800],
            ['name' => 'خالد فتحي حسين',       'phone' => '01223344556', 'address' => 'طنطا، الغربية',       'balance' => 6700],
            ['name' => 'عمر عبد الله محمد',    'phone' => '01067891234', 'address' => 'أسيوط',               'balance' => 0],
            ['name' => 'يوسف سامي النجار',     'phone' => '01189012345', 'address' => 'دمياط',               'balance' => 3100],
            ['name' => 'طارق رمضان عوض',       'phone' => '01534567890', 'address' => 'سوهاج',               'balance' => 9200],
            ['name' => 'إبراهيم مصطفى سالم',  'phone' => '01245678901', 'address' => 'قنا',                 'balance' => 1500],
            ['name' => 'عبد العزيز محمد ربيع', 'phone' => '01378901234', 'address' => 'الفيوم',              'balance' => 5500],
            ['name' => 'سامي جلال عثمان',      'phone' => '01456789012', 'address' => 'المنيا',              'balance' => 0],
            ['name' => 'رامي وليد الشرقاوي',   'phone' => '01112233445', 'address' => 'الإسماعيلية',        'balance' => 7800],
            ['name' => 'علي حسن الصعيدي',      'phone' => '01567890123', 'address' => 'بني سويف',            'balance' => 2300],
            ['name' => 'محمود أحمد الجمال',    'phone' => '01690123456', 'address' => 'كفر الشيخ',          'balance' => 4100],
            ['name' => 'وليد عصام الدين',      'phone' => '01701234567', 'address' => 'البحيرة',             'balance' => 16000],
            ['name' => 'هشام نبيل السيد',      'phone' => '01812345678', 'address' => 'الغربية',             'balance' => 950],
            ['name' => 'عادل فريد منصور',      'phone' => '01923456789', 'address' => 'الدقهلية',            'balance' => 3700],
            ['name' => 'كريم صلاح عبد الحميد', 'phone' => '01034567890', 'address' => 'الشرقية',             'balance' => 0],
            ['name' => 'ماجد توفيق الزيات',    'phone' => '01145678901', 'address' => 'الجيزة',              'balance' => 8800],
            ['name' => 'باسم جورج حنا',        'phone' => '01256789012', 'address' => 'أسيوط',               'balance' => 1200],
            ['name' => 'تامر عبد الفتاح راضي', 'phone' => '01367890123', 'address' => 'سوهاج',               'balance' => 5900],
        ];

        $customers = [];
        foreach ($data as $row) {
            unset($row['balance']);
            $customers[] = Customer::create(array_merge($row, ['store_id' => $this->storeId]));
        }
        // Create sample sales invoices and payments for first few customers
        foreach (array_slice($customers, 0, 4) as $i => $customer) {
            $invoiceDate = now()->subDays(10 - $i)->toDateString();
            $total = 1000 + ($i * 500);
            $paid = $i % 2 === 0 ? ($total * 0.5) : 0; // some partially paid, some unpaid

            $invoice = SalesInvoice::create([
                'store_id'        => $this->storeId,
                'invoice_number'  => SalesInvoice::generateNumber($this->storeId),
                'invoice_date'    => $invoiceDate,
                'customer_id'     => $customer->id,
                'total_amount'    => $total,
                'discount_amount' => 0,
                'net_amount'      => $total,
                'paid_amount'     => $paid,
                'remaining_amount'=> $total - $paid,
                'status'          => InvoiceStatus::CONFIRMED,
                'created_by'      => $this->createdByUserId,
            ]);

            // create cash transaction and financial transaction for paid amount
            if ($paid > 0) {
                CashTransaction::create([
                    'store_id'         => $this->storeId,
                    'type'             => 'in',
                    'amount'           => $paid,
                    'reference_type'   => 'sales_invoice',
                    'reference_id'     => $invoice->id,
                    'description'      => "Payment for invoice {$invoice->invoice_number}",
                    'transaction_date' => $invoiceDate,
                    'created_by'       => $this->createdByUserId,
                ]);

                FinancialTransaction::create([
                    'store_id'       => $this->storeId,
                    'party_type'     => 'customer',
                    'party_id'       => $customer->id,
                    'type'           => 'credit',
                    'amount'         => $paid,
                    'reference_type' => 'sales_invoice',
                    'reference_id'   => $invoice->id,
                    'description'    => "Payment applied",
                    'created_by'     => $this->createdByUserId,
                ]);
            }
        }

        // Create 100 test payment collection records for pagination testing
        $firstCustomer = $customers[0] ?? null;
        if ($firstCustomer) {
            for ($i = 1; $i <= 100; $i++) {
                $amount = rand(500, 15000);
                $paymentDate = now()->subDays(101 - $i)->toDateString();
                
                $payment = Payment::create([
                    'store_id' => $this->storeId,
                    'party_type' => 'customer',
                    'party_id' => $firstCustomer->id,
                    'amount' => $amount,
                    'payment_number' => sprintf('PM-TEST-%04d', $i),
                    'payment_date' => $paymentDate,
                    'description' => "دفعة تجريبية رقم {$i} لاختبار Pagination",
                    'receipt_number' => sprintf('RC-TEST-%04d', $i),
                    'created_by' => $this->createdByUserId,
                ]);

                FinancialTransaction::create([
                    'store_id'       => $this->storeId,
                    'party_type'     => 'customer',
                    'party_id'       => $firstCustomer->id,
                    'type'           => 'credit',
                    'amount'         => $amount,
                    'reference_type' => 'payment',
                    'reference_id'   => $payment->id,
                    'description'    => "تحصيل نقدي مباشر من العميل: {$firstCustomer->id} (تجريبي رقم {$i})",
                    'receipt_number' => sprintf('RC-TEST-%04d', $i),
                    'created_by'     => $this->createdByUserId,
                ]);

                CashTransaction::create([
                    'store_id'         => $this->storeId,
                    'type'             => 'in',
                    'amount'           => $amount,
                    'reference_type'   => 'payment',
                    'reference_id'     => $payment->id,
                    'description'      => "تحصيل نقدي مباشر من العميل: {$firstCustomer->id} (تجريبي رقم {$i})",
                    'transaction_date' => $paymentDate,
                    'created_by'       => $this->createdByUserId,
                ]);
            }
        }

        return $customers;
    }

    // ────────────────────────────────────────────────────────────────
    private function seedProducts(array $categories): void
    {
        $products = [

            // ── مبيدات حشرية ──────────────────────────────────────
            ['cat' => 'مبيدات حشرية', 'name' => 'ديازينون 60%', 'variants' => [
                ['name' => '100 مل',  'buy' => 18,  'sell' => 28,  'stock' => 120],
                ['name' => '500 مل',  'buy' => 75,  'sell' => 110, 'stock' => 85],
                ['name' => '1 لتر',   'buy' => 140, 'sell' => 200, 'stock' => 40],
            ]],
            ['cat' => 'مبيدات حشرية', 'name' => 'كلوربيريفوس 48%', 'variants' => [
                ['name' => '250 مل',  'buy' => 35,  'sell' => 55,  'stock' => 95],
                ['name' => '1 لتر',   'buy' => 120, 'sell' => 180, 'stock' => 60],
                ['name' => '5 لتر',   'buy' => 550, 'sell' => 800, 'stock' => 15],
            ]],
            ['cat' => 'مبيدات حشرية', 'name' => 'إيميداكلوبريد 20%', 'variants' => [
                ['name' => '100 مل',  'buy' => 45,  'sell' => 70,  'stock' => 150],
                ['name' => '250 مل',  'buy' => 100, 'sell' => 155, 'stock' => 70],
                ['name' => '1 لتر',   'buy' => 380, 'sell' => 560, 'stock' => 25],
            ]],
            ['cat' => 'مبيدات حشرية', 'name' => 'لامبدا سيهالوثرين', 'variants' => [
                ['name' => '100 مل',  'buy' => 22,  'sell' => 35,  'stock' => 200],
                ['name' => '500 مل',  'buy' => 95,  'sell' => 145, 'stock' => 90],
            ]],
            ['cat' => 'مبيدات حشرية', 'name' => 'أبامكتين 1.8%', 'variants' => [
                ['name' => '50 مل',   'buy' => 30,  'sell' => 48,  'stock' => 180],
                ['name' => '250 مل',  'buy' => 130, 'sell' => 200, 'stock' => 65],
                ['name' => '1 لتر',   'buy' => 480, 'sell' => 720, 'stock' => 20],
            ]],
            ['cat' => 'مبيدات حشرية', 'name' => 'تيامثوكسام 25%', 'variants' => [
                ['name' => '100 جم',  'buy' => 55,  'sell' => 85,  'stock' => 110],
                ['name' => '250 جم',  'buy' => 120, 'sell' => 185, 'stock' => 55],
                ['name' => '1 كجم',   'buy' => 440, 'sell' => 660, 'stock' => 18],
            ]],
            ['cat' => 'مبيدات حشرية', 'name' => 'سبينوساد 24%', 'variants' => [
                ['name' => '100 مل',  'buy' => 80,  'sell' => 125, 'stock' => 75],
                ['name' => '500 مل',  'buy' => 350, 'sell' => 530, 'stock' => 30],
            ]],
            ['cat' => 'مبيدات حشرية', 'name' => 'إندوكساكارب 15%', 'variants' => [
                ['name' => '100 مل',  'buy' => 65,  'sell' => 100, 'stock' => 90],
                ['name' => '250 مل',  'buy' => 150, 'sell' => 230, 'stock' => 40],
            ]],

            // ── مبيدات فطرية ──────────────────────────────────────
            ['cat' => 'مبيدات فطرية', 'name' => 'مانكوزيب 80%', 'variants' => [
                ['name' => '200 جم',  'buy' => 20,  'sell' => 32,  'stock' => 300],
                ['name' => '1 كجم',   'buy' => 85,  'sell' => 130, 'stock' => 120],
                ['name' => '5 كجم',   'buy' => 390, 'sell' => 580, 'stock' => 35],
            ]],
            ['cat' => 'مبيدات فطرية', 'name' => 'كوبروكسات', 'variants' => [
                ['name' => '250 مل',  'buy' => 28,  'sell' => 44,  'stock' => 200],
                ['name' => '1 لتر',   'buy' => 100, 'sell' => 155, 'stock' => 80],
            ]],
            ['cat' => 'مبيدات فطرية', 'name' => 'بروبيكونازول 25%', 'variants' => [
                ['name' => '100 مل',  'buy' => 40,  'sell' => 62,  'stock' => 140],
                ['name' => '500 مل',  'buy' => 180, 'sell' => 275, 'stock' => 50],
                ['name' => '1 لتر',   'buy' => 340, 'sell' => 510, 'stock' => 22],
            ]],
            ['cat' => 'مبيدات فطرية', 'name' => 'تيبوكونازول 25%', 'variants' => [
                ['name' => '250 مل',  'buy' => 95,  'sell' => 148, 'stock' => 85],
                ['name' => '1 لتر',   'buy' => 350, 'sell' => 530, 'stock' => 28],
            ]],
            ['cat' => 'مبيدات فطرية', 'name' => 'أزوكسيستروبين 25%', 'variants' => [
                ['name' => '100 مل',  'buy' => 85,  'sell' => 132, 'stock' => 60],
                ['name' => '250 مل',  'buy' => 195, 'sell' => 300, 'stock' => 35],
            ]],

            // ── مبيدات أعشاب ──────────────────────────────────────
            ['cat' => 'مبيدات أعشاب', 'name' => 'جلايفوسيت 48%', 'variants' => [
                ['name' => '500 مل',  'buy' => 30,  'sell' => 48,  'stock' => 250],
                ['name' => '1 لتر',   'buy' => 55,  'sell' => 85,  'stock' => 150],
                ['name' => '5 لتر',   'buy' => 250, 'sell' => 380, 'stock' => 45],
                ['name' => '20 لتر',  'buy' => 950, 'sell' => 1400,'stock' => 12],
            ]],
            ['cat' => 'مبيدات أعشاب', 'name' => 'أتروزين 50%', 'variants' => [
                ['name' => '500 جم',  'buy' => 38,  'sell' => 60,  'stock' => 180],
                ['name' => '1 كجم',   'buy' => 70,  'sell' => 108, 'stock' => 90],
            ]],
            ['cat' => 'مبيدات أعشاب', 'name' => 'بندميثالين 33%', 'variants' => [
                ['name' => '500 مل',  'buy' => 45,  'sell' => 70,  'stock' => 130],
                ['name' => '1 لتر',   'buy' => 85,  'sell' => 130, 'stock' => 65],
                ['name' => '5 لتر',   'buy' => 390, 'sell' => 590, 'stock' => 20],
            ]],
            ['cat' => 'مبيدات أعشاب', 'name' => 'هالوكسيفوب 10.8%', 'variants' => [
                ['name' => '500 مل',  'buy' => 75,  'sell' => 115, 'stock' => 95],
                ['name' => '1 لتر',   'buy' => 140, 'sell' => 215, 'stock' => 45],
            ]],

            // ── أسمدة كيماوية ─────────────────────────────────────
            ['cat' => 'أسمدة كيماوية', 'name' => 'نيتروجين يوريا 46%', 'variants' => [
                ['name' => '1 كجم',   'buy' => 18,  'sell' => 28,  'stock' => 500],
                ['name' => '5 كجم',   'buy' => 85,  'sell' => 130, 'stock' => 200],
                ['name' => '25 كجم',  'buy' => 400, 'sell' => 600, 'stock' => 60],
                ['name' => '50 كجم',  'buy' => 780, 'sell' => 1150,'stock' => 25],
            ]],
            ['cat' => 'أسمدة كيماوية', 'name' => 'نيتروفوسكا 12-12-17', 'variants' => [
                ['name' => '1 كجم',   'buy' => 25,  'sell' => 38,  'stock' => 350],
                ['name' => '5 كجم',   'buy' => 115, 'sell' => 175, 'stock' => 130],
                ['name' => '25 كجم',  'buy' => 550, 'sell' => 820, 'stock' => 40],
            ]],
            ['cat' => 'أسمدة كيماوية', 'name' => 'كالسيوم نيترات', 'variants' => [
                ['name' => '1 كجم',   'buy' => 22,  'sell' => 35,  'stock' => 280],
                ['name' => '5 كجم',   'buy' => 100, 'sell' => 155, 'stock' => 100],
                ['name' => '25 كجم',  'buy' => 480, 'sell' => 720, 'stock' => 30],
            ]],
            ['cat' => 'أسمدة كيماوية', 'name' => 'بوتاسيوم سلفات', 'variants' => [
                ['name' => '500 جم',  'buy' => 30,  'sell' => 47,  'stock' => 220],
                ['name' => '1 كجم',   'buy' => 55,  'sell' => 85,  'stock' => 160],
                ['name' => '5 كجم',   'buy' => 260, 'sell' => 395, 'stock' => 55],
            ]],
            ['cat' => 'أسمدة كيماوية', 'name' => 'مونوبوتاسيوم فوسفات', 'variants' => [
                ['name' => '500 جم',  'buy' => 35,  'sell' => 55,  'stock' => 190],
                ['name' => '1 كجم',   'buy' => 65,  'sell' => 100, 'stock' => 110],
                ['name' => '5 كجم',   'buy' => 300, 'sell' => 460, 'stock' => 38],
            ]],
            ['cat' => 'أسمدة كيماوية', 'name' => 'سلفات الحديد', 'variants' => [
                ['name' => '500 جم',  'buy' => 20,  'sell' => 32,  'stock' => 240],
                ['name' => '1 كجم',   'buy' => 38,  'sell' => 58,  'stock' => 130],
            ]],

            // ── أسمدة عضوية ───────────────────────────────────────
            ['cat' => 'أسمدة عضوية', 'name' => 'هيوميك أسيد', 'variants' => [
                ['name' => '500 مل',  'buy' => 40,  'sell' => 62,  'stock' => 160],
                ['name' => '1 لتر',   'buy' => 75,  'sell' => 115, 'stock' => 90],
                ['name' => '5 لتر',   'buy' => 340, 'sell' => 510, 'stock' => 28],
            ]],
            ['cat' => 'أسمدة عضوية', 'name' => 'فولفيك أسيد', 'variants' => [
                ['name' => '500 مل',  'buy' => 55,  'sell' => 85,  'stock' => 120],
                ['name' => '1 لتر',   'buy' => 100, 'sell' => 155, 'stock' => 65],
            ]],
            ['cat' => 'أسمدة عضوية', 'name' => 'أحماض أمينية', 'variants' => [
                ['name' => '500 مل',  'buy' => 48,  'sell' => 74,  'stock' => 145],
                ['name' => '1 لتر',   'buy' => 90,  'sell' => 138, 'stock' => 70],
                ['name' => '5 لتر',   'buy' => 420, 'sell' => 630, 'stock' => 22],
            ]],
            ['cat' => 'أسمدة عضوية', 'name' => 'سيويد أعشاب بحرية', 'variants' => [
                ['name' => '250 مل',  'buy' => 38,  'sell' => 58,  'stock' => 175],
                ['name' => '1 لتر',   'buy' => 130, 'sell' => 200, 'stock' => 80],
            ]],

            // ── منظمات نمو ────────────────────────────────────────
            ['cat' => 'منظمات نمو', 'name' => 'جبريلين GA3', 'variants' => [
                ['name' => '10 جم',   'buy' => 35,  'sell' => 55,  'stock' => 200],
                ['name' => '50 جم',   'buy' => 150, 'sell' => 230, 'stock' => 75],
            ]],
            ['cat' => 'منظمات نمو', 'name' => 'سيتوكينين', 'variants' => [
                ['name' => '100 مل',  'buy' => 55,  'sell' => 85,  'stock' => 140],
                ['name' => '500 مل',  'buy' => 240, 'sell' => 370, 'stock' => 50],
            ]],
            ['cat' => 'منظمات نمو', 'name' => 'إيثيفون 48%', 'variants' => [
                ['name' => '100 مل',  'buy' => 30,  'sell' => 47,  'stock' => 160],
                ['name' => '500 مل',  'buy' => 130, 'sell' => 200, 'stock' => 60],
                ['name' => '1 لتر',   'buy' => 250, 'sell' => 380, 'stock' => 25],
            ]],
            ['cat' => 'منظمات نمو', 'name' => 'حمض الإندول أسيتيك', 'variants' => [
                ['name' => '50 جم',   'buy' => 45,  'sell' => 70,  'stock' => 130],
                ['name' => '100 جم',  'buy' => 85,  'sell' => 130, 'stock' => 65],
            ]],

            // ── مطهرات تربة ───────────────────────────────────────
            ['cat' => 'مطهرات تربة', 'name' => 'ميثيل برومايد', 'variants' => [
                ['name' => '250 جم',  'buy' => 60,  'sell' => 94,  'stock' => 80],
                ['name' => '1 كجم',   'buy' => 220, 'sell' => 340, 'stock' => 30],
            ]],
            ['cat' => 'مطهرات تربة', 'name' => 'دازوميت 98%', 'variants' => [
                ['name' => '500 جم',  'buy' => 75,  'sell' => 116, 'stock' => 95],
                ['name' => '1 كجم',   'buy' => 140, 'sell' => 216, 'stock' => 45],
                ['name' => '5 كجم',   'buy' => 650, 'sell' => 980, 'stock' => 15],
            ]],
            ['cat' => 'مطهرات تربة', 'name' => 'تريكودرما', 'variants' => [
                ['name' => '100 جم',  'buy' => 40,  'sell' => 62,  'stock' => 170],
                ['name' => '500 جم',  'buy' => 170, 'sell' => 265, 'stock' => 70],
                ['name' => '1 كجم',   'buy' => 320, 'sell' => 495, 'stock' => 30],
            ]],

            // ── معدات ورشاشات ─────────────────────────────────────
            ['cat' => 'معدات ورشاشات', 'name' => 'رشاشة ظهرية يدوية', 'variants' => [
                ['name' => '16 لتر',  'buy' => 180, 'sell' => 280, 'stock' => 25],
                ['name' => '20 لتر',  'buy' => 220, 'sell' => 340, 'stock' => 18],
            ]],
            ['cat' => 'معدات ورشاشات', 'name' => 'رشاشة كهربائية', 'variants' => [
                ['name' => '16 لتر',  'buy' => 480, 'sell' => 720, 'stock' => 10],
                ['name' => '20 لتر',  'buy' => 580, 'sell' => 870, 'stock' => 8],
            ]],
            ['cat' => 'معدات ورشاشات', 'name' => 'قفازات مطاطية', 'variants' => [
                ['name' => 'مقاس S',  'buy' => 12,  'sell' => 20,  'stock' => 100],
                ['name' => 'مقاس M',  'buy' => 12,  'sell' => 20,  'stock' => 150],
                ['name' => 'مقاس L',  'buy' => 12,  'sell' => 20,  'stock' => 120],
            ]],
            ['cat' => 'معدات ورشاشات', 'name' => 'كمامة واقية', 'variants' => [
                ['name' => 'قطعة',    'buy' => 25,  'sell' => 40,  'stock' => 80],
                ['name' => 'علبة 10', 'buy' => 220, 'sell' => 340, 'stock' => 20],
            ]],
        ];

        foreach ($products as $productData) {
            $category = $categories[$productData['cat']];

            $product = Product::create([
                'store_id'    => $this->storeId,
                'category_id' => $category->id,
                'name'        => $productData['name'],
            ]);

            foreach ($productData['variants'] as $variantData) {
                $variant = ProductVariant::create([
                    'store_id'            => $this->storeId,
                    'product_id'          => $product->id,
                    'name'                => $variantData['name'],
                    'purchase_price'      => $variantData['buy'],
                    'sale_price'          => $variantData['sell'],
                    'low_stock_threshold' => 10,
                    'is_active'           => true,
                ]);

                // Initial stock movement:
                if ($variantData['stock'] > 0) {
                    StockMovement::create([
                        'store_id'       => $this->storeId,
                        'product_id'     => $product->id,
                        'variant_id'     => $variant->id,
                        'type'           => 'in',
                        'quantity'       => $variantData['stock'],
                        'reference_type' => 'initial_stock',
                        'reference_id'   => $variant->id,
                        'notes'          => 'Initial stock seed',
                        'created_by'     => $this->createdByUserId,
                    ]);
                }
            }
        }
    }
}