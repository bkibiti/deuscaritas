<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Arranges permissions for the role forms: grouped by the part of the system they belong to,
 * in the order of the menu, with the matching "View" and "Manage" permissions next to each other.
 */
class PermissionGroups
{
    /**
     * Group title => patterns on the permission name. The first group that matches wins, so a
     * permission appears once.
     *
     * @var array<string, array<int, string>>
     */
    private const GROUPS = [
        'Requests' => ['/Requests?$/', '/^View All Requests$/'],
        'Inventory and stock' => ['/Stock$/', '/Inventory/', '/Suppliers?$/'],
        'Administration' => ['/Administration$/', '/Schools?$/', '/Departments?$/', '/Request Categories$/', '/Approval Chain$/'],
        'User Management' => ['/User Management$/', '/Users?$/', '/Roles?$/'],
        'Settings and dashboard' => ['/Settings$/', '/Dashboard$/'],
    ];

    /** Names that open an area; they come first in their group. */
    private const FIRST = ['/^View (Administration|User Management|Settings)$/'];

    /** How a permission starts, in the order they should read. */
    private const VERBS = ['View', 'Add', 'Create', 'Edit', 'Manage', 'Assign', 'Receive', 'Approve', 'Delete'];

    /**
     * @param  iterable<int, object>  $permissions  objects with ->id and ->name
     * @return array<string, Collection<int, object>> group title => permissions, in display order
     */
    public static function arrange(iterable $permissions): array
    {
        $groups = array_fill_keys(array_keys(self::GROUPS), []);
        $groups['Other'] = [];

        foreach ($permissions as $permission) {
            $groups[self::titleFor($permission->name)][] = $permission;
        }

        $arranged = [];

        foreach ($groups as $title => $members) {
            if ($members === []) {
                continue;
            }

            usort($members, fn ($a, $b) => self::sortKey($a->name) <=> self::sortKey($b->name));
            $arranged[$title] = collect($members);
        }

        return $arranged;
    }

    private static function titleFor(string $name): string
    {
        foreach (self::GROUPS as $title => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $name)) {
                    return $title;
                }
            }
        }

        return 'Other';
    }

    /**
     * @return array{0: int, 1: string, 2: int}
     */
    private static function sortKey(string $name): array
    {
        foreach (self::FIRST as $pattern) {
            if (preg_match($pattern, $name)) {
                return [0, '', 0];
            }
        }

        $verb = strtok($name, ' ');
        $noun = trim(substr($name, strlen($verb)));
        $rank = array_search($verb, self::VERBS, true);

        return [1, strtolower($noun), $rank === false ? count(self::VERBS) : $rank];
    }
}
