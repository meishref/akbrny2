<?php

namespace App\Jobs;

use App\Services\FirebaseCloudMessaging;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendFcmNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public string $token,
        public string $title,
        public string $body,
        public string $icon = 'img/icon.png',
        public string $image = 'img/d.png',
    ) {}

    public function handle(FirebaseCloudMessaging $fcm): void
    {
        $fcm->sendDataNotification(
            $this->token,
            $this->title,
            $this->body,
            $this->icon,
            $this->image,
        );
    }
}
