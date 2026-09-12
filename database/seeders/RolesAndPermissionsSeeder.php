<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // --- Permissions ---
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Roles & Permissions
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',

            // Dashboard
            'dashboard.view',

            // Portal de docentes: calificar únicamente los cursos propios.
            'grades.own.edit',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // --- Roles ---
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $editor = Role::firstOrCreate(['name' => 'editor']);
        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $teacher = Role::firstOrCreate(['name' => 'docente']);

        // super-admin gets all permissions via gate bypass (see AppServiceProvider)
        // admin gets all permissions except deleting roles
        $admin->syncPermissions(Permission::whereNotIn('name', ['roles.delete'])->get());

        // editor can view users and dashboard
        $editor->syncPermissions([
            'dashboard.view',
            'users.view',
        ]);

        // viewer can only view the dashboard
        $viewer->syncPermissions(['dashboard.view']);

        // El docente solo entra a su portal: sus materias y las notas de ellas.
        // Qué materias son suyas lo decide CoursePolicy, no este permiso.
        $teacher->syncPermissions(['grades.own.edit']);

        // --- Superadmin user ---
        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $superAdminUser->assignRole($superAdmin);
    }
}
