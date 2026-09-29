<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $matrice = [
            'admin' => [
                'beneficiaires.creer', 'beneficiaires.voir_tous', 'beneficiaires.modifier', 'beneficiaires.archiver',
                'qr.gerer', 'objets.creer', 'objets.gerer_tous',
                'utilisateurs.gerer', 'structures.gerer', 'journal.voir',
            ],
            'agent_terrain' => [
                'beneficiaires.creer', 'beneficiaires.voir_tous', 'beneficiaires.modifier',
                'qr.gerer', 'objets.creer', 'objets.gerer_tous',
            ],
            'structure_sante' => ['medical.voir', 'medical.modifier'],
            'utilisateur' => ['beneficiaires.creer', 'objets.creer'],
        ];

        foreach (array_unique(array_merge(...array_values($matrice))) as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach ($matrice as $nom => $permissions) {
            Role::firstOrCreate(['name' => $nom, 'guard_name' => 'web'])->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
