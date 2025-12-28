<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factory\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'second_last_name',
        'phone',
        'email',
        'profile_photo_path',
        'postal_code',
        'state',
        'municipality',
        'colony',
        'street',
        'external_number',
        'internal_number',
        'phone_home',
        'phone_office',
        'role',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public string $phone {
        set => $this->sanitizeE164($value);
    }

    public ?string $phone_home {
        set => $this->sanitizeOptionalPhone($value);
    }

    public ?string $phone_office {
        set => $this->sanitizeOptionalPhone($value);
    }

    protected function sanitizeE164(string $value): string
    {
        $trimmed = preg_replace('/\s+/', '', $value) ?? '';

        if (!str_starts_with($trimmed, '+')) {
            $trimmed = '+'.ltrim($trimmed, '+');
        }

        return $trimmed;
    }

    protected function sanitizeOptionalPhone(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->sanitizeE164($value);
    }
}
