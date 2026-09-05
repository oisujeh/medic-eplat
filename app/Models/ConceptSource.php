<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A coding system concepts are mapped to: LOINC, ICD-10, the national
 * essential medicines list, or the facility's own local codes.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $version
 * @property string|null $url
 */
#[Fillable(['code', 'name', 'version', 'url'])]
class ConceptSource extends Model implements AuditableRecord
{
    use Auditable;

    public const LOCAL = 'LOCAL';

    public const ICD10 = 'ICD10';

    public const LOINC = 'LOINC';

    public const NEML = 'NEML';

    /**
     * @return HasMany<ConceptMapping, $this>
     */
    public function mappings(): HasMany
    {
        return $this->hasMany(ConceptMapping::class);
    }

    public function auditLabel(): string
    {
        return "Concept source {$this->code}";
    }
}
