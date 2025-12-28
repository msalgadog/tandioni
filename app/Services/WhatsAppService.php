<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $instanceId,
        private readonly string $token,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            config('services.evolution.base_url', ''),
            config('services.evolution.instance_id', ''),
            config('services.evolution.token', ''),
        );
    }

    public function sendText(string $phone, string $message): array
    {
        return Http::withToken($this->token)
            ->baseUrl($this->baseUrl)
            ->post('/message/sendText', [
                'instanceId' => $this->instanceId,
                'phone' => $phone,
                'message' => $message,
            ])
            ->throw()
            ->json();
    }

    public function sendMedia(string $phone, string $mediaUrl, string $caption = ''): array
    {
        return Http::withToken($this->token)
            ->baseUrl($this->baseUrl)
            ->post('/message/sendMedia', [
                'instanceId' => $this->instanceId,
                'phone' => $phone,
                'mediaUrl' => $mediaUrl,
                'caption' => $caption,
            ])
            ->throw()
            ->json();
    }
}
