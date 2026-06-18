<?php
// bootstrap and run a quick test for updateDirectPayment
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    /** @var \App\Services\PaymentService $svc */
    $svc = app()->make(\App\Services\PaymentService::class);
    $svc->updateDirectPayment(2, 90, ['amount' => 2100.00, 'description' => 'تعديل اختبار عبر سكربت', 'transaction_date' => '2026-06-11']);
    echo "updateDirectPayment: OK\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

$pdo = Illuminate\Support\Facades\DB::getPdo();
$stm = $pdo->prepare('select id,store_id,party_type,party_id,type,amount,reference_type,reference_id,description,created_at from financial_transactions where id=?');
$stm->execute([90]);
print_r($stm->fetchAll(PDO::FETCH_ASSOC));
$stm = $pdo->prepare('select id,store_id,type,amount,reference_type,reference_id,transaction_date,description from cash_transactions where reference_id=? order by id desc limit 5');
$stm->execute([26]);
print_r($stm->fetchAll(PDO::FETCH_ASSOC));
$stm = $pdo->prepare('select id,invoice_number,paid_amount,remaining_amount from sales_invoices where id=?');
$stm->execute([26]);
print_r($stm->fetchAll(PDO::FETCH_ASSOC));

echo "done\n";
