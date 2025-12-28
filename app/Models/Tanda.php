<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factory\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tanda extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'amount',
        'frequency',
        'participants_count',
        'start_date',
        'delivery_date',
        'payment_mode',
    ];

    protected $casts = [
        'start_date' => 'date',
        'delivery_date' => 'date',
    ];

    public float $amount {
        set => max(0, $value);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TandaParticipant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->with('participant.user');
    }

    public function calculatePot(): float
    {
        return (float) $this->payments->sum('amount_snapshot');
    }
}
