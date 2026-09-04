<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A note appended to a signed encounter. The original narrative is never
 * changed; an addendum records what was added, by whom and when.
 *
 * @property int $id
 * @property int $encounter_id
 * @property int|null $author_id
 * @property string $body
 * @property Carbon $recorded_at
 */
#[Fillable(['encounter_id', 'author_id', 'body', 'recorded_at'])]
class EncounterAddendum extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * Eloquent would pluralise this to "addendums".
     *
     * @var string
     */
    protected $table = 'encounter_addenda';

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
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The patient is reached through the parent encounter.
     */
    public function auditPatientId(): ?int
    {
        return $this->encounter?->patient_id;
    }
}
