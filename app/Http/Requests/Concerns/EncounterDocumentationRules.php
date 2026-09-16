<?php

namespace App\Http\Requests\Concerns;

use App\Enums\EncounterOutcome;
use App\Enums\EncounterType;
use Illuminate\Validation\Rule;

/**
 * Validation rules for encounter documentation, shared by the save-draft and
 * sign requests. The SOAP narrative columns are common to every type; the
 * `structured` payload is validated per encounter type.
 */
trait EncounterDocumentationRules
{
    /** Permitted follow-up intervals. */
    public const FOLLOW_UP_INTERVALS = ['1w', '2w', '1m', '3m', '6m'];

    /** Permitted general-appearance values. */
    public const APPEARANCES = ['well', 'ill', 'distressed'];

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function documentationRules(EncounterType $type): array
    {
        return [
            'presenting_complaint' => ['nullable', 'string', 'max:5000'],
            'subjective' => ['nullable', 'string', 'max:10000'],
            'objective' => ['nullable', 'string', 'max:10000'],
            'assessment' => ['nullable', 'string', 'max:5000'],
            'plan' => ['nullable', 'string', 'max:10000'],
            'outcome' => ['nullable', Rule::enum(EncounterOutcome::class)],
            'follow_up_at' => ['nullable', 'date'],
            'structured' => ['nullable', 'array'],
            ...($type->isConsultation() ? $this->consultationStructuredRules() : $this->nursingStructuredRules()),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function consultationStructuredRules(): array
    {
        return [
            'structured.subjective' => ['nullable', 'array'],
            'structured.subjective.past_medical_history' => ['nullable', 'string', 'max:5000'],
            'structured.subjective.family_history' => ['nullable', 'string', 'max:5000'],
            'structured.subjective.social_history' => ['nullable', 'string', 'max:5000'],
            'structured.subjective.medication_history' => ['nullable', 'string', 'max:5000'],
            'structured.subjective.allergy_history' => ['nullable', 'string', 'max:5000'],
            'structured.subjective.review_of_systems' => ['nullable', 'string', 'max:5000'],

            'structured.examination' => ['nullable', 'array'],
            'structured.examination.general' => ['nullable', 'array'],
            'structured.examination.general.appearance' => ['nullable', Rule::in(self::APPEARANCES)],
            'structured.examination.general.consciousness' => ['nullable', 'string', 'max:255'],
            'structured.examination.general.hydration' => ['nullable', 'string', 'max:255'],
            'structured.examination.general.pallor' => ['nullable', 'boolean'],
            'structured.examination.general.jaundice' => ['nullable', 'boolean'],
            'structured.examination.general.cyanosis' => ['nullable', 'boolean'],
            'structured.examination.general.edema' => ['nullable', 'boolean'],
            'structured.examination.systems' => ['nullable', 'array'],
            'structured.examination.systems.*' => ['nullable', 'string', 'max:5000'],

            'structured.plan' => ['nullable', 'array'],
            'structured.plan.procedures' => ['nullable', 'array'],
            'structured.plan.procedures.*' => ['string', 'max:255'],
            'structured.plan.imaging' => ['nullable', 'array'],
            'structured.plan.imaging.*' => ['string', 'max:255'],
            'structured.plan.referrals' => ['nullable', 'array'],
            'structured.plan.referrals.*' => ['string', 'max:255'],
            'structured.plan.counseling' => ['nullable', 'array'],
            'structured.plan.counseling.*' => ['string', 'max:255'],

            'structured.follow_up' => ['nullable', 'array'],
            'structured.follow_up.interval' => ['nullable', Rule::in(self::FOLLOW_UP_INTERVALS)],
            'structured.follow_up.monitoring_goals' => ['nullable', 'array'],
            'structured.follow_up.monitoring_goals.*' => ['string', 'max:255'],
            'structured.follow_up.patient_instructions' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Service-specific fields captured at nursing points. Antenatal
     * measurements are observations, not structured text.
     *
     * @return array<string, array<int, mixed>>
     */
    private function nursingStructuredRules(): array
    {
        return [
            'structured.family_planning' => ['nullable', 'array'],
            'structured.family_planning.method' => ['nullable', 'string', 'max:100'],
            'structured.family_planning.counseling' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
