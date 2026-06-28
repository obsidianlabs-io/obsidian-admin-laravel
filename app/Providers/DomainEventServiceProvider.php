<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Auth\Events\UserLoggedInEvent;
use App\Domains\Auth\Events\UserLoggedOutEvent;
use App\Domains\Shared\Events\DomainAuditEvent;
use App\Domains\System\Events\AuditPolicyUpdatedEvent;
use App\Domains\System\Listeners\RecordAuditEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DomainEventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(UserLoggedInEvent::class, RecordAuditEvent::class);
        Event::listen(UserLoggedOutEvent::class, RecordAuditEvent::class);
        Event::listen(AuditPolicyUpdatedEvent::class, RecordAuditEvent::class);
        Event::listen(DomainAuditEvent::class, RecordAuditEvent::class);
    }
}
