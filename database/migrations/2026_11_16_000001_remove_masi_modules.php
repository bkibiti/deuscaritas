<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * DCS-RAWMS started from a copy of the Masi code and database. This removes what Masi left
 * behind: its tables, its permissions and its roles. Users, the remaining roles, settings
 * and reminders are kept. There is no way back from this migration.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = [
        'loan_status_histories',
        'loan_penalties',
        'loan_installments',
        'loan_guarantors',
        'payment_allocations',
        'member_fines',
        'member_penalties',
        'member_dues',
        'other_incomes',
        'payments',
        'loans',
        'loan_types',
        'fine_types',
        'payment_types',
        'payment_methods',
        'contribution_settings',
        'expenses',
        'expense_categories',
        'members',
    ];

    /** @var array<int, string> */
    private array $permissions = [
        'View Members', 'Manage Members',
        'View Contributions', 'Record Payments', 'Manage Payments',
        'View Contribution Settings', 'Manage Contribution Settings',
        'View Payment Methods', 'Manage Payment Methods',
        'View Payment Types', 'Manage Payment Types',
        'View Loan Types', 'Manage Loan Types',
        'View Loans', 'Manage Loans', 'Approve Loans', 'Disburse Loans', 'Record Loan Repayments',
        'View Fine Types', 'Manage Fine Types', 'View Fines', 'Manage Fines',
        'View Other Income', 'Manage Other Income',
        'View Expense Management', 'View Expenses', 'Add Expenses', 'Edit Expenses',
        'View Expense Categories', 'Manage Expense Categories',
        'View Advanced Dashboard',
    ];

    /** @var array<int, string> */
    private array $roles = ['Loan Officer', 'Treasurer', 'Member Login', 'Member'];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        $permissionIds = DB::table('permissions')->whereIn('name', $this->permissions)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        $roleIds = DB::table('roles')->whereIn('name', $this->roles)->pluck('id');
        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        DB::table('roles')->whereIn('id', $roleIds)->delete();

        DB::table('settings')->where('name', 'loan_guarantor_threshold_percent')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // The Masi tables and their data are not restored.
    }
};
