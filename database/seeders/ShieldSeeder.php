<?php

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolesWithPermissions = '[
                {
                    "name":"admin",
                    "guard_name":"web",
                    "permissions":[
                        "view_audit",
                        "view_any_audit",
                        "view_module",
                        "view_any_module",
                        "create_module",
                        "update_module",
                        "delete_module",
                        "delete_any_module",
                        "view_user",
                        "view_any_user",
                        "create_user",
                        "update_user",
                        "delete_user",
                        "delete_any_user"
                    ]
                },
                {
                    "name":"manager",
                    "guard_name":"web",
                    "permissions":[]
                },
                {
                    "name":"staff",
                    "guard_name":"web",
                    "permissions":[]
                }
            ]';

        $directPermissions = '[]';

        static::makeRolesWithPermissions($rolesWithPermissions);
        static::makeDirectPermissions($directPermissions);

        $this->command->info('Shield Seeding Completed.');
    }

    protected static function makeRolesWithPermissions(string $rolesWithPermissions): void
    {
        if (! blank($rolePlusPermissions = json_decode($rolesWithPermissions, true))) {
            /** @var Model $roleModel */
            $roleModel = Utils::getRoleModel();
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($rolePlusPermissions as $rolePlusPermission) {
                $role = $roleModel::firstOrCreate([
                    'name' => $rolePlusPermission['name'],
                    'guard_name' => $rolePlusPermission['guard_name'],
                ]);

                if (! blank($rolePlusPermission['permissions'])) {
                    $permissionModels = collect($rolePlusPermission['permissions'])
                        ->map(
                            fn ($permission) => $permissionModel::firstOrCreate([
                                'name' => $permission,
                                'guard_name' => $rolePlusPermission['guard_name'],
                            ]),
                        )
                        ->all();

                    $role->syncPermissions($permissionModels);
                }
            }
        }
    }

    public static function makeDirectPermissions(string $directPermissions): void
    {
        if (! blank($permissions = json_decode($directPermissions, true))) {
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($permissions as $permission) {
                if ($permissionModel::whereName($permission)->doesntExist()) {
                    $permissionModel::create([
                        'name' => $permission['name'],
                        'guard_name' => $permission['guard_name'],
                    ]);
                }
            }
        }
    }

    // lalu hapus "view_role","view_any_role","create_role","update_role","delete_role","delete_any_role", agar tidak bisa menambahkan role di filamen

    public function down(): void
    {
        //
    }
}
