<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Users belong to a school (not a department) and the approval chain names roles, not people.
 *
 *  - users: school_id replaces department_id.
 *  - workflow_steps: each step names a role and a scope ("school": the holder must belong to the
 *    request's school; "any": a holder anywhere). The department head step is removed.
 *  - request_approvals: each step records the role and school it needs; whoever acts is in acted_by.
 *  - schools and departments no longer have a head user.
 */
return new class extends Migration
{
    /** @var array<string, string> step key => role name */
    private array $roleNames = [
        'school_head' => 'Head of School',
        'coordinator' => 'School Coordinator',
        'manager' => 'Manager',
        'director' => 'Director',
    ];

    /**
     * Every step checks what is already there, so the migration can be run again after a
     * failure part-way (MySQL does not roll back schema changes).
     */
    public function up(): void
    {
        $now = now();

        if (! Schema::hasColumn('users', 'school_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('users', 'department_id')) {
            // A user's school: the school of the department they were in.
            foreach (DB::table('departments')->whereNotNull('school_id')->get(['id', 'school_id']) as $department) {
                DB::table('users')->where('department_id', $department->id)->update(['school_id' => $department->school_id]);
            }

            // (SQLite rebuilds the table to drop a column, which needs foreign keys off for a moment.)
            Schema::withoutForeignKeyConstraints(function () {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropForeign(['department_id']);
                    $table->dropColumn('department_id');
                });
            });
        }

        // Roles for the steps, then the people who held each step keep it through the role.
        $roleIds = [];
        foreach ($this->roleNames as $key => $name) {
            $id = DB::table('roles')->where('name', $name)->where('guard_name', 'web')->value('id');
            $roleIds[$key] = $id ?: DB::table('roles')->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }

        $give = function (int $userId, int $roleId): void {
            DB::table('model_has_roles')->insertOrIgnore(['role_id' => $roleId, 'model_type' => 'App\\Models\\User', 'model_id' => $userId]);
        };

        if (Schema::hasColumn('schools', 'head_user_id')) {
            foreach (DB::table('schools')->whereNotNull('head_user_id')->get(['id', 'head_user_id']) as $school) {
                $give($school->head_user_id, $roleIds['school_head']);
                DB::table('users')->where('id', $school->head_user_id)->update(['school_id' => $school->id]);
            }
        }

        if (Schema::hasColumn('workflow_steps', 'user_id')) {
            foreach (DB::table('workflow_steps')->whereNotNull('user_id')->get(['key', 'user_id']) as $step) {
                if (isset($roleIds[$step->key])) {
                    $give($step->user_id, $roleIds[$step->key]);
                }
            }
        }

        // role_id must be the same type as roles.id, which differs between databases.
        $bigRoleIds = $this->rolesUseBigIntegerIds();

        if (! Schema::hasColumn('workflow_steps', 'role_id')) {
            Schema::table('workflow_steps', function (Blueprint $table) use ($bigRoleIds) {
                $bigRoleIds ? $table->unsignedBigInteger('role_id')->nullable()->after('name') : $table->unsignedInteger('role_id')->nullable()->after('name');
            });
        }

        $this->matchRoleIdType('workflow_steps', $bigRoleIds);

        if (! Schema::hasColumn('workflow_steps', 'scope')) {
            Schema::table('workflow_steps', function (Blueprint $table) {
                $table->string('scope', 10)->default('any')->after('role_id'); // school | any
            });
        }

        if (! $this->hasForeignKeyOn('workflow_steps', 'role_id')) {
            Schema::table('workflow_steps', function (Blueprint $table) {
                $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            });
        }

        DB::table('workflow_steps')->where('key', 'department_head')->delete();

        foreach ($roleIds as $key => $roleId) {
            DB::table('workflow_steps')->where('key', $key)->update([
                'role_id' => $roleId,
                'scope' => $key === 'school_head' ? 'school' : 'any',
            ]);
        }

        foreach (DB::table('workflow_steps')->orderBy('position')->pluck('id')->values() as $index => $id) {
            DB::table('workflow_steps')->where('id', $id)->update(['position' => $index + 1]);
        }

        if (Schema::hasColumn('workflow_steps', 'user_id')) {
            Schema::table('workflow_steps', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }

        if (Schema::hasColumn('workflow_steps', 'approver_type')) {
            Schema::table('workflow_steps', function (Blueprint $table) {
                $table->dropColumn('approver_type');
            });
        }

        if (! Schema::hasColumn('request_approvals', 'role_id')) {
            Schema::table('request_approvals', function (Blueprint $table) use ($bigRoleIds) {
                $bigRoleIds ? $table->unsignedBigInteger('role_id')->nullable()->after('step_name') : $table->unsignedInteger('role_id')->nullable()->after('step_name');
            });
        }

        $this->matchRoleIdType('request_approvals', $bigRoleIds);

        if (! Schema::hasColumn('request_approvals', 'scope_school_id')) {
            Schema::table('request_approvals', function (Blueprint $table) {
                $table->unsignedBigInteger('scope_school_id')->nullable()->after('role_id');
            });
        }

        if (Schema::hasColumn('request_approvals', 'approver_id')) {
            // Requests already in the chain: the step needs its role (and the request's school).
            foreach (DB::table('request_approvals')->get(['id', 'request_id', 'step_key']) as $approval) {
                $schoolScoped = $approval->step_key === 'school_head';
                DB::table('request_approvals')->where('id', $approval->id)->update([
                    'role_id' => $roleIds[$approval->step_key] ?? null,
                    'scope_school_id' => $schoolScoped ? DB::table('requests')->where('id', $approval->request_id)->value('school_id') : null,
                ]);
            }

            Schema::table('request_approvals', function (Blueprint $table) {
                // The foreign key first: MySQL will not drop an index a foreign key still uses.
                $table->dropForeign(['approver_id']);
                $table->dropIndex(['approver_id', 'status']);
                $table->dropColumn('approver_id');
            });
        }

        if (! $this->hasIndexOn('request_approvals', ['role_id', 'status'])) {
            Schema::table('request_approvals', function (Blueprint $table) {
                $table->index(['role_id', 'status']);
            });
        }

        foreach (['schools', 'departments'] as $tableName) {
            if (Schema::hasColumn($tableName, 'head_user_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['head_user_id']);
                    $table->dropColumn('head_user_id');
                });
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * A role_id column left by an earlier failed run may have the wrong type: correct it, or the
     * foreign key to roles.id is refused.
     */
    private function matchRoleIdType(string $table, bool $big): void
    {
        foreach (Schema::getColumns($table) as $column) {
            if ($column['name'] !== 'role_id') {
                continue;
            }

            if (str_contains(strtolower($column['type']), 'bigint') !== $big) {
                Schema::table($table, function (Blueprint $blueprint) use ($big) {
                    $big ? $blueprint->unsignedBigInteger('role_id')->nullable()->change() : $blueprint->unsignedInteger('role_id')->nullable()->change();
                });
            }
        }
    }

    private function rolesUseBigIntegerIds(): bool
    {
        foreach (Schema::getColumns('roles') as $column) {
            if ($column['name'] === 'id') {
                return str_contains(strtolower($column['type']), 'bigint');
            }
        }

        return true;
    }

    private function hasForeignKeyOn(string $table, string $column): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if ($foreignKey['columns'] === [$column]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function hasIndexOn(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === $columns) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->unsignedInteger('head_user_id')->nullable();
            $table->foreign('head_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedInteger('head_user_id')->nullable();
            $table->foreign('head_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('request_approvals', function (Blueprint $table) {
            $table->dropIndex(['role_id', 'status']);
            $table->dropColumn(['role_id', 'scope_school_id']);
            $table->unsignedInteger('approver_id')->nullable();
            $table->foreign('approver_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['approver_id', 'status']);
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'scope']);
            $table->string('approver_type', 20)->default('user');
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['school_id']);
                $table->dropColumn('school_id');
            });
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
        });
    }
};
