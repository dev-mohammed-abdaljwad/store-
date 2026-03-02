 <?php
namespace App\Domain\Store\Repositories;

use App\Domain\Store\Interfaces\ISalesInvoiceRepository;
use App\Models\SalesInvoice;

class SalesInvoiceRepository implements ISalesInvoiceRepository
{
    public function findById(string $id, string $storeId): ?SalesInvoice
    {
        return SalesInvoice::where('id', $id)->where('store_id', $storeId)->first();
    }

    public function findByStore(string $storeId, array $filters = []): array
    {
        $query = SalesInvoice::where('store_id', $storeId);
        foreach ($filters as $key => $value) {
            $query->where($key, $value);
        }
        return $query->get()->all();
    }

    public function findByCustomer(string $customerId, string $storeId): array
    {
        return SalesInvoice::where('customer_id', $customerId)->where('store_id', $storeId)->get()->all();
    }

    public function save(SalesInvoice $invoice): SalesInvoice
    {
        $invoice->save();
        return $invoice;
    }

    public function update(string $id, SalesInvoice $invoice): SalesInvoice
    {
        $existing = $this->findById($id, $invoice->store_id);
        if ($existing) {
            $existing->fill($invoice->toArray());
            $existing->save();
        }
        return $existing;
    }

    public function generateInvoiceNumber(string $storeId): string
    {
        $last = SalesInvoice::where('store_id', $storeId)->orderByDesc('id')->first();
        return $last ? (string)($last->id + 1) : '1';
    }
}
