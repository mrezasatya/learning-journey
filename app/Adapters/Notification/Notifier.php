<?php

namespace App\Adapters\Notification;

use App\Models\User;

/**
 * Titik sambung notifikasi (Brief 9.3). Fase 1: in-app + email.
 * Fase berikutnya: tambahkan WhatsAppNotifier (butuh WA gateway resmi) tanpa mengubah pemanggil.
 */
interface Notifier
{
    /** @param array<string> $channels IN_APP, EMAIL */
    public function send(User $user, string $type, string $title, string $body, ?string $url = null, array $channels = ['IN_APP', 'EMAIL']): void;
}
