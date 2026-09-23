<?php

namespace App\Providers;

use App\Events\ProjectCreated;
use App\Listeners\SendProjectCreatedNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(
            ProjectCreated::class,
            SendProjectCreatedNotification::class,
        );
    }
}