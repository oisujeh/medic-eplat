<?php

namespace App\Services;

use App\Enums\FacilityLevel;
use App\Enums\FacilityOwnership;
use App\Enums\NumberKind;
use App\Enums\TierProfile;
use App\Models\GlobalProperty;
use Illuminate\Support\Facades\Cache;

/**
 * The facility profile captured by the first-run wizard, backed by global
 * properties. Every request consults it, so the resolved profile is cached
 * until the next write.
 *
 * @phpstan-type Profile array{
 *     name: string|null, state: string|null, lga: string|null, code: string|null,
 *     level: string|null, ownership: string|null, tier_profile: string|null,
 *     dhis2_org_unit: string|null, notice: string|null,
 *     billing_enabled: bool, patient_number_prefix: string, patient_number_check_digit: bool,
 *     completed_at: string|null
 * }
 */
class FacilitySettings
{
    public const KEY_NAME = 'facility.name';

    public const KEY_STATE = 'facility.state';

    public const KEY_LGA = 'facility.lga';

    public const KEY_CODE = 'facility.code';

    public const KEY_LEVEL = 'facility.level';

    public const KEY_OWNERSHIP = 'facility.ownership';

    public const KEY_TIER_PROFILE = 'facility.tier_profile';

    public const KEY_DHIS2_ORG_UNIT = 'facility.dhis2_org_unit';

    public const KEY_NOTICE = 'facility.notice';

    public const KEY_BILLING_ENABLED = 'billing.enabled';

    public const KEY_PATIENT_NUMBER_PREFIX = 'numbering.patient_prefix';

    public const KEY_PATIENT_NUMBER_CHECK_DIGIT = 'numbering.patient_check_digit';

    public const KEY_SETUP_COMPLETED_AT = 'setup.completed_at';

    private const CACHE_KEY = 'facility.profile';

    /**
     * The optional profile properties and how each is stored.
     *
     * @var list<string>
     */
    private const OPTIONAL_KEYS = [
        self::KEY_LEVEL, self::KEY_OWNERSHIP, self::KEY_TIER_PROFILE, self::KEY_DHIS2_ORG_UNIT,
        self::KEY_NOTICE, self::KEY_BILLING_ENABLED, self::KEY_PATIENT_NUMBER_PREFIX,
        self::KEY_PATIENT_NUMBER_CHECK_DIGIT,
    ];

    /**
     * Human-readable purpose of each profile property, stored alongside it.
     *
     * @var array<string, string>
     */
    private const DESCRIPTIONS = [
        self::KEY_NAME => 'Official name of the facility, shown across the system.',
        self::KEY_STATE => 'State the facility is located in.',
        self::KEY_LGA => 'Local government area the facility is located in.',
        self::KEY_CODE => 'Facility registry code (e.g. the health facility registry / NHMIS code).',
        self::KEY_LEVEL => 'Level of care: primary, secondary or tertiary.',
        self::KEY_OWNERSHIP => 'Who owns the facility: public, private or faith-based.',
        self::KEY_TIER_PROFILE => 'The configuration profile the facility runs: PHC, secondary, tertiary or private.',
        self::KEY_DHIS2_ORG_UNIT => 'The facility\'s DHIS2 organisation unit UID, for NHMIS data value set exports.',
        self::KEY_NOTICE => 'A notice shown to every member of staff on the home screen.',
        self::KEY_BILLING_ENABLED => 'Whether billing is switched on. When off, no charges post and no prices are shown.',
        self::KEY_PATIENT_NUMBER_PREFIX => 'The prefix on every hospital (file) number.',
        self::KEY_PATIENT_NUMBER_CHECK_DIGIT => 'Whether hospital numbers carry a check digit.',
        self::KEY_SETUP_COMPLETED_AT => 'When the first-run setup wizard was completed.',
    ];

    /**
     * Whether the first-run wizard has been completed.
     */
    public function isConfigured(): bool
    {
        return $this->resolved()['completed_at'] !== null;
    }

    /**
     * The facility profile as shared with the frontend.
     *
     * @return Profile
     */
    public function profile(): array
    {
        return $this->resolved();
    }

    /**
     * Whether charges post and prices show anywhere in the workflow (PRD BIL-01).
     */
    public function billingEnabled(): bool
    {
        return $this->resolved()['billing_enabled'];
    }

    /**
     * How the patient file number prints (PRD REG-01).
     *
     * @return array{patient_prefix: string, patient_check_digit: bool}
     */
    public function numbering(): array
    {
        $profile = $this->resolved();

        return [
            'patient_prefix' => $profile['patient_number_prefix'],
            'patient_check_digit' => $profile['patient_number_check_digit'],
        ];
    }

