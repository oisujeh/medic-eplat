<?php

use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\FacilityController as AdminFacilityController;
use App\Http\Controllers\Admin\FormDesignerController as AdminFormDesignerController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AllergyController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BreakGlassController;
use App\Http\Controllers\ClaimBatchController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\ClinicalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataQualityController;
use App\Http\Controllers\DataQualityRuleController;
use App\Http\Controllers\DataQualityScorecardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DuplicateController;
use App\Http\Controllers\EncounterController;
use App\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\IcdCodeController;
use App\Http\Controllers\ImmunizationController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LabCatalogueController;
use App\Http\Controllers\LaboratoryController;
use App\Http\Controllers\LabOrderController;
use App\Http\Controllers\MaternityController;
use App\Http\Controllers\MedicationController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotifiableDiseaseController;
use App\Http\Controllers\NursingController;
use App\Http\Controllers\ObservationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientRegistrationController;
use App\Http\Controllers\PatientRelationshipController;
use App\Http\Controllers\PatientRoutingController;
use App\Http\Controllers\PatientStatusController;
use App\Http\Controllers\PayerController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\ProblemController;
use App\Http\Controllers\ProviderScheduleController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\QueueEntryController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReportCatalogController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportRunnerController;
use App\Http\Controllers\ServiceChargeController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StockRequisitionController;
use App\Http\Controllers\SurveillanceController;
use App\Http\Controllers\TerminologyController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\WardController;
use Illuminate\Support\Facades\Route;

// The root URL sends guests to the login screen and signed-in staff to the dashboard.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

