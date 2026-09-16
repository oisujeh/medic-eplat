<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\AdmissionNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A ward-round, progress or nursing note written during an admission.
 *
 * @property int $id
 * @property int $admission_id
 * @property int $patient_id
 * @property int|null $author_id
 * @property string $type
 * @property string $note
 * @property Carbon $recorded_at
 */
#[Fillable(['admission_id', 'patient_id', 'author_id', 'type', 'note', 'recorded_at'])]
class AdmissionNote extends Model implements AuditableRecord
{
    /** @use HasFactory<AdmissionNoteFactory> */
    use Auditable, HasFactory;

    public const TYPE_WARD_ROUND = 'ward_round';

    public const TYPE_PROGRESS = 'progress';

    public const TYPE_NURSING = 'nursing';

    /** @var array<string, string> */
    public const TYPES = [
        self::TYPE_WARD_ROUND => 'Ward round',
        self::TYPE_PROGRESS => 'Progress note',
        self::TYPE_NURSING => 'Nursing note',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Admission, $this>
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Human-readable note type.
     */
    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
