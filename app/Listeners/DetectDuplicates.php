<?php

namespace App\Listeners;

use App\Events\PatientRegistered;
use App\Events\PatientUpdated;
use App\Services\DuplicateReview;

/**
 * Keeps the duplicate review queue current: every registration and every
 * edit of a patient's identifying details is scored against the register.
 */
class DetectDuplicates
{
    /**
     * The attributes whose change can alter a match.
     *
     * @var list<string>
     */
    private const IDENTIFYING = [
        'surname', 'first_name', 'other_names', 'date_of_birth', 'sex', 'nin', 'phone', 'lga',
        'next_of_kin_name', 'next_of_kin_relationship', 'mother_patient_id',
    ];

    public function __construct(private readonly DuplicateReview $review) {}

    public function handleRegistered(PatientRegistered $event): void
    {
        $this->review->refresh($event->patient);
    }

    public function handleUpdated(PatientUpdated $event): void
    {
        if (array_intersect($event->changed, self::IDENTIFYING) !== []) {
            $this->review->refresh($event->patient);
        }
    }
}
