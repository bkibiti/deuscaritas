<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Recovery for a lockout: makes sure the Superuser role exists with every permission, gives it
 * to the named user, and clears the permission cache.
 */
class RestoreSuperuser extends Command
{
    protected $signature = 'dcs:restore-superuser {username : The username (or email) of the user to restore}';

    protected $description = 'Give a user the Superuser role again, and give that role every permission';

    public function handle(): int
    {
        $login = $this->argument('username');
        $user = User::where('username', $login)->orWhere('email', $login)->first();

        if (! $user) {
            $this->error("No user with the username or email \"{$login}\".");

            return self::FAILURE;
        }

        $role = Role::firstOrCreate(['name' => 'Superuser', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::where('guard_name', 'web')->get());

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info("{$user->name} is a Superuser again, and the Superuser role has all {$role->permissions()->count()} permissions.");

        return self::SUCCESS;
    }
}
