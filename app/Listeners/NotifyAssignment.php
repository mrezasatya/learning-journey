<?php

namespace App\Listeners;

use App\Events\AssignmentCreated;
use App\Services\ReminderService;

class NotifyAssignment
{
    public function __construct(private ReminderService $reminders) {}

    public function handle(AssignmentCreated $event): void
    {
        if (! config('blj.mute_notifications')) {
            $this->reminders->assigned($event->assignment);
        }
    }
}
