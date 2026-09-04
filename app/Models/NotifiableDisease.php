<?php

namespace App\Models;

use App\Enums\NotifiableDiseaseCategory;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A priority disease or event under Nigeria's IDSR and the rules that apply
 * to it. Cases snapshot these rules when opened, so editing or deactivating
 * an entry only affects detection from then on.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property NotifiableDiseaseCategory $category
 * @property string $detection
 * @property array<int, string> $icd_prefixes
 * @property string|null $case_definition
 * @property int|null $notification_hours
 * @property bool $requires_contact_tracing
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable([
    'name', 'slug', 'category', 'detection', 'icd_prefixes', 'case_definition',
    'notification_hours', 'requires_contact_tracing', 'sort_order', 'is_active',
])]
class NotifiableDisease extends Model implements AuditableRecord
{
    use Auditable;

    /** Opened automatically when a diagnosis carries one of the ICD-10 prefixes. */
    public const DETECTION_DIAGNOSIS = 'diagnosis';

    /** Opened by another module (maternity, immunization) through the surveillance service. */
    public const DETECTION_EVENT = 'event';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => NotifiableDiseaseCategory::class,
            'icd_prefixes' => 'array',
            'notification_hours' => 'integer',
            'requires_contact_tracing' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<NotifiableDisease>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Entries that open cases from coded diagnoses.
     *
     * @param  Builder<NotifiableDisease>  $query
     */
    #[Scope]
    protected function detectedByDiagnosis(Builder $query): void
    {
        $query->where('detection', self::DETECTION_DIAGNOSIS);
    }

    /**
     * @return HasMany<SurveillanceCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(SurveillanceCase::class);
    }

    /**
     * Whether a normalised ICD-10 code ("A00", "A00.0", "B50.8") identifies
     * this disease. A three-character prefix matches the whole category; a
     * dotted prefix matches that subcategory and anything beneath it.
     */
    public function matches(string $code): bool
    {
        $code = IcdCode::normalise($code);

        foreach ($this->icd_prefixes as $prefix) {
            $prefix = IcdCode::normalise($prefix);

            if ($code === $prefix || str_starts_with($code, str_contains($prefix, '.') ? $prefix : $prefix.'.')) {
                return true;
            }
        }

        return false;
    }
}
