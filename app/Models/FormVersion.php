<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use App\Support\Forms\FormDefinition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One published version of a form: the definition as it stood, frozen.
 * Submissions point here, so a form can change without changing how what
 * was filled under an earlier version reads (PRD FRM-06).
 *
 * @property int $id
 * @property int $form_id
 * @property int $version
 * @property array<string, mixed> $definition
 * @property string|null $notes
 * @property int|null $published_by
 * @property Carbon $published_at
 */
#[Fillable(['form_id', 'version', 'definition', 'notes', 'published_by', 'published_at'])]
class FormVersion extends Model implements AuditableRecord
{
    use Auditable;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'definition' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('A published form version is immutable; publish a new version instead.');
        });

        static::deleting(function (): never {
            throw new LogicException('Published form versions cannot be deleted; submissions refer to them.');
        });
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function parsed(): FormDefinition
    {
        return FormDefinition::fromArray($this->definition);
    }

    public function auditLabel(): string
    {
        return "Form #{$this->form_id} v{$this->version}";
    }
}
