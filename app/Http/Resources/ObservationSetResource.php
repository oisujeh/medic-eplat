<?php

namespace App\Http\Resources;

use App\Models\Observation;
use App\Models\ObservationSet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A set of readings ready for display: values keyed by code for scoring and
 * trends, plus a display list with each reading's label, unit and flag.
 * Expects `observations` and `recordedBy` to be loaded.
 *
 * @mixin ObservationSet
 */
class ObservationSetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'values' => (object) $this->values(),
            'blood_pressure' => $this->bloodPressure(),
            'readings' => $this->observations->map(fn (Observation $o) => [
                'code' => $o->code->value,
                'label' => $o->code->label(),
                'short_label' => $o->code->shortLabel(),
                'panel' => $o->code->panel()->value,
                'value' => $o->reading(),
                'unit' => $o->unit,
                'display' => $o->display(),
                'level' => $o->level?->value ?? 'normal',
                'flag' => $o->flag,
            ])->values(),
            'notes' => $this->notes,
            'recorded_by' => $this->recordedBy?->name,
            'recorded_at' => $this->recorded_at->toIso8601String(),
            'recorded_at_label' => $this->recorded_at->isoFormat('D MMM YYYY, h:mm a'),
            'recorded_at_short' => $this->recorded_at->isoFormat('D MMM, HH:mm'),
            'recorded_at_diff' => $this->recorded_at->diffForHumans(),
            'alert_level' => $this->alert_level->value,
            'flags' => $this->flags(),
        ];
    }
}
