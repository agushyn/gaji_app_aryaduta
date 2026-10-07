<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Segregation of Duties Enforcement
    |--------------------------------------------------------------------------
    |
    | When set to true, the creator or submitter of a payroll period or payslip
    | cannot approve their own submission, enforcing internal financial audit
    | compliance and four-eyes principle.
    |
    /*
    |--------------------------------------------------------------------------
    | Official Duty (Uang Dinas) Treatment
    |--------------------------------------------------------------------------
    |
    | When set to false, official duty / PJD is treated as an operational reimbursement
    | that is added to Total Terima Gaji outside Gaji Bruto.
    | When set to true, official duty is included in Gaji Bruto.
    | This guarantees Uang Dinas is never counted twice.
    |
    */
    'official_duty_included_in_gross' => env('PAYROLL_OFFICIAL_DUTY_IN_GROSS', false),

    /*
    | Component codes considered as Official Duty (Uang Dinas / PJD Luar Kota)
    */
    'official_duty_codes' => ['OFFICIAL_DUTY', 'UANG_DINAS', 'PJD', 'PJD_LUAR_KOTA'],
];
