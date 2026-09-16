<?php

namespace App\Models;

use App\Enums\ClaimBatchStatus;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A claims schedule: the claims submitted to one payer for one month.
 *
 * @property int $id
 * @property string $batch_number
 * @property int $payer_id
 * @property string $period
 * @property ClaimBatchStatus $status
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property string|null $reference
 * @property string|null $notes
 */
#[Fillable(['batch_number', 'payer_id', 'period', 'status', 'submitted_by', 'submitted_at', 'reference', 'notes'])]
class ClaimBatch extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClaimBatchStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payer, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    /**
     * @return HasMany<Claim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class)->orderBy('service_date')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * The period as a month name, e.g. "September 2026".
     */
    public function periodLabel(): string
    {
        return Carbon::createFromFormat('Y-m', $this->period)?->isoFormat('MMMM YYYY') ?? $this->period;
    }
}
