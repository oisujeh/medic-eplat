<?php

namespace App\Models;

use App\Enums\EncounterType;
use App\Enums\FormEntryMode;
use App\Enums\FormStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Contracts\AuditableRecord;
use App\Support\Forms\FormDefinition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A clinical data-capture template: its working definition, where it is
 * offered, and the versions that have been published of it.
 *
 * @property int $id
 * @property string $ulid
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property FormStatus $status
 * @property FormEntryMode $entry_mode
 * @property list<string> $encounter_types
 * @property list<string> $roles
 * @property array<string, mixed> $definition
 * @property int|null $published_version
 * @property int|null $created_by
 */
#[Fillable([
    'slug', 'name', 'description', 'status', 'entry_mode', 'encounter_types', 'roles',
    'definition', 'published_version', 'created_by',
])]
class Form extends Model implements AuditableRecord
{
    use Auditable, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FormStatus::class,
            'entry_mode' => FormEntryMode::class,
            'encounter_types' => 'array',
            'roles' => 'array',
            'definition' => 'array',
            'published_version' => 'integer',
        ];
    }

    /**
     * @param  Builder<Form>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', FormStatus::Published->value);
    }

    /**
     * Forms assigned to an encounter type.
     *
     * @param  Builder<Form>  $query
     */
    #[Scope]
    protected function forEncounterType(Builder $query, EncounterType $type): void
    {
        $query->whereJsonContains('encounter_types', $type->value);
    }

    /**
     * @return HasMany<FormVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class)->orderByDesc('version');
    }

    /**
     * The version clinicians fill today.
     *
     * @return HasOne<FormVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(FormVersion::class)->ofMany('version', 'max');
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The working draft, parsed.
     */
    public function draftDefinition(): FormDefinition
    {
        return FormDefinition::fromArray($this->definition ?? []);
    }

    /**
     * Whether the working draft differs from what was last published.
     */
    public function hasUnpublishedChanges(): bool
    {
        $current = $this->currentVersion;

        return $current === null || $current->definition !== $this->definition;
    }

    /**
     * Whether the form may be filled by a member of staff: it is offered to
     * everyone who can document the encounter unless roles are named.
     */
    public function canBeFilledBy(User $user): bool
    {
        if ($this->roles === [] || $user->hasFullModuleAccess()) {
            return true;
        }

        return $user->roles->pluck('slug')->intersect($this->roles)->isNotEmpty();
    }

    public function auditLabel(): string
    {
        return "Form {$this->name}";
    }
}
