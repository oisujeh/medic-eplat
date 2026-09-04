<?php

namespace App\Http\Resources;

use App\Models\Encounter;
use App\Models\Problem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A past encounter as it appears on a timeline: who, when, what was found
 * and what was planned. Expects `author`, `servicePoint` and
 * `codedDiagnoses` to be loaded; `addenda` is included when loaded.
 *
 * @mixin Encounter
 */
class EncounterSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $at = $this->signed_at ?? $this->started_at ?? $this->created_at;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'service_point' => $this->servicePoint?->name,
            'author' => $this->author?->name,
            'date' => $at?->isoFormat('D MMM YYYY'),
            'date_label' => $at?->isoFormat('D MMM YYYY, h:mm a'),
            'ago' => $at?->diffForHumans(short: true),
            'presenting_complaint' => $this->presenting_complaint,
            'subjective' => $this->subjective,
            'objective' => $this->objective,
            'assessment' => $this->assessment,
            'plan' => $this->plan,
            'diagnoses' => $this->codedDiagnoses
                ->map(fn (Problem $p) => trim(($p->code ? $p->code.' ' : '').$p->name))
                ->values(),
            'outcome' => $this->outcome?->label(),
            'addenda' => EncounterAddendumResource::collection($this->whenLoaded('addenda')),
            'url' => route('encounters.show', $this->id),
        ];
    }
}
