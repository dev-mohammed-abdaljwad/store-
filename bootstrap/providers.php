<?php

$isLocalEnvironment = in_array(env('APP_ENV'), ['local', 'development'], true);

return array_values(array_filter([
    App\Providers\AppServiceProvider::class,
    App\Providers\RepositoryServiceProvider::class,
    $isLocalEnvironment ? App\Providers\TelescopeServiceProvider::class : null,
]));
