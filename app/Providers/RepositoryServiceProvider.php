<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Domain\Store\Interfaces\ICashRepository;
use App\Domain\Store\Repositories\CashRepository;
use App\Domain\Store\Interfaces\ICustomerRepository;
use App\Domain\Store\Repositories\CustomerRepository;
use App\Domain\Store\Interfaces\IFinancialRepository;
use App\Domain\Store\Repositories\FinancialRepository;
use App\Domain\Store\Interfaces\IProductRepository;
use App\Domain\Store\Repositories\ProductRepository;
use App\Domain\Store\Interfaces\IPurchaseInvoiceRepository;
use App\Domain\Store\Repositories\PurchaseInvoiceRepository;
use App\Domain\Store\Interfaces\ISalesInvoiceRepository;
use App\Domain\Store\Repositories\SalesInvoiceRepository;
use App\Domain\Store\Interfaces\IStockRepository;
use App\Domain\Store\Repositories\StockRepository;
use App\Domain\Store\Interfaces\IStoreRepository;
use App\Domain\Store\Repositories\StoreRepository;
use App\Domain\Store\Interfaces\ISupplierRepository;
use App\Domain\Store\Repositories\SupplierRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ICashRepository::class, CashRepository::class);
        $this->app->bind(ICustomerRepository::class, CustomerRepository::class);
        $this->app->bind(IFinancialRepository::class, FinancialRepository::class);
        $this->app->bind(IProductRepository::class, ProductRepository::class);
        $this->app->bind(IPurchaseInvoiceRepository::class, PurchaseInvoiceRepository::class);
        $this->app->bind(ISalesInvoiceRepository::class, SalesInvoiceRepository::class);
        $this->app->bind(IStockRepository::class, StockRepository::class);
        $this->app->bind(IStoreRepository::class, StoreRepository::class);
        $this->app->bind(ISupplierRepository::class, SupplierRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
