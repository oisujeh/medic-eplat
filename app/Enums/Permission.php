<?php

namespace App\Enums;

/**
 * The actions a role can be granted beneath its module access. Module access
 * says which consoles a role can open; a permission says which sensitive
 * actions it can take there. A role that grants all modules holds every
 * permission.
 */
enum Permission: string
{
    case VerifyLabResults = 'laboratory.verify_results';
    case CancelLabOrders = 'laboratory.cancel_orders';
    case Dispense = 'pharmacy.dispense';
    case AdjustStock = 'inventory.adjust_stock';
    case RecordPayments = 'billing.record_payments';
    case ManageFees = 'billing.manage_fees';
    case ManageClaims = 'claims.manage';
    case ManagePayers = 'claims.manage_payers';
    case Discharge = 'admissions.discharge';
    case ManageWards = 'admissions.manage_wards';
    case NotifySurveillance = 'surveillance.notify';
    case ManageSurveillanceCatalogue = 'surveillance.manage_catalogue';
    case ManageSchedules = 'appointments.manage_schedules';
    case MergePatients = 'patients.merge';

    public function label(): string
    {
        return match ($this) {
            self::MergePatients => 'Merge duplicate patient records',
            self::VerifyLabResults => 'Verify and release laboratory results',
            self::CancelLabOrders => 'Cancel laboratory orders',
            self::Dispense => 'Dispense medicines',
            self::AdjustStock => 'Adjust stock counts',
            self::RecordPayments => 'Record payments',
            self::ManageFees => 'Manage the fee schedule',
            self::ManageClaims => 'Authorise, submit, remit and reject claims',
            self::ManagePayers => 'Manage payers and their tariffs',
            self::Discharge => 'Discharge inpatients',
            self::ManageWards => 'Manage wards and beds',
            self::NotifySurveillance => 'Record DSNO notifications',
            self::ManageSurveillanceCatalogue => 'Manage the notifiable disease catalogue',
            self::ManageSchedules => 'Manage provider schedules',
        };
    }

    /**
     * The module the permission belongs to, for grouping.
     */
    public function module(): string
    {
        return explode('.', $this->value, 2)[0];
    }

    /**
     * The roles that hold the permission on a fresh installation. Roles that
     * grant all modules are not listed: they hold everything.
     *
     * @return list<string>
     */
    public function defaultRoles(): array
    {
        return match ($this) {
            self::MergePatients => ['records-officer', 'chief-medical-director'],
            self::VerifyLabResults => ['laboratory-staff', 'chief-medical-director'],
            self::CancelLabOrders => ['laboratory-staff', 'physician', 'chief-medical-director'],
            self::Dispense => ['pharmacy-staff', 'chief-medical-director'],
            self::AdjustStock => ['inventory-officer', 'pharmacy-staff', 'chief-medical-director'],
            self::RecordPayments => ['cashier', 'chief-medical-director'],
            self::ManageFees => ['cashier', 'chief-medical-director'],
            self::ManageClaims => ['cashier', 'chief-medical-director'],
            self::ManagePayers => ['cashier', 'chief-medical-director'],
            self::Discharge => ['physician', 'chief-medical-director'],
            self::ManageWards => ['nurse', 'chief-medical-director'],
            self::NotifySurveillance => ['physician', 'nurse', 'records-officer', 'chief-medical-director'],
            self::ManageSurveillanceCatalogue => ['chief-medical-director'],
            self::ManageSchedules => ['records-officer', 'physician', 'chief-medical-director'],
        };
    }

    /**
     * @return array<int, array{value: string, label: string, module: string}>
     */
    public static function definitions(): array
    {
        return array_map(fn (self $p) => [
            'value' => $p->value,
            'label' => $p->label(),
            'module' => $p->module(),
        ], self::cases());
    }
}