    /**
     * Persist the facility profile. The identity fields are required; the
     * rest are written only when present, and a blank notice clears the board.
     *
     * @param  array<string, mixed>  $profile
     */
    public function save(array $profile): void
    {
        GlobalProperty::put(self::KEY_NAME, $profile['name'], self::DESCRIPTIONS[self::KEY_NAME]);
        GlobalProperty::put(self::KEY_STATE, $profile['state'], self::DESCRIPTIONS[self::KEY_STATE]);
        GlobalProperty::put(self::KEY_LGA, $profile['lga'], self::DESCRIPTIONS[self::KEY_LGA]);
        GlobalProperty::put(self::KEY_CODE, $profile['code'], self::DESCRIPTIONS[self::KEY_CODE]);

        foreach (self::OPTIONAL_KEYS as $key) {
            $field = $this->fieldFor($key);

            if (! array_key_exists($field, $profile)) {
                continue;
            }

            GlobalProperty::put($key, $this->storable($key, $profile[$field]), self::DESCRIPTIONS[$key]);
        }

        $this->forget();
    }

    /**
     * Persist the facility profile and mark the first-run wizard as done.
     *
     * @param  array<string, mixed>  $profile
     */
    public function complete(array $profile): void
    {
        $this->save($profile);

        GlobalProperty::put(
            self::KEY_SETUP_COMPLETED_AT,
            now()->toIso8601String(),
            self::DESCRIPTIONS[self::KEY_SETUP_COMPLETED_AT],
        );

        $this->forget();
    }

    /**
     * Drop the cached profile so the next read hits the database.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The pick-lists behind the profile form.
     *
     * @return array{levels: array<int, array{value: string, label: string}>, ownerships: array<int, array{value: string, label: string}>, tierProfiles: array<int, array{value: string, label: string}>}
     */
    public static function options(): array
    {
        return [
            'levels' => FacilityLevel::options(),
            'ownerships' => FacilityOwnership::options(),
            'tierProfiles' => TierProfile::options(),
        ];
    }

    /**
     * The keys a resolved profile carries. A cached profile missing any of
     * them was written by an older release and is rebuilt.
     *
     * @var list<string>
     */
    private const PROFILE_KEYS = [
        'name', 'state', 'lga', 'code', 'level', 'ownership', 'tier_profile', 'dhis2_org_unit', 'notice',
        'billing_enabled', 'patient_number_prefix', 'patient_number_check_digit', 'completed_at',
    ];

    /**
     * @return Profile
     */
    private function resolved(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && array_diff_key(array_flip(self::PROFILE_KEYS), $cached) === []) {
            /** @var Profile $cached */
            return $cached;
        }

        $profile = $this->build();

        Cache::forever(self::CACHE_KEY, $profile);

        return $profile;
    }

    /**
     * The profile as the property table holds it now.
     *
     * @return Profile
     */
    private function build(): array
    {
        $values = GlobalProperty::query()
            ->whereIn('key', [
                self::KEY_NAME, self::KEY_STATE, self::KEY_LGA, self::KEY_CODE,
                ...self::OPTIONAL_KEYS,
                self::KEY_SETUP_COMPLETED_AT,
            ])
            ->pluck('value', 'key');

        $prefix = trim((string) $values->get(self::KEY_PATIENT_NUMBER_PREFIX));

        return [
            'name' => $values->get(self::KEY_NAME),
            'state' => $values->get(self::KEY_STATE),
            'lga' => $values->get(self::KEY_LGA),
            'code' => $values->get(self::KEY_CODE),
            'level' => $values->get(self::KEY_LEVEL),
            'ownership' => $values->get(self::KEY_OWNERSHIP),
            'tier_profile' => $values->get(self::KEY_TIER_PROFILE),
            'dhis2_org_unit' => $values->get(self::KEY_DHIS2_ORG_UNIT),
            'notice' => $values->get(self::KEY_NOTICE),
            // Billing stays on until a facility switches it off, so an
            // installation set up before the switch existed keeps working.
            'billing_enabled' => $values->get(self::KEY_BILLING_ENABLED, '1') === '1',
            'patient_number_prefix' => $prefix === '' ? NumberKind::Patient->prefix() : $prefix,
            'patient_number_check_digit' => $values->get(self::KEY_PATIENT_NUMBER_CHECK_DIGIT) === '1',
            'completed_at' => $values->get(self::KEY_SETUP_COMPLETED_AT),
        ];
    }

    /**
     * The profile array key a property is submitted under.
     */
    private function fieldFor(string $key): string
    {
        return match ($key) {
            self::KEY_BILLING_ENABLED => 'billing_enabled',
            self::KEY_PATIENT_NUMBER_PREFIX => 'patient_number_prefix',
            self::KEY_PATIENT_NUMBER_CHECK_DIGIT => 'patient_number_check_digit',
            default => substr($key, strlen('facility.')),
        };
    }

    /**
     * Reduce a submitted value to the string the property table holds.
     */
    private function storable(string $key, mixed $value): ?string
    {
        if (in_array($key, [self::KEY_BILLING_ENABLED, self::KEY_PATIENT_NUMBER_CHECK_DIGIT], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0';
        }

        $value = trim((string) $value);

        if ($key === self::KEY_PATIENT_NUMBER_PREFIX) {
            $value = strtoupper($value);
        }

        return $value === '' ? null : $value;
    }
}
