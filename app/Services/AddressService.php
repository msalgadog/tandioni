<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AddressService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            config('services.copomex.base_url', 'https://api.copomex.com/query'),
            config('services.copomex.token', ''),
        );
    }

    /**
     * @return array{state: string, municipality: string, colonies: array<int, string>}
     */
    public function lookupByPostalCode(string $postalCode): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->get("/info_cp/{$postalCode}", [
                'token' => $this->token,
            ])
            ->throw()
            ->json('response');

        $colonies = array_values(array_unique($response['asentamiento'] ?? []));

        return [
            'state' => $response['estado'] ?? '',
            'municipality' => $response['municipio'] ?? '',
            'colonies' => $colonies,
        ];
    }
}
