<?php

namespace App\Http\Requests;

use App\Enums\BirthOutcome;
use App\Enums\DeliveryMode;
use App\Enums\MaternalOutcome;
use App\Models\Birth;
use App\Models\Delivery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordDeliveryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Authorization is handled by the `module:maternity` middleware.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'delivered_at' => ['required', 'date', 'before_or_equal:now'],
            'mode' => ['required', Rule::enum(DeliveryMode::class)],
            'labour_onset' => ['nullable', Rule::in(array_keys(Delivery::LABOUR_ONSETS))],
            'gestational_age_weeks' => ['nullable', 'integer', 'min:20', 'max:45'],
            'place' => ['nullable', Rule::in(array_keys(Delivery::PLACES))],
            'attendant_id' => ['nullable', Rule::exists('users', 'id')],
            'complications' => ['nullable', 'array'],
            'complications.*' => ['string', Rule::in(Delivery::COMPLICATIONS)],
            'blood_loss_ml' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'maternal_outcome' => ['required', Rule::enum(MaternalOutcome::class)],
            'notes' => ['nullable', 'string', 'max:2000'],

            'births' => ['required', 'array', 'min:1', 'max:5'],
            'births.*.outcome' => ['required', Rule::enum(BirthOutcome::class)],
            'births.*.sex' => ['required', Rule::in(['M', 'F'])],
            'births.*.weight_grams' => ['nullable', 'integer', 'min:300', 'max:7000'],
            'births.*.apgar_1' => ['nullable', 'integer', 'min:0', 'max:10'],
            'births.*.apgar_5' => ['nullable', 'integer', 'min:0', 'max:10'],
            'births.*.resuscitated' => ['nullable', 'boolean'],
            'births.*.breastfed_within_hour' => ['nullable', 'boolean'],
            'births.*.bcg_given' => ['nullable', 'boolean'],
            'births.*.opv0_given' => ['nullable', 'boolean'],
            'births.*.hepb0_given' => ['nullable', 'boolean'],
            'births.*.condition' => ['nullable', Rule::in(array_keys(Birth::CONDITIONS))],
            'births.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delivered_at.required' => 'Enter when the baby was delivered.',
            'delivered_at.before_or_equal' => 'The delivery time cannot be in the future.',
            'mode.required' => 'Choose the mode of delivery.',
            'maternal_outcome.required' => 'Record the mother\'s condition.',
            'births.required' => 'Record at least one baby.',
            'births.*.outcome.required' => 'Choose the birth outcome for each baby.',
            'births.*.sex.required' => 'Choose the sex of each baby.',
        ];
    }
}