// First-run setup. Until the facility profile is captured, signed-in staff are
// held here by the EnsureFacilityIsConfigured middleware.
Route::middleware('auth')->group(function () {
    Route::get('setup/pending', [SetupController::class, 'pending'])->name('setup.pending');

    Route::middleware('module:administration')->group(function () {
        Route::get('setup', [SetupController::class, 'show'])->name('setup.show');
        Route::post('setup', [SetupController::class, 'store'])->name('setup.store');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Concept lookup for the pickers on clinical forms (drugs, allergens, vaccines).
    Route::get('terminology/search', [TerminologyController::class, 'search'])->name('terminology.search');

    Route::middleware('module:registration')->group(function () {
        Route::get('registration', [PatientRegistrationController::class, 'create'])->name('patients.register');
        // The Master Patient Index check the form runs while details are typed.
        Route::get('registration/lookup', [PatientRegistrationController::class, 'lookup'])->name('patients.lookup');
        Route::post('registration', [PatientRegistrationController::class, 'store'])->name('patients.store');

        // Duplicate review. Registered before patients/{patient} so the
        // static path wins.
        Route::get('patients/duplicates', [DuplicateController::class, 'index'])->name('patients.duplicates.index');
        Route::get('patients/duplicates/{candidate}', [DuplicateController::class, 'show'])->name('patients.duplicates.show');
        Route::post('patients/duplicates/{candidate}/dismiss', [DuplicateController::class, 'dismiss'])->name('patients.duplicates.dismiss');
        Route::post('patients/duplicates/{candidate}/merge', [DuplicateController::class, 'merge'])->name('patients.duplicates.merge')->middleware('permission:patients.merge');
        Route::get('patients/{patient}/edit', [PatientRegistrationController::class, 'edit'])->name('patients.edit');
        Route::patch('patients/{patient}/status', [PatientStatusController::class, 'update'])->name('patients.status');
        Route::get('patients/{patient}/relationships/search', [PatientRelationshipController::class, 'search'])->name('patients.relationships.search');
        Route::post('patients/{patient}/relationships', [PatientRelationshipController::class, 'store'])->name('patients.relationships.store');
        Route::delete('patients/{patient}/relationships/{relationship}', [PatientRelationshipController::class, 'destroy'])->name('patients.relationships.destroy');
        Route::patch('patients/{patient}', [PatientRegistrationController::class, 'update'])->name('patients.update');
    });

    Route::middleware('module:patient-records')->group(function () {
        Route::get('patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
        Route::post('patients/{patient}/break-glass', [BreakGlassController::class, 'store'])->name('patients.break-glass');
    });

    // Patient flow — service-point queues.
    Route::post('patients/{patient}/route', [PatientRoutingController::class, 'store'])->name('patients.route');
    Route::post('visits/{visit}/close', [VisitController::class, 'close'])->name('visits.close');

    Route::middleware('module:queues')->group(function () {
        Route::get('queues', [QueueController::class, 'index'])->name('queues.index');
        Route::get('queues/{servicePoint:slug}', [QueueController::class, 'show'])->name('queues.show');
    });

    // Appointments module — scheduling, walk-ins, check-in and provider schedules.
    Route::middleware('module:appointments')->group(function () {
        Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');

        // Static paths must precede the {appointment} wildcard.
        Route::get('appointments/slots', [AppointmentController::class, 'slots'])->name('appointments.slots');
        Route::get('appointments/patient-search', [AppointmentController::class, 'patientSearch'])->name('appointments.patients');

        Route::get('appointments/schedules', [ProviderScheduleController::class, 'index'])->name('appointments.schedules');
        Route::post('appointments/schedules', [ProviderScheduleController::class, 'storeSchedule'])->name('appointments.schedules.store')->middleware('permission:appointments.manage_schedules');
        Route::patch('appointments/schedules/{schedule}', [ProviderScheduleController::class, 'updateSchedule'])->name('appointments.schedules.update')->middleware('permission:appointments.manage_schedules');
        Route::delete('appointments/schedules/{schedule}', [ProviderScheduleController::class, 'deleteSchedule'])->name('appointments.schedules.destroy')->middleware('permission:appointments.manage_schedules');
        Route::post('appointments/blocks', [ProviderScheduleController::class, 'storeBlock'])->name('appointments.blocks.store')->middleware('permission:appointments.manage_schedules');
        Route::delete('appointments/blocks/{block}', [ProviderScheduleController::class, 'deleteBlock'])->name('appointments.blocks.destroy')->middleware('permission:appointments.manage_schedules');

        Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::post('appointments/walk-in', [AppointmentController::class, 'walkIn'])->name('appointments.walk-in');
        Route::patch('appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::post('appointments/{appointment}/check-in', [AppointmentController::class, 'checkIn'])->name('appointments.check-in');
        Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::post('appointments/{appointment}/no-show', [AppointmentController::class, 'noShow'])->name('appointments.no-show');
    });

    // Clinical module — the clinician console. Opening a queue entry starts
    // (or resumes) its encounter and redirects to the encounter screen.
    Route::middleware('module:clinical')->group(function () {
        Route::get('clinical', [ClinicalController::class, 'index'])->name('clinical.index');
        // Static paths must precede the {entry} wildcard.
        Route::get('clinical/icd-search', [IcdCodeController::class, 'search'])->name('clinical.icd-search');
        Route::get('clinical/{entry}', [ClinicalController::class, 'open'])->name('clinical.consult');
    });

    // Nursing module — the nursing console for triage, ANC, family planning
    // and immunization.
    Route::middleware('module:nursing')->group(function () {
        Route::get('nursing', [NursingController::class, 'index'])->name('nursing.index');
        Route::get('nursing/{entry}', [NursingController::class, 'open'])->name('nursing.workspace');
    });

    // Encounters — every clinical contact, whoever documents it. Access is
    // governed by EncounterPolicy rather than a single module.
    Route::get('encounters/{encounter}', [EncounterController::class, 'show'])->name('encounters.show');
    Route::patch('encounters/{encounter}', [EncounterController::class, 'update'])->name('encounters.update');
    Route::post('encounters/{encounter}/sign', [EncounterController::class, 'sign'])->name('encounters.sign');
    Route::post('encounters/{encounter}/follow-up', [EncounterController::class, 'bookFollowUp'])->name('encounters.follow-up');
    Route::post('encounters/{encounter}/addenda', [EncounterController::class, 'addend'])->name('encounters.addenda.store');

    // Clinical records written during an encounter.
    Route::post('encounters/{encounter}/problems', [ProblemController::class, 'store'])->name('encounters.problems.store');
    Route::patch('encounters/{encounter}/problems/{problem}', [ProblemController::class, 'update'])->name('encounters.problems.update');
    Route::post('encounters/{encounter}/problems/{problem}/resolve', [ProblemController::class, 'resolve'])->name('encounters.problems.resolve');
    Route::delete('encounters/{encounter}/problems/{problem}', [ProblemController::class, 'destroy'])->name('encounters.problems.destroy');

    Route::post('encounters/{encounter}/medications', [MedicationController::class, 'store'])->name('encounters.medications.store');
    Route::patch('encounters/{encounter}/medications/{medication}', [MedicationController::class, 'update'])->name('encounters.medications.update');
    Route::post('encounters/{encounter}/medications/{medication}/stop', [MedicationController::class, 'stop'])->name('encounters.medications.stop');
    Route::delete('encounters/{encounter}/medications/{medication}', [MedicationController::class, 'destroy'])->name('encounters.medications.destroy');

    Route::post('encounters/{encounter}/allergies', [AllergyController::class, 'store'])->name('encounters.allergies.store');
    Route::delete('encounters/{encounter}/allergies/{allergy}', [AllergyController::class, 'destroy'])->name('encounters.allergies.destroy');

    Route::post('encounters/{encounter}/lab-orders', [LabOrderController::class, 'store'])->name('encounters.lab-orders.store');
    Route::patch('encounters/{encounter}/lab-results/{labResult}', [LabOrderController::class, 'result'])->name('encounters.lab-results.update');
    Route::delete('encounters/{encounter}/lab-results/{labResult}', [LabOrderController::class, 'destroy'])->name('encounters.lab-results.destroy');

    Route::post('encounters/{encounter}/immunizations', [ImmunizationController::class, 'store'])->name('encounters.immunizations.store');

    // Clinical forms (PRD 4.3): started from the encounter, filled on their
    // own screen, authorised by the encounter policy and the form's roles.
    Route::get('encounters/{encounter}/forms/{form}', [FormSubmissionController::class, 'open'])->name('encounters.forms.open');
    Route::get('forms/{submission}', [FormSubmissionController::class, 'show'])->name('forms.show');
    Route::patch('forms/{submission}', [FormSubmissionController::class, 'save'])->name('forms.save');
    Route::post('forms/{submission}/complete', [FormSubmissionController::class, 'complete'])->name('forms.complete');
    Route::post('forms/{submission}/void', [FormSubmissionController::class, 'void'])->name('forms.void');

    // Referrals are issued from the encounter (authorised by the encounter
    // policy) and tracked in the referrals module.
    Route::post('encounters/{encounter}/referrals', [ReferralController::class, 'store'])->name('encounters.referrals.store');

    Route::middleware('module:referrals')->group(function () {
        Route::get('referrals', [ReferralController::class, 'index'])->name('referrals.index');
        Route::get('referrals/{referral}', [ReferralController::class, 'show'])->name('referrals.show');
        Route::get('referrals/{referral}/letter', [ReferralController::class, 'letter'])->name('referrals.letter');
        Route::post('referrals/{referral}/status', [ReferralController::class, 'status'])->name('referrals.status');
    });
    Route::delete('encounters/{encounter}/immunizations/{immunization}', [ImmunizationController::class, 'destroy'])->name('encounters.immunizations.destroy');

    // Observations — recorded from a queue, an encounter or a ward; the
    // request decides who may record from the context it is given.
    Route::post('patients/{patient}/observations', [ObservationController::class, 'store'])->name('patients.observations.store');

    // Maternity module — the antenatal register, deliveries and births.
    Route::middleware('module:maternity')->group(function () {
        Route::get('maternity', [MaternityController::class, 'index'])->name('maternity.index');
        Route::post('maternity', [MaternityController::class, 'store'])->name('maternity.store');

        // Static paths must precede the {pregnancy} wildcard.
        Route::get('maternity/patient-search', [MaternityController::class, 'patientSearch'])->name('maternity.patients');
        Route::post('maternity/births/{birth}/register', [DeliveryController::class, 'registerNewborn'])->name('maternity.births.register');

        Route::get('maternity/{pregnancy}', [MaternityController::class, 'show'])->name('maternity.show');
        Route::patch('maternity/{pregnancy}', [MaternityController::class, 'update'])->name('maternity.update');
        Route::post('maternity/{pregnancy}/close', [MaternityController::class, 'close'])->name('maternity.close');
        Route::post('maternity/{pregnancy}/delivery', [DeliveryController::class, 'store'])->name('maternity.delivery.store');
    });

    // Case surveillance module — IDSR notifiable disease cases and the
    // catalogue that detects them.
    Route::middleware('module:surveillance')->group(function () {
        Route::get('surveillance', [SurveillanceController::class, 'index'])->name('surveillance.index');

        // Static paths must precede the {case} wildcard.
        Route::get('surveillance/diseases', [NotifiableDiseaseController::class, 'index'])->name('surveillance.diseases.index');
        Route::patch('surveillance/diseases/{disease}', [NotifiableDiseaseController::class, 'update'])->name('surveillance.diseases.update')->middleware('permission:surveillance.manage_catalogue');

        Route::get('surveillance/{case}', [SurveillanceController::class, 'show'])->name('surveillance.show');
        Route::patch('surveillance/{case}', [SurveillanceController::class, 'update'])->name('surveillance.update');
        Route::post('surveillance/{case}/notify', [SurveillanceController::class, 'notify'])->name('surveillance.notify')->middleware('permission:surveillance.notify');
    });

    // Admissions module — wards, beds and the inpatient record.
    Route::middleware('module:admissions')->group(function () {
        Route::get('admissions', [AdmissionController::class, 'index'])->name('admissions.index');
        Route::post('admissions', [AdmissionController::class, 'store'])->name('admissions.store');

        // Static paths must precede the {admission} wildcard.
        Route::get('admissions/patient-search', [AdmissionController::class, 'patientSearch'])->name('admissions.patients');

        Route::post('admissions/wards', [WardController::class, 'store'])->name('admissions.wards.store')->middleware('permission:admissions.manage_wards');
        Route::get('admissions/wards/{ward}', [WardController::class, 'show'])->name('admissions.wards.show');
        Route::patch('admissions/wards/{ward}', [WardController::class, 'update'])->name('admissions.wards.update')->middleware('permission:admissions.manage_wards');
        Route::post('admissions/wards/{ward}/beds', [WardController::class, 'storeBeds'])->name('admissions.wards.beds.store')->middleware('permission:admissions.manage_wards');
        Route::patch('admissions/beds/{bed}', [WardController::class, 'updateBed'])->name('admissions.beds.update')->middleware('permission:admissions.manage_wards');

        Route::get('admissions/{admission}', [AdmissionController::class, 'show'])->name('admissions.show');
        Route::post('admissions/{admission}/assign', [AdmissionController::class, 'assign'])->name('admissions.assign');
        Route::post('admissions/{admission}/transfer', [AdmissionController::class, 'transfer'])->name('admissions.transfer');
        Route::post('admissions/{admission}/discharge', [AdmissionController::class, 'discharge'])->name('admissions.discharge')->middleware('permission:admissions.discharge');
        Route::post('admissions/{admission}/cancel', [AdmissionController::class, 'cancel'])->name('admissions.cancel');
        Route::post('admissions/{admission}/notes', [AdmissionController::class, 'storeNote'])->name('admissions.notes.store');
    });

    // Laboratory module — specimen processing, result entry and verification.
    Route::middleware('module:laboratory')->group(function () {
        Route::get('laboratory', [LaboratoryController::class, 'index'])->name('laboratory.index');

        Route::get('laboratory/catalogue', [LabCatalogueController::class, 'index'])->name('laboratory.catalogue.index');
        Route::get('laboratory/catalogue/{test}', [LabCatalogueController::class, 'show'])->name('laboratory.catalogue.show');
        Route::post('laboratory/catalogue/{test}/ranges', [LabCatalogueController::class, 'storeRange'])->name('laboratory.catalogue.ranges.store')->middleware('permission:laboratory.manage_catalogue');
        Route::delete('laboratory/catalogue/{test}/ranges/{range}', [LabCatalogueController::class, 'destroyRange'])->name('laboratory.catalogue.ranges.destroy')->middleware('permission:laboratory.manage_catalogue');

        Route::get('laboratory/{order}', [LaboratoryController::class, 'show'])->name('laboratory.show');
        Route::post('laboratory/{order}/collect', [LaboratoryController::class, 'collect'])->name('laboratory.collect');
        Route::post('laboratory/{order}/receive', [LaboratoryController::class, 'receive'])->name('laboratory.receive');
        Route::post('laboratory/{order}/results', [LaboratoryController::class, 'saveResults'])->name('laboratory.results');
        Route::post('laboratory/{order}/verify', [LaboratoryController::class, 'verify'])->name('laboratory.verify')->middleware('permission:laboratory.verify_results');
        Route::post('laboratory/{order}/cancel', [LaboratoryController::class, 'cancel'])->name('laboratory.cancel')->middleware('permission:laboratory.cancel_orders');
    });

    // Stocked products for the prescribing form (any clinician).
    Route::get('products/search', [InventoryController::class, 'products'])->name('products.search');

    // Inventory / Store module.
    Route::middleware('module:inventory')->group(function () {
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::patch('inventory/settings', [InventoryController::class, 'updateSettings'])->name('inventory.settings.update')->middleware('permission:inventory.manage_settings');
        Route::post('inventory/reorder-levels/apply', [InventoryController::class, 'applyReorderLevels'])->name('inventory.reorder.apply')->middleware('permission:inventory.manage_settings');
        Route::post('inventory/batches/{batch}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust')->middleware('permission:inventory.adjust_stock');

        Route::get('inventory/requisitions', [StockRequisitionController::class, 'index'])->name('inventory.requisitions.index');
        Route::post('inventory/requisitions', [StockRequisitionController::class, 'store'])->name('inventory.requisitions.store');
        Route::get('inventory/requisitions/{requisition}', [StockRequisitionController::class, 'show'])->name('inventory.requisitions.show');
        Route::post('inventory/requisitions/{requisition}/approve', [StockRequisitionController::class, 'approve'])->name('inventory.requisitions.approve')->middleware('permission:inventory.approve_requisitions');
        Route::post('inventory/requisitions/{requisition}/issue', [StockRequisitionController::class, 'issue'])->name('inventory.requisitions.issue')->middleware('permission:inventory.issue_stock');
        Route::post('inventory/requisitions/{requisition}/receive', [StockRequisitionController::class, 'receive'])->name('inventory.requisitions.receive');
        Route::post('inventory/requisitions/{requisition}/cancel', [StockRequisitionController::class, 'cancel'])->name('inventory.requisitions.cancel');

        Route::get('inventory/{item}', [InventoryController::class, 'show'])->name('inventory.show');
        Route::post('inventory/{item}/receive', [InventoryController::class, 'receive'])->name('inventory.receive');
    });

    // Pharmacy module — dispensing from stock.
    Route::middleware('module:pharmacy')->group(function () {
        Route::get('pharmacy', [PharmacyController::class, 'index'])->name('pharmacy.index');
        Route::get('pharmacy/{entry}', [PharmacyController::class, 'show'])->name('pharmacy.dispense');
        Route::post('pharmacy/{entry}/dispense', [PharmacyController::class, 'dispense'])->name('pharmacy.dispense.store')->middleware('permission:pharmacy.dispense');
        Route::post('pharmacy/{entry}/prescriptions/{medication}/external', [PharmacyController::class, 'markExternal'])->name('pharmacy.prescriptions.external')->middleware('permission:pharmacy.dispense');
        Route::post('pharmacy/{entry}/prescriptions/{medication}/reopen', [PharmacyController::class, 'reopen'])->name('pharmacy.prescriptions.reopen')->middleware('permission:pharmacy.dispense');
    });

    // Billing module — the patient's running bill.
    Route::middleware('module:billing')->group(function () {
        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');

        // Fee schedule (static path — must precede the {bill} wildcard).
        Route::get('billing/services', [ServiceChargeController::class, 'index'])->name('billing.services.index');
        Route::post('billing/services', [ServiceChargeController::class, 'store'])->name('billing.services.store')->middleware('permission:billing.manage_fees');
        Route::patch('billing/services/{serviceCharge}', [ServiceChargeController::class, 'update'])->name('billing.services.update')->middleware('permission:billing.manage_fees');

        Route::get('billing/{bill}', [BillingController::class, 'show'])->name('billing.show');
        Route::get('billing/{bill}/invoice', [BillingController::class, 'invoice'])->name('billing.invoice');
        Route::post('billing/{bill}/charge', [BillingController::class, 'addCharge'])->name('billing.charge');
        Route::post('billing/{bill}/pay', [BillingController::class, 'pay'])->name('billing.pay')->middleware('permission:billing.record_payments');
    });

    // Claims module — NHIA / HMO claims, schedules and payers.
    Route::middleware('module:claims')->group(function () {
        Route::get('claims', [ClaimController::class, 'index'])->name('claims.index');
        Route::post('claims', [ClaimController::class, 'store'])->name('claims.store');

        // Static paths must precede the {claim} wildcard.
        Route::get('claims/bill-search', [ClaimController::class, 'billSearch'])->name('claims.bills');

        Route::get('claims/payers', [PayerController::class, 'index'])->name('claims.payers.index');
        Route::post('claims/payers', [PayerController::class, 'store'])->name('claims.payers.store')->middleware('permission:claims.manage_payers');
        Route::patch('claims/payers/{payer}', [PayerController::class, 'update'])->name('claims.payers.update')->middleware('permission:claims.manage_payers');

        Route::get('claims/batches', [ClaimBatchController::class, 'index'])->name('claims.batches.index');
        Route::get('claims/batches/{batch}', [ClaimBatchController::class, 'show'])->name('claims.batches.show');
        Route::post('claims/batches/{batch}/submit', [ClaimBatchController::class, 'submit'])->name('claims.batches.submit')->middleware('permission:claims.manage');

        Route::get('claims/{claim}', [ClaimController::class, 'show'])->name('claims.show');
        Route::delete('claims/{claim}', [ClaimController::class, 'destroy'])->name('claims.destroy')->middleware('permission:claims.manage');
        Route::patch('claims/{claim}/lines/{line}', [ClaimController::class, 'updateLine'])->name('claims.lines.update');
        Route::post('claims/{claim}/authorization', [ClaimController::class, 'authorize'])->name('claims.authorize')->middleware('permission:claims.manage');
        Route::post('claims/{claim}/submit', [ClaimController::class, 'submit'])->name('claims.submit')->middleware('permission:claims.manage');
        Route::post('claims/{claim}/remit', [ClaimController::class, 'remit'])->name('claims.remit')->middleware('permission:claims.manage');
        Route::post('claims/{claim}/reject', [ClaimController::class, 'reject'])->name('claims.reject')->middleware('permission:claims.manage');
    });

    // Reports module — a catalogue of runnable reports plus the executive dashboard.
    Route::middleware('module:reports')->group(function () {
        Route::get('reports', [ReportCatalogController::class, 'index'])->name('reports.index');
        Route::get('reports/overview', [ReportController::class, 'index'])->name('reports.overview');
        Route::get('reports/run/{report}', [ReportRunnerController::class, 'show'])->name('reports.run');
        Route::get('reports/run/{report}/export', [ReportRunnerController::class, 'export'])->name('reports.export');
    });

    // Data quality module — the issue queue, the scorecard and the rules
    // that raise them.
    Route::middleware('module:data-quality')->group(function () {
        Route::get('data-quality', [DataQualityController::class, 'index'])->name('data-quality.index');
        Route::get('data-quality/scorecard', [DataQualityScorecardController::class, 'show'])->name('data-quality.scorecard');

        Route::get('data-quality/rules', [DataQualityRuleController::class, 'index'])->name('data-quality.rules.index');
        Route::patch('data-quality/rules/{rule}', [DataQualityRuleController::class, 'update'])->name('data-quality.rules.update')->middleware('permission:data-quality.manage_rules');

        Route::post('data-quality/issues/{issue}/recheck', [DataQualityController::class, 'recheck'])->name('data-quality.issues.recheck');
        Route::post('data-quality/issues/{issue}/waive', [DataQualityController::class, 'waive'])->name('data-quality.issues.waive')->middleware('permission:data-quality.waive_issues');
    });

    // Administration module — staff accounts and role assignment. The
    // administration module maps to no roles, so only a role granting all
    // modules (Super Administrator) reaches these routes.
    Route::middleware('module:administration')->group(function () {
        Route::get('admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::post('admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::patch('admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::post('admin/users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('admin.users.deactivate');
        Route::post('admin/users/{user}/reactivate', [AdminUserController::class, 'reactivate'])->name('admin.users.reactivate');
        Route::delete('admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');

        Route::get('admin/facility', [AdminFacilityController::class, 'edit'])->name('admin.facility.edit');
        Route::patch('admin/facility', [AdminFacilityController::class, 'update'])->name('admin.facility.update');

        // The audit trail — read-only, plus an on-demand hash-chain check.
        Route::get('admin/audit', [AdminAuditLogController::class, 'index'])->name('admin.audit.index');
        Route::post('admin/audit/verify', [AdminAuditLogController::class, 'verify'])->name('admin.audit.verify');

        // The form designer: the catalogue of clinical forms and their
        // working drafts. Reading is open to the module; changing needs
        // the forms permission.
        Route::get('admin/forms', [AdminFormDesignerController::class, 'index'])->name('admin.forms.index');
        Route::post('admin/forms', [AdminFormDesignerController::class, 'store'])->name('admin.forms.store')->middleware('permission:forms.manage');
        Route::post('admin/forms/import', [AdminFormDesignerController::class, 'import'])->name('admin.forms.import')->middleware('permission:forms.manage');
        Route::get('admin/forms/{form}', [AdminFormDesignerController::class, 'show'])->name('admin.forms.show');
        Route::patch('admin/forms/{form}', [AdminFormDesignerController::class, 'update'])->name('admin.forms.update')->middleware('permission:forms.manage');
        Route::post('admin/forms/{form}/publish', [AdminFormDesignerController::class, 'publish'])->name('admin.forms.publish')->middleware('permission:forms.manage');
        Route::post('admin/forms/{form}/retire', [AdminFormDesignerController::class, 'retire'])->name('admin.forms.retire')->middleware('permission:forms.manage');
        Route::post('admin/forms/{form}/restore', [AdminFormDesignerController::class, 'restore'])->name('admin.forms.restore')->middleware('permission:forms.manage');
        Route::get('admin/forms/{form}/export', [AdminFormDesignerController::class, 'export'])->name('admin.forms.export');
    });

    // Queue management: attending happens in the consoles; these fix the queue.
    Route::patch('queue-entries/{entry}/assign', [QueueEntryController::class, 'assign'])->name('queue-entries.assign');
    Route::post('queue-entries/{entry}/reroute', [QueueEntryController::class, 'reroute'])->name('queue-entries.reroute');
    Route::post('queue-entries/{entry}/cancel', [QueueEntryController::class, 'cancel'])->name('queue-entries.cancel');

    Route::get('modules/{module:slug}', [ModuleController::class, 'show'])->name('modules.show');
});

require __DIR__.'/settings.php';
