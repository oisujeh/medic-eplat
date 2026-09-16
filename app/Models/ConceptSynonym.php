<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Another name a concept is known by.
 *
 * @property int $id
 * @property int $concept_id
 * @property string $name
 */
#[Fillable(['concept_id', 'name'])]
class ConceptSynonym extends Model
{
    /**
     * @return BelongsTo<Concept, $this>
     */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }
}
