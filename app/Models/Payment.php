<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factory\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tanda_id',
        'participant_id',
        'recipient_user_id',
        'due_date',
        'amount_snapshot',
        'status',
        'receipt_path',
        'validated_at',
        'rejected_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount_snapshot' => 'decimal:2',
        'status' => PaymentStatus::class,
        'validated_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public float $amount_snapshot {
        set => max(0, $value);
    }

    public function tanda(): BelongsTo
    {
        return $this->belongsTo(Tanda::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(TandaParticipant::class, 'participant_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
