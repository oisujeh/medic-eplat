<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one coding system calls a concept.
 *
 * @property int $id
 * @property int $concept_id
 * @property int $concept_source_id
 * @property string $code
 */
#[Fillable(['concept_id', 'concept_source_id', 'code'])]
class ConceptMapping extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * @return BelongsTo<Concept, $this>
     */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    /**
     * @return BelongsTo<ConceptSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ConceptSource::class, 'concept_source_id');
    }

    public function auditLabel(): string
    {
        return "Mapping {$this->code} for concept #{$this->concept_id}";
    }
}
