<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

class AddDeactivatedAccountsPermissions extends Migration
{
    /**
     * @var array<string, array<int, string>>
     */
    protected $groups = [
        'deactivated rider' => ['deactivated rider list', 'deactivated rider delete'],
        'driver reactivation request' => ['driver reactivation request list', 'driver reactivation request action'],
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $adminRole = Role::findOrCreate('admin', 'web');

        foreach ($this->groups as $groupName => $children) {
            $group = Permission::findOrCreate($groupName, 'web');
            $group->parent_id = null;
            $group->save();
            $adminRole->givePermissionTo($group);

            foreach ($children as $childName) {
                $child = Permission::findOrCreate($childName, 'web');
                $child->parent_id = $group->id;
                $child->save();
                $adminRole->givePermissionTo($child);
            }
        }

        Cache::flush();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $names = [];
        foreach ($this->groups as $groupName => $children) {
            $names[] = $groupName;
            foreach ($children as $childName) {
                $names[] = $childName;
            }
        }

        Permission::whereIn('name', $names)->delete();
        Cache::flush();
    }
}
