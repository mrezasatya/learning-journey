<?php

namespace App\Listeners;

use App\Events\PostTestExhausted;
use App\Services\ReminderService;

class NotifyRelearn
{
    public function __construct(private ReminderService $reminders) {}

    public function handle(PostTestExhausted $event): void
    {
        $this->reminders->relearn($event->assignment->load('employee.supervisor.user', 'program'));
    }
}
