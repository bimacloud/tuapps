<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Permissions
        $permissions = [
            'view_dashboard',
            'view_reports',
            'manage_students',
            'manage_classes',
            'manage_majors',
            'manage_academic_years',
            'manage_payment_types',
            'manage_payment_rates',
            'create_payment',
            'view_payments',
            'request_payment_cancellation',
            'approve_payment_cancellation',
            'request_rate_change',
            'approve_rate_change',
            'view_audit_logs',
            'manage_users',
            'export_reports',
            'manage_cashier',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. Create Roles and Assign Permissions
        $kepsekRole = Role::firstOrCreate(['name' => 'Kepala Sekolah']);
        $kepsekPermissions = [
            'view_dashboard',
            'view_reports',
            'view_payments',
            'approve_payment_cancellation',
            'approve_rate_change',
            'view_audit_logs',
            'export_reports',
            // Kepsek hanya view master data, tidak kelola.
        ];
        $kepsekRole->syncPermissions($kepsekPermissions);

        $bendaharaRole = Role::firstOrCreate(['name' => 'Bendahara']);
        $bendaharaPermissions = [
            'view_dashboard',
            'view_reports',
            'manage_students',
            'manage_classes',
            'manage_majors',
            'manage_academic_years',
            'manage_payment_types',
            'manage_payment_rates',
            'create_payment',
            'view_payments',
            'request_payment_cancellation',
            'request_rate_change',
            'export_reports',
            'manage_cashier',
        ];
        $bendaharaRole->syncPermissions($bendaharaPermissions);

        // 3. Create Users and Assign Roles
        $kepsek = User::firstOrCreate([
            'email' => 'kepsek@smk.com'
        ], [
            'name' => 'Kepala Sekolah',
            'password' => bcrypt('password'),
        ]);
        $kepsek->assignRole($kepsekRole);

        $bendahara = User::firstOrCreate([
            'email' => 'bendahara@smk.com'
        ], [
            'name' => 'Bendahara 1',
            'password' => bcrypt('password'),
        ]);
        $bendahara->assignRole($bendaharaRole);

        $this->call(PaymentMethodSeeder::class);
    }
}
