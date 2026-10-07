<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class CompanySettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'company_name' => 'CV. BINA PUSTAKA',
            'company_address' => 'Jl. M. Nasir, Bendungan Kec. Cilodong - Kel. Cilodong, Depok.',
            'signatory_personalia_name' => 'Singgih Sugiarto',
            'signatory_personalia_title' => 'Personalia',
            'signatory_reliever_name' => 'Sheren Regiana',
            'signatory_reliever_title' => 'Yang Menyerahkan',
            'default_working_days' => '25',
            'official_duty_included_in_gross' => '0',
        ];

        foreach ($settings as $key => $value) {
            CompanySetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
