<?php

namespace App\Services;

use App\Enums\IdentifierType;
use App\Enums\MatchLevel;
use App\Enums\MatchSignal;
use App\Models\Patient;
use App\Support\MatchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Scores how likely two patient records are to describe one person.
 *
 * Deterministic signals (NIN) settle it on their own; the rest add up: a
 * shared surname, a first name that sounds alike, the same date of birth,
 * the same phone, the same parent. The weights and the bands the total
 * falls into come from MatchingRules, so the facility can tune them.
 */
class PatientMatcher
{
    private const BLOCK_LIMIT = 300;

    public function __construct(private readonly MatchingRules $rules) {}

    /**
     * Existing records the details could describe, best match first.
     *
     * @param  Patient|array<string, mixed>  $subject  A record, or the details typed on a form.
     * @return Collection<int, MatchResult>
     */
    public function candidatesFor(Patient|array $subject, ?Patient $except = null): Collection
    {
        $details = $this->details($subject);
        $except ??= $subject instanceof Patient ? $subject : null;

        return $this->block($details, $except)
            ->map(fn (Patient $candidate) => $this->score($subject, $candidate))
            ->filter(fn (MatchResult $result) => $result->level->isShown())
            ->sortByDesc(fn (MatchResult $result) => $result->score)
            ->values();
    }

    /**
     * Score one existing record against the details.
     *
     * @param  Patient|array<string, mixed>  $subject
     */
    public function score(Patient|array $subject, Patient $candidate): MatchResult
    {
        $a = $this->details($subject);
        $b = $this->details($candidate);
        $signals = [];

        if ($a['nin'] !== null && $a['nin'] === $b['nin']) {
            $signals[] = MatchSignal::NinExact;
        }

        if ($a['phone'] !== null && $a['phone'] === $b['phone']) {
            $signals[] = MatchSignal::PhoneExact;
        }

        if ($a['dob'] !== null && $b['dob'] !== null) {
            if ($a['dob'] === $b['dob']) {
                $signals[] = MatchSignal::DobExact;
            } elseif (substr($a['dob'], 0, 4) === substr($b['dob'], 0, 4)) {
                $signals[] = MatchSignal::DobYear;
            }
        }

        if ($a['sex'] !== null && $b['sex'] !== null) {
            $signals[] = $a['sex'] === $b['sex'] ? MatchSignal::SexSame : MatchSignal::SexDiffers;
        }

        $swapped = $a['surname'] !== null && $a['first_name'] !== null
            && $a['surname'] === $b['first_name'] && $a['first_name'] === $b['surname'];

        if ($swapped) {
            $signals[] = MatchSignal::NamesSwapped;
        } else {
            $this->compareName($a['surname'], $b['surname'], MatchSignal::SurnameExact, MatchSignal::SurnameSounds, $signals);
            $this->compareName($a['first_name'], $b['first_name'], MatchSignal::FirstNameExact, MatchSignal::FirstNameSounds, $signals);
        }

        if ($a['lga'] !== null && $a['lga'] === $b['lga']) {
            $signals[] = MatchSignal::LgaSame;
        }

        if ($a['mother_id'] !== null && $a['mother_id'] === $b['mother_id']) {
            $signals[] = MatchSignal::MotherLinked;
        } elseif ($a['parent'] !== null && $a['parent'] === $b['parent']) {
            $signals[] = MatchSignal::ParentSame;
        }

        $score = array_sum(array_map(fn (MatchSignal $s) => $this->rules->weight($s), $signals));

        return new MatchResult($candidate, $score, $this->level($score), $signals);
    }

    public function level(int $score): MatchLevel
    {
        return match (true) {
            $score >= $this->rules->autoThreshold() => MatchLevel::Auto,
            $score >= $this->rules->reviewThreshold() => MatchLevel::Review,
            $score >= $this->rules->hintThreshold() => MatchLevel::Hint,
            default => MatchLevel::None,
        };
    }

