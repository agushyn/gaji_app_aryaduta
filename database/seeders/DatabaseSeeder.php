<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            OrganizationMasterSeeder::class,
            EmployeeSeeder::class,
            PayComponentSeeder::class,
            CompanySettingSeeder::class,
            PayrollPeriodSeeder::class,
            PayslipSeeder::class,
            HolidayCalendarSeeder::class,
            PayrollCalculationConfigSeeder::class,
        ]);
    }
}
