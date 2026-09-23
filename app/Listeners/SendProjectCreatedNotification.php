<?php

namespace App\Listeners;

use App\Events\ProjectCreated;
use App\Jobs\NotifyProjectCreated;

class SendProjectCreatedNotification
{
    public function handle(ProjectCreated $event): void
    {
        NotifyProjectCreated::dispatch($event->project->id);
    }
}