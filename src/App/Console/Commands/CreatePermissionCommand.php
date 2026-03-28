<?php

namespace ITHilbert\UserAuth\App\Console\Commands;

use Illuminate\Console\Command;
use ITHilbert\UserAuth\Entities\Permission;

class CreatePermissionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'userauth:permission:create {permission : The system name of the permission, e.g. manage-billing} {permission_display? : The human readable name} {group_id? : ID of the permission group}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a new permission in the database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $permissionName = $this->argument('permission');
        $permissionDisplay = $this->argument('permission_display') ?? ucfirst(str_replace(['-', '_'], ' ', $permissionName));
        $groupId = $this->argument('group_id') ?? 1; // Standard Gruppe falls nicht angegeben

        $existing = Permission::where('permission', $permissionName)->first();

        if ($existing) {
            $this->error("Permission [{$permissionName}] already exists.");
            return 1;
        }

        $permission = new Permission();
        $permission->permission = $permissionName;
        $permission->permission_display = $permissionDisplay;
        $permission->group_id = $groupId;
        $permission->save();

        $this->info("Successfully created permission '{$permissionName}'.");

        return 0;
    }
}
