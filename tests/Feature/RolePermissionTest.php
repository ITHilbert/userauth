<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use ITHilbert\UserAuth\Entities\Permission;
use ITHilbert\UserAuth\Entities\PermissionGroup;
use ITHilbert\UserAuth\Entities\Role;
use Tests\TestCase;

final class RolePermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('permissions_groups');
        Schema::dropIfExists('roles');

        Schema::create('roles', function ($t) {
            $t->bigIncrements('id');
            $t->string('role')->unique();
            $t->string('role_display');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('permissions_groups', function ($t) {
            $t->bigIncrements('id');
            $t->string('group_name')->unique();
            $t->string('group_display');
            $t->integer('is_group')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('permissions', function ($t) {
            $t->bigIncrements('id');
            $t->string('permission')->unique();
            $t->string('permission_display');
            $t->bigInteger('group_id');
            $t->string('crud', 10);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('role_permission', function ($t) {
            $t->unsignedBigInteger('role_id');
            $t->unsignedBigInteger('permission_id');
        });
    }

    public function test_role_fillable_only_allows_whitelisted_columns(): void
    {
        $role = Role::create([
            'role' => 'editor',
            'role_display' => 'Editor',
            'unknown_column' => 'should-be-ignored',
        ]);

        $this->assertSame('editor', $role->role);
        $this->assertSame('Editor', $role->role_display);
        $this->assertArrayNotHasKey('unknown_column', $role->getAttributes());
    }

    public function test_permission_fillable_only_allows_whitelisted_columns(): void
    {
        $group = PermissionGroup::create([
            'group_name' => 'user',
            'group_display' => 'User',
            'is_group' => 0,
        ]);

        $perm = Permission::create([
            'permission' => 'user_edit',
            'permission_display' => 'User editieren',
            'group_id' => $group->id,
            'crud' => 'edit',
            'evil_field' => 'should-be-ignored',
        ]);

        $this->assertSame('user_edit', $perm->permission);
        $this->assertSame('edit', $perm->crud);
        $this->assertArrayNotHasKey('evil_field', $perm->getAttributes());
    }

    public function test_has_permission_or_returns_true_if_any_match(): void
    {
        $role = Role::create(['role' => 'editor', 'role_display' => 'Editor']);
        $group = PermissionGroup::create(['group_name' => 'user', 'group_display' => 'User']);
        $permEdit = Permission::create([
            'permission' => 'user_edit', 'permission_display' => 'edit',
            'group_id' => $group->id, 'crud' => 'edit',
        ]);
        $role->permissions()->save($permEdit);

        $this->assertTrue($role->hasPermissionOr('user_edit, user_delete'));
        $this->assertTrue($role->hasPermissionOr('user_delete, user_edit'));
    }

    public function test_has_permission_or_returns_false_if_no_match(): void
    {
        $role = Role::create(['role' => 'editor', 'role_display' => 'Editor']);
        $group = PermissionGroup::create(['group_name' => 'user', 'group_display' => 'User']);
        $permEdit = Permission::create([
            'permission' => 'user_edit', 'permission_display' => 'edit',
            'group_id' => $group->id, 'crud' => 'edit',
        ]);
        $role->permissions()->save($permEdit);

        $this->assertFalse($role->hasPermissionOr('user_delete, user_create'));
    }

    public function test_has_permission_and_requires_all_to_match(): void
    {
        $role = Role::create(['role' => 'editor', 'role_display' => 'Editor']);
        $group = PermissionGroup::create(['group_name' => 'user', 'group_display' => 'User']);
        $permEdit = Permission::create([
            'permission' => 'user_edit', 'permission_display' => 'edit',
            'group_id' => $group->id, 'crud' => 'edit',
        ]);
        $permDelete = Permission::create([
            'permission' => 'user_delete', 'permission_display' => 'delete',
            'group_id' => $group->id, 'crud' => 'delete',
        ]);
        $role->permissions()->save($permEdit);
        $role->permissions()->save($permDelete);

        $this->assertTrue($role->hasPermissionAnd('user_edit, user_delete'));
        $this->assertFalse($role->hasPermissionAnd('user_edit, user_create'));
    }

    public function test_has_permission_returns_true_for_assigned_permission(): void
    {
        $role = Role::create(['role' => 'editor', 'role_display' => 'Editor']);
        $group = PermissionGroup::create(['group_name' => 'user', 'group_display' => 'User']);
        $perm = Permission::create([
            'permission' => 'user_edit', 'permission_display' => 'edit',
            'group_id' => $group->id, 'crud' => 'edit',
        ]);
        $role->permissions()->save($perm);

        $this->assertTrue($role->hasPermission('user_edit'));
        $this->assertFalse($role->hasPermission('user_delete'));
    }

    public function test_dev_and_admin_have_all_permissions_implicitly(): void
    {
        $dev = Role::create(['role' => 'dev', 'role_display' => 'Developer']);
        $admin = Role::create(['role' => 'admin', 'role_display' => 'Administrator']);

        $this->assertTrue($dev->hasPermission('anything_at_all'));
        $this->assertTrue($admin->hasPermission('anything_at_all'));
        $this->assertTrue($dev->hasPermissionOr('a, b, c'));
        $this->assertTrue($admin->hasPermissionAnd('a, b, c'));
    }
}
