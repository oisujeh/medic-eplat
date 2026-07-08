<?php

use App\Http\Controllers\ClinicalController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientRegistrationController;
use App\Http\Controllers\PatientRoutingController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\QueueEntryController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\VitalSignsController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::middleware('module:registration')->group(function () {
        Route::get('registration', [PatientRegistrationController::class, 'create'])->name('patients.register');
        Route::post('registration', [PatientRegistrationController::class, 'store'])->name('patients.store');
    });

    Route::middleware('module:patient-records')->group(function () {
        Route::get('patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
    });

    // Patient flow — service-point queues.
    Route::post('patients/{patient}/route', [PatientRoutingController::class, 'store'])->name('patients.route');
    Route::post('visits/{visit}/close', [VisitController::class, 'close'])->name('visits.close');

    Route::middleware('module:queues')->group(function () {
        Route::get('queues', [QueueController::class, 'index'])->name('queues.index');
        Route::get('queues/{servicePoint:slug}', [QueueController::class, 'show'])->name('queues.show');
    });

    // Clinical module — clinician consultation console.
    Route::middleware('module:clinical')->group(function () {
        Route::get('clinical', [ClinicalController::class, 'index'])->name('clinical.index');
        Route::get('clinical/{entry}', [ClinicalController::class, 'consult'])->name('clinical.consult');
        Route::post('clinical/{entry}/save', [ClinicalController::class, 'save'])->name('clinical.save');
        Route::post('clinical/{entry}/complete', [ClinicalController::class, 'complete'])->name('clinical.complete');
    });

    Route::post('queue-entries/{entry}/call', [QueueEntryController::class, 'call'])->name('queue-entries.call');
    Route::post('queue-entries/{entry}/complete', [QueueEntryController::class, 'complete'])->name('queue-entries.complete');
    Route::post('queue-entries/{entry}/cancel', [QueueEntryController::class, 'cancel'])->name('queue-entries.cancel');
    Route::post('queue-entries/{entry}/vitals', [VitalSignsController::class, 'store'])->name('queue-entries.vitals');

    Route::get('modules/{module:slug}', [ModuleController::class, 'show'])->name('modules.show');
});

require __DIR__.'/settings.php';
