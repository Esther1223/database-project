<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'approver_id',
        'decision',
        'decision_time',
    ];

    public const DECISION_APPROVED = 'approved';

    public const DECISION_REJECTED = 'rejected';

    protected $casts = [
        'decision_time' => 'datetime',
    ];

    /**
     * Convenience list of allowed decision values.
     *
     * @return string[]
     */
    public static function allowedDecisions(): array
    {
        return [self::DECISION_APPROVED, self::DECISION_REJECTED];
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function isApproved(): bool
    {
        return $this->decision === self::DECISION_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->decision === self::DECISION_REJECTED;
    }
}
