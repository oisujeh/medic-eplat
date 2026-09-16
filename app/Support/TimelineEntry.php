<?php

namespace App\Support;

use App\Enums\TimelineKind;
use Carbon\CarbonInterface;

/**
 * One event on a patient's timeline, ready for the client.
 */
final class TimelineEntry
{
    public function __construct(
        public readonly TimelineKind $kind,
        public readonly CarbonInterface $at,
        public readonly string $title,
        public readonly ?string $detail = null,
        public readonly ?string $badge = null,
        public readonly string $tone = 'muted',
        public readonly ?string $href = null,
        public readonly ?string $by = null,
        public readonly ?string $key = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key ?? $this->kind->value.':'.$this->at->getTimestamp(),
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'icon' => $this->kind->icon(),
            'at_iso' => $this->at->toIso8601String(),
            'date' => $this->at->toDateString(),
            'date_label' => $this->at->isoFormat('dddd, D MMMM YYYY'),
            'time' => $this->at->isoFormat('HH:mm'),
            'title' => $this->title,
            'detail' => $this->detail,
            'badge' => $this->badge,
            'tone' => $this->tone,
            'href' => $this->href,
            'by' => $this->by,
        ];
    }
}
