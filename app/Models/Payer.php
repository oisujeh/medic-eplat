<?php

namespace App\Models;

use App\Enums\PayerType;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\PayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A third-party payer (NHIA, an HMO or a corporate scheme) and the tariff
 * rules applied to its enrollees' bills.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property PayerType $type
 * @property float $discount_percent
 * @property float $drug_copay_percent
 * @property string|null $contact_person
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $notes
 * @property bool $is_active
 */
#[Fillable([
    'name', 'code', 'type', 'discount_percent', 'drug_copay_percent',
    'contact_person', 'phone', 'email', 'address', 'notes', 'is_active',
])]
class Payer extends Model implements AuditableRecord
{
    /** @use HasFactory<PayerFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PayerType::class,
            'discount_percent' => 'float',
            'drug_copay_percent' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Patient, $this>
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    /**
     * @return HasMany<Claim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    /**
     * @return HasMany<ClaimBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(ClaimBatch::class);
    }

    /**
     * Scope to payers whose enrollees can be claimed for, in display order.
     *
     * @param  Builder<Payer>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('name');
    }
}
