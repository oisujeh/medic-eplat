<?php

namespace App\Models;

use App\Enums\MatchSignal;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The configured weight of one deduplication signal.
 *
 * @property int $id
 * @property MatchSignal $signal
 * @property int $weight
 * @property bool $is_active
 */
#[Fillable(['signal', 'weight', 'is_active'])]
class MatchingRule extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signal' => MatchSignal::class,
            'weight' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function auditLabel(): string
    {
        return 'Matching rule '.$this->signal->value;
    }
}
