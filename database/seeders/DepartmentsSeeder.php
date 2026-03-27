<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentsSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'General',           'description' => 'General enquiries and questions', 'sort_order' => 0],
            ['name' => 'Billing',           'description' => 'Invoices, payments, and account balance', 'sort_order' => 1],
            ['name' => 'Technical Support', 'description' => 'Hosting, servers, and technical issues', 'sort_order' => 2],
            ['name' => 'Sales',             'description' => 'New orders, upgrades, and pricing', 'sort_order' => 3],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['name' => $dept['name']], $dept);
        }
    }
}
