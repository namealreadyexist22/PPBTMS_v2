<?php

namespace Database\Seeders;

use App\Models\Procurement\Office;
use Illuminate\Database\Seeder;

/**
 * The organization's offices: [office no., name, parent office no.].
 * Safe to re-run: matches on office no. and only fills name/parent (and marks the
 * departments that receive budget allocations), so heads and acronyms set in the
 * Offices screen are kept.
 */
class OfficeSeeder extends Seeder
{
    protected array $offices = [
        ['01000', 'OFFICE OF THE SUGAR BOARD', null],

        ['02000', 'INTERNAL AUDIT DEPT - MANAGER III', null],
        ['02010', 'INTERNAL AUDIT DEPT - OPERATIONS AUDIT DIVISION', '02000'],
        ['02020', 'INTERNAL AUDIT DEPT - FINANCE AUDIT DIVISION', '02000'],

        ['03000', 'OFFICE OF THE ADMINISTRATOR', null],
        ['04000', 'LEGAL DEPARTMENT', null],

        ['05000', 'PPSPD - MANAGER III', null],
        ['05010', 'PPSPD - PLANNING, POLICY AND PROGRAMMING DIVISION', '05000'],
        ['05011', 'PPSPD - PPRD - PLANNING AND POLICY RESEARCH SECTION', '05010'],
        ['05012', 'PPSPD - PPRD - MIS SECTION', '05010'],
        ['05020', 'PPSPD - SPECIAL PROJECTS, PROJECT DEVELOPMENT, EVALUATION AND MONITORING DIVISION', '05000'],

        ['06000', 'OFC OF THE DEP. ADMIN - ADMINISTRATION AND FINANCE', null],
        ['06010', 'AFD-LUZON MINDANAO - MANAGER III', '06000'],
        ['06020', 'AFD-LUZON MINDANAO - GENERAL ADMINISTRATIVE DIVISION', '06010'],
        ['06021', 'AFD-LUZON MINDANAO - GENERAL ADMINISTRATIVE DIVISION - HRRS', '06020'],
        ['06022', 'AFD-LUZON MINDANAO - GENERAL ADMINISTRATIVE DIVISION - PPBTMS', '06020'],
        ['06030', 'AFD-LUZON MINDANAO - BUDGET AND TREASURY DIVISION', '06010'],
        ['06040', 'AFD-LUZON MINDANAO - ACCOUNTING DIVISION', '06010'],
        ['06550', 'AFD-VISAYAS - MANAGER III', '06000'],
        ['06560', 'AFD-VISAYAS - GENERAL ADMINISTRATIVE DIVISION', '06550'],
        ['06561', 'AFD-VISAYAS - GENERAL ADMINISTRATIVE DIVISION - HRRS', '06560'],
        ['06562', 'AFD-VISAYAS - GENERAL ADMINISTRATIVE DIVISION - PPBTMS', '06560'],
        ['06570', 'AFD-VISAYAS - FINANCE DIVISION', '06550'],
        ['06571', 'AFD-VISAYAS - FINANCE DIVISION - ACCOUNTING SECTION', '06570'],
        ['06572', 'AFD-VISAYAS - FINANCE DIVISION - BUDGET AND TREASURY SECTION', '06570'],

        ['07000', 'OFC OF THE DEP. ADMIN - RESEARCH, DEVELOPMENT AND EXTENSION', null],
        ['07020', 'RDE-LM - MANAGER III', '07000'],
        ['07010', 'RDE-LM - FSRD', '07020'],
        ['07011', 'RDE-LM - FSRD - OAS', '07010'],
        ['07012', 'RDE-LM - FSRD - ORS', '07010'],
        ['07030', 'RDE-LM - ASSD', '07020'],
        ['07040', 'RDE-LM - AARD', '07020'],
        ['07050', 'RDE-LM - ESD', '07020'],
        ['07560', 'RDE-VIS - MANAGER III', '07000'],
        ['07570', 'RDE-VIS - ASSD', '07560'],
        ['07580', 'RDE-VIS - ARD', '07560'],
        ['07581', 'RDE-VIS - ARD - SOILS LAB/PTCM', '07580'],
        ['07582', 'RDE-VIS - ARD - VIPM', '07580'],
        ['07583', 'RDE-VIS - ARD - BIOTECH SECTION', '07580'],
        ['07590', 'RDE-VIS - ESD', '07560'],

        ['08000', 'OFC OF THE DEP. ADMIN - REGULATIONS', null],
        ['08010', 'RD-LM - MANAGER III', '08000'],
        ['08020', 'RD-LM - STD', '08010'],
        ['08021', 'RD-LM - STD - ISAS', '08020'],
        ['08022', 'RD-LM - STD - EDSS', '08020'],
        ['08030', 'RD-LM - LMD', '08010'],
        ['08040', 'RD-LM - LABSERV DIVISION', '08010'],
        ['08050', 'RD-LM - SRED', '08010'],
        ['08560', 'RD-VIS - MANAGER III', '08000'],
        ['08570', 'RD-VIS - LMD', '08560'],
        ['08580', 'RD-VIS - SRED', '08560'],
        ['08590', 'RD-VIS - LABSERV DIVISION', '08560'],

        ['09000', 'GENDER AND DEVELOPMENT (GAD)', null],
        ['10000', 'SIDA-BFP', null],
        ['11000', 'SIDA-SCP', null],
        ['12000', 'SIDA-HRD', null],
        ['13000', 'SIDA-FMR', null],
        ['14000', 'SIDA-R&D', null],
        ['15000', 'REGIONAL BIDS AND AWARDS COMMITTEE', null],
    ];

    /** Offices that receive a budget allocation, shared by every office under them. */
    protected array $departments = [
        '01000', '02000', '03000', '04000', '05000', '06000', '06010', '06550', '07000', '07020', '07560',
        '08000', '08010', '08560', '09000', '10000', '11000', '12000', '13000', '14000', '15000',
    ];

    public function run(): void
    {
        // Parents are listed before their children, so one pass is enough.
        $ids = [];

        foreach ($this->offices as [$code, $name, $parentCode]) {
            $office = Office::withTrashed()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'parent_id' => $parentCode ? $ids[$parentCode] : null]
            );

            if (in_array($code, $this->departments, true) && ! $office->is_department) {
                $office->update(['is_department' => true]);
            }

            $ids[$code] = $office->id;
        }
    }
}
