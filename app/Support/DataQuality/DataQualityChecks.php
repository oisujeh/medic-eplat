<?php

namespace App\Support\DataQuality;

use App\Support\DataQuality\Rules\CaseNotificationOverdue;
use App\Support\DataQuality\Rules\ClaimNotSubmitted;
use App\Support\DataQuality\Rules\DiagnosisNotCoded;
use App\Support\DataQuality\Rules\DuplicateReviewAgeing;
use App\Support\DataQuality\Rules\EncounterMissingDiagnosis;
use App\Support\DataQuality\Rules\EncounterUnsigned;
use App\Support\DataQuality\Rules\LabResultUnverified;
use App\Support\DataQuality\Rules\ObservationImplausible;
use App\Support\DataQuality\Rules\PatientContactMissing;
use App\Support\DataQuality\Rules\PatientDemographicsIncomplete;
use App\Support\DataQuality\Rules\PatientDobImplausible;
use App\Support\DataQuality\Rules\PatientNextOfKinMissing;
use App\Support\DataQuality\Rules\PatientPhoneMalformed;
use App\Support\DataQuality\Rules\ReferralWithoutFeedback;
use App\Support\DataQuality\Rules\SharedIdentifier;
use App\Support\DataQuality\Rules\VisitLeftOpen;
use Illuminate\Support\Collection;

/**
 * Every check the code knows about. A registry row names one of these by key;
 * adding a check is a class plus an entry here, and the seeder writes its row.
 */
final class DataQualityChecks
{
    /**
     * @return list<class-string<DataQualityCheck>>
     */
    public static function checks(): array
    {
        return [
            // Completeness
            PatientDemographicsIncomplete::class,
            PatientContactMissing::class,
            PatientNextOfKinMissing::class,
            EncounterMissingDiagnosis::class,
            DiagnosisNotCoded::class,

            // Validity
            PatientDobImplausible::class,
            PatientPhoneMalformed::class,
            ObservationImplausible::class,

            // Duplicates
            SharedIdentifier::class,
            DuplicateReviewAgeing::class,

            // Timeliness
            EncounterUnsigned::class,
            LabResultUnverified::class,
            CaseNotificationOverdue::class,
            VisitLeftOpen::class,
            ReferralWithoutFeedback::class,
            ClaimNotSubmitted::class,
        ];
    }

    /**
     * The checks, keyed by their rule key, in registry order.
     *
     * @return Collection<string, DataQualityCheck>
     */
    public static function all(): Collection
    {
        return collect(self::checks())->mapWithKeys(fn (string $class) => [$class::key() => new $class]);
    }

    public static function find(string $key): ?DataQualityCheck
    {
        return self::all()->get($key);
    }
}
