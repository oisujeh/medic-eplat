<?php

namespace App\Services;

use App\Models\GlobalProperty;
use Illuminate\Support\Facades\Cache;

/**
 * The facility profile captured by the first-run wizard, backed by global
 * properties. Every request consults it, so the resolved profile is cached
 * until the next write.
 */
class FacilitySettings
{
    public const KEY_NAME = 'facility.name';

    public const KEY_STATE = 'facility.state';

    public const KEY_LGA = 'facility.lga';

    public const KEY_CODE = 'facility.code';

    public const KEY_NOTICE = 'facility.notice';

    public const KEY_SETUP_COMPLETED_AT = 'setup.completed_at';

    private const CACHE_KEY = 'facility.profile';

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
        self::KEY_NOTICE => 'A notice shown to every member of staff on the home screen.',
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
     * @return array{name: string|null, state: string|null, lga: string|null, code: string|null, notice: string|null, completed_at: string|null}
     */
    public function profile(): array
    {
        return $this->resolved();
    }

    /**
     * Persist the facility profile. The notice is optional: the first-run
     * wizard does not ask for one, and a blank notice clears the board.
     *
     * @param  array{name: string, state: string, lga: string, code: string, notice?: string|null}  $profile
     */
    public function save(array $profile): void
    {
        GlobalProperty::put(self::KEY_NAME, $profile['name'], self::DESCRIPTIONS[self::KEY_NAME]);
        GlobalProperty::put(self::KEY_STATE, $profile['state'], self::DESCRIPTIONS[self::KEY_STATE]);
        GlobalProperty::put(self::KEY_LGA, $profile['lga'], self::DESCRIPTIONS[self::KEY_LGA]);
        GlobalProperty::put(self::KEY_CODE, $profile['code'], self::DESCRIPTIONS[self::KEY_CODE]);

        if (array_key_exists('notice', $profile)) {
            $notice = trim((string) $profile['notice']);
            GlobalProperty::put(self::KEY_NOTICE, $notice === '' ? null : $notice, self::DESCRIPTIONS[self::KEY_NOTICE]);
        }

        $this->forget();
    }

    /**
     * Persist the facility profile and mark the first-run wizard as done.
     *
     * @param  array{name: string, state: string, lga: string, code: string, notice?: string|null}  $profile
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
     * @return array{name: string|null, state: string|null, lga: string|null, code: string|null, notice: string|null, completed_at: string|null}
     */
    private function resolved(): array
    {
        /** @var array{name: string|null, state: string|null, lga: string|null, code: string|null, notice: string|null, completed_at: string|null} */
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $values = GlobalProperty::query()
                ->whereIn('key', [
                    self::KEY_NAME,
                    self::KEY_STATE,
                    self::KEY_LGA,
                    self::KEY_CODE,
                    self::KEY_NOTICE,
                    self::KEY_SETUP_COMPLETED_AT,
                ])
                ->pluck('value', 'key');

            return [
                'name' => $values->get(self::KEY_NAME),
                'state' => $values->get(self::KEY_STATE),
                'lga' => $values->get(self::KEY_LGA),
                'code' => $values->get(self::KEY_CODE),
                'notice' => $values->get(self::KEY_NOTICE),
                'completed_at' => $values->get(self::KEY_SETUP_COMPLETED_AT),
            ];
        });
    }
}
