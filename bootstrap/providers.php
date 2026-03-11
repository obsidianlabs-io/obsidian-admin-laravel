<?php

declare(strict_types=1);
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\DomainEventServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    DomainEventServiceProvider::class,
    HorizonServiceProvider::class,
];
