<?php

namespace App\Models;

use App\Enums\ConceptClass;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One thing the system has a name for: a drug, a vaccine, an allergen, an
 * observation, a laboratory test. Records point here; the mappings say
 * what each coding system calls it.
 *
 * @property int $id
 * @property string $ulid
 * @property ConceptClass $class
 * @property string $name
 * @property string|null $description
 * @property string|null $datatype
 * @property string|null $unit
 * @property bool $is_active
 * @property Carbon|null $retired_at
 */
#[Fillable(['class', 'name', 'description', 'datatype', 'unit', 'is_active', 'retired_at'])]
class Concept extends Model implements AuditableRecord
{
    use Auditable, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'class' => ConceptClass::class,
            'is_active' => 'boolean',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Concept>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->whereNull('retired_at');
    }

    /**
     * @param  Builder<Concept>  $query
     */
    #[Scope]
    protected function ofClass(Builder $query, ConceptClass $class): void
    {
        $query->where('class', $class->value);
    }

    /**
     * @return HasMany<ConceptMapping, $this>
     */
    public function mappings(): HasMany
    {
        return $this->hasMany(ConceptMapping::class);
    }

    /**
     * @return HasMany<ConceptSynonym, $this>
     */
    public function synonyms(): HasMany
    {
        return $this->hasMany(ConceptSynonym::class);
    }

    /**
     * The concept's code in a coding system, when it has one.
     */
    public function codeIn(string $sourceCode): ?string
    {
        $mapping = $this->mappings->first(fn (ConceptMapping $m) => $m->source->code === $sourceCode);

        return $mapping?->code;
    }

    public function auditLabel(): string
    {
        return "Concept {$this->name} ({$this->class->value})";
    }
}