    /**
     * The records worth scoring: anything sharing an identifier, a name or a
     * date of birth with the details. Merged tombstones are never offered.
     *
     * @param  array<string, mixed>  $details
     * @return Collection<int, Patient>
     */
    private function block(array $details, ?Patient $except): Collection
    {
        if ($this->isBlank($details)) {
            return collect();
        }

        return Patient::query()
            ->current()
            ->when($except, fn (Builder $q) => $q->whereKeyNot($except->id))
            ->where(function (Builder $q) use ($details) {
                $q->whereRaw('1 = 0');

                if ($details['nin'] !== null) {
                    $q->orWhereHas('identifiers', fn (Builder $i) => $i->where('type', IdentifierType::Nin->value)->where('value', $details['nin']));
                }

                if ($details['phone'] !== null) {
                    $q->orWhereHas('identifiers', fn (Builder $i) => $i->where('type', IdentifierType::Phone->value)->where('value', $details['phone']));
                }

                if ($details['surname'] !== null) {
                    $q->orWhere('surname', 'like', $details['surname_raw']);
                }

                if ($details['first_name'] !== null) {
                    $q->orWhere('first_name', 'like', $details['first_name_raw']);
                }

                if ($details['surname'] !== null && $details['first_name'] !== null) {
                    $q->orWhere(fn (Builder $s) => $s->where('surname', 'like', $details['first_name_raw'])->where('first_name', 'like', $details['surname_raw']));
                }

                if ($details['dob'] !== null) {
                    $q->orWhereDate('date_of_birth', $details['dob']);
                }

                if ($details['mother_id'] !== null) {
                    $q->orWhere('mother_patient_id', $details['mother_id']);
                }
            })
            ->limit(self::BLOCK_LIMIT)
            ->get();
    }

    /**
     * The comparable form of a record or of typed details.
     *
     * @param  Patient|array<string, mixed>  $subject
     * @return array<string, mixed>
     */
    private function details(Patient|array $subject): array
    {
        $get = fn (string $key): mixed => $subject instanceof Patient ? $subject->getAttribute($key) : ($subject[$key] ?? null);

        $dob = $get('date_of_birth');
        $dob = $dob instanceof \DateTimeInterface ? $dob->format('Y-m-d') : (filled($dob) ? Carbon::parse((string) $dob)->toDateString() : null);

        $relationship = strtolower(trim((string) $get('next_of_kin_relationship')));
        $parent = in_array($relationship, ['parent', 'mother', 'father', 'guardian'], true)
            ? $this->nameKey($get('next_of_kin_name'))
            : null;

        return [
            'nin' => $this->identifier($get('nin'), IdentifierType::Nin),
            'phone' => $this->identifier($get('phone'), IdentifierType::Phone),
            'dob' => $dob,
            'sex' => filled($get('sex')) ? strtoupper(substr((string) $get('sex'), 0, 1)) : null,
            'surname' => $this->nameKey($get('surname')),
            'surname_raw' => trim((string) $get('surname')),
            'first_name' => $this->nameKey($get('first_name')),
            'first_name_raw' => trim((string) $get('first_name')),
            'lga' => filled($get('lga')) ? Str::lower(trim((string) $get('lga'))) : null,
            'parent' => $parent,
            'mother_id' => $get('mother_patient_id') !== null ? (int) $get('mother_patient_id') : null,
        ];
    }

    /**
     * @param  list<MatchSignal>  $signals
     */
    private function compareName(?string $a, ?string $b, MatchSignal $exact, MatchSignal $sounds, array &$signals): void
    {
        if ($a === null || $b === null) {
            return;
        }

        if ($a === $b) {
            $signals[] = $exact;
        } elseif (metaphone($a) !== '' && metaphone($a) === metaphone($b)) {
            $signals[] = $sounds;
        }
    }

    private function nameKey(mixed $name): ?string
    {
        $key = preg_replace('/[^a-z]/', '', Str::lower(Str::ascii(trim((string) $name)))) ?? '';

        return $key === '' ? null : $key;
    }

    private function identifier(mixed $value, IdentifierType $type): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalised = $type->normalise($value);

        return strlen($normalised) < 7 ? null : $normalised;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function isBlank(array $details): bool
    {
        return $details['nin'] === null
            && $details['phone'] === null
            && $details['dob'] === null
            && $details['mother_id'] === null
            && ($details['surname'] === null || $details['first_name'] === null);
    }
}
