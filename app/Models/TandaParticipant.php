<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factory\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TandaParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'tanda_id',
        'user_id',
        'position',
        'is_winner',
    ];

    protected $casts = [
        'is_winner' => 'boolean',
    ];

    public function tanda(): BelongsTo
    {
        return $this->belongsTo(Tanda::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'participant_id');
    }
}
