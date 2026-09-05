<?php

namespace App\Events;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A duplicate record was folded into the surviving one.
 */
class PatientsMerged extends DomainEvent
{
    /**
     * @param  array<string, int>  $moved  Rows moved per table.
     */
    public function __construct(
        public readonly Patient $survivor,
        public readonly Patient $duplicate,
        public readonly array $moved,
        ?User $actor = null,
    ) {
        parent::__construct($actor);
    }

    public function subject(): Model
    {
        return $this->survivor;
    }

    public function payload(): array
    {
        return [
            'survivor_file_number' => $this->survivor->file_number,
            'duplicate_id' => $this->duplicate->id,
            'duplicate_file_number' => $this->duplicate->file_number,
            'moved' => $this->moved,
        ];
    }
}
