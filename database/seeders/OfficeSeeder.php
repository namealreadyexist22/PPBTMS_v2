<?php

namespace Database\Seeders;

use App\Models\Procurement\Office;
use Illuminate\Database\Seeder;

/**
 * The organization tree: [office no., name, parent office no., type, acronym, budget fund].
 * A department is the top unit of its branch: it receives the budget (COB, or SIDA for the
 * SIDA departments) and can prepare its own PPMP; divisions and sections sit under it.
 *
 * Safe to re-run: matches on office no. and only fills name and parent; type, acronym and
 * budget fund are set when a unit is first created, so changes made in Organization are kept.
 */
class OfficeSeeder extends Seeder
{
    protected array $offices = [
        ['01000', 'OFFICE OF THE SUGAR BOARD', null, 'department', 'OSB', 'regular'],

        ['02000', 'INTERNAL AUDIT DEPARTMENT', null, 'department', 'IAD', 'regular'],
        ['02010', 'INTERNAL AUDIT DEPT - OPERATIONS AUDIT DIVISION', '02000', 'division'],
        ['02020', 'INTERNAL AUDIT DEPT - FINANCE AUDIT DIVISION', '02000', 'division'],

        ['03000', 'OFFICE OF THE ADMINISTRATOR', null, 'department', 'OA', 'regular'],
        ['04000', 'LEGAL DEPARTMENT', null, 'department', 'LEGAL', 'regular'],

        ['05000', 'PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT', null, 'department', 'PPSPD', 'regular'],
        ['05010', 'PLANNING, POLICY AND PROGRAMMING DIVISION', '05000', 'division', 'PPPD'],
        ['05011', 'PLANNING AND POLICY RESEARCH SECTION', '05010', 'section', 'PPRS'],
        ['05012', 'MIS SECTION', '05010', 'section', 'MIS'],
        ['05020', 'SPECIAL PROJECTS, PROJECT DEVELOPMENT, EVALUATION AND MONITORING DIVISION', '05000', 'division', 'SPPDEM'],

        ['06000', 'OFC OF THE DEP. ADMIN - ADMINISTRATION AND FINANCE', null, 'department', 'ODA-AF', 'regular'],
        ['06010', 'AFD-LUZON MINDANAO', '06000', 'department', 'AFD-LM', 'regular'],
        ['06020', 'AFD-LUZON MINDANAO - GENERAL ADMINISTRATIVE DIVISION', '06010', 'division'],
        ['06021', 'AFD-LUZON MINDANAO - GENERAL ADMINISTRATIVE DIVISION - HRRS', '06020', 'section'],
        ['06022', 'AFD-LUZON MINDANAO - GENERAL ADMINISTRATIVE DIVISION - PPBTMS', '06020', 'section'],
        ['06030', 'AFD-LUZON MINDANAO - BUDGET AND TREASURY DIVISION', '06010', 'division'],
        ['06040', 'AFD-LUZON MINDANAO - ACCOUNTING DIVISION', '06010', 'division'],
        ['06550', 'AFD-VISAYAS', '06000', 'department', 'AFD-VIS', 'regular'],
        ['06560', 'AFD-VISAYAS - GENERAL ADMINISTRATIVE DIVISION', '06550', 'division'],
        ['06561', 'AFD-VISAYAS - GENERAL ADMINISTRATIVE DIVISION - HRRS', '06560', 'section'],
        ['06562', 'AFD-VISAYAS - GENERAL ADMINISTRATIVE DIVISION - PPBTMS', '06560', 'section'],
        ['06570', 'AFD-VISAYAS - FINANCE DIVISION', '06550', 'division'],
        ['06571', 'AFD-VISAYAS - FINANCE DIVISION - ACCOUNTING SECTION', '06570', 'section'],
        ['06572', 'AFD-VISAYAS - FINANCE DIVISION - BUDGET AND TREASURY SECTION', '06570', 'section'],

        ['07000', 'OFC OF THE DEP. ADMIN - RESEARCH, DEVELOPMENT AND EXTENSION', null, 'department', 'ODA-RDE', 'regular'],
        ['07020', 'RDE-LM', '07000', 'department', 'RDE-LM', 'regular'],
        ['07010', 'RDE-LM - FSRD', '07020', 'division'],
        ['07011', 'RDE-LM - FSRD - OAS', '07010', 'section'],
        ['07012', 'RDE-LM - FSRD - ORS', '07010', 'section'],
        ['07030', 'RDE-LM - ASSD', '07020', 'division'],
        ['07040', 'RDE-LM - AARD', '07020', 'division'],
        ['07050', 'RDE-LM - ESD', '07020', 'division'],
        ['07560', 'RDE-VIS', '07000', 'department', 'RDE-VIS', 'regular'],
        ['07570', 'RDE-VIS - ASSD', '07560', 'division'],
        ['07580', 'RDE-VIS - ARD', '07560', 'division'],
        ['07581', 'RDE-VIS - ARD - SOILS LAB/PTCM', '07580', 'section'],
        ['07582', 'RDE-VIS - ARD - VIPM', '07580', 'section'],
        ['07583', 'RDE-VIS - ARD - BIOTECH SECTION', '07580', 'section'],
        ['07590', 'RDE-VIS - ESD', '07560', 'division'],

        ['08000', 'OFC OF THE DEP. ADMIN - REGULATIONS', null, 'department', 'ODA-RD', 'regular'],
        ['08010', 'RD-LM', '08000', 'department', 'RD-LM', 'regular'],
        ['08020', 'RD-LM - STD', '08010', 'division'],
        ['08021', 'RD-LM - STD - ISAS', '08020', 'section'],
        ['08022', 'RD-LM - STD - EDSS', '08020', 'section'],
        ['08030', 'RD-LM - LMD', '08010', 'division'],
        ['08040', 'RD-LM - LABSERV DIVISION', '08010', 'division'],
        ['08050', 'RD-LM - SRED', '08010', 'division'],
        ['08560', 'RD-VIS', '08000', 'department', 'RD-VIS', 'regular'],
        ['08570', 'RD-VIS - LMD', '08560', 'division'],
        ['08580', 'RD-VIS - SRED', '08560', 'division'],
        ['08590', 'RD-VIS - LABSERV DIVISION', '08560', 'division'],

        ['09000', 'GENDER AND DEVELOPMENT (GAD)', null, 'department', 'GAD', 'regular'],
        ['10000', 'SIDA-BFP', null, 'department', 'SIDA-BFP', 'sida'],
        ['11000', 'SIDA-SCP', null, 'department', 'SIDA-SCP', 'sida'],
        ['12000', 'SIDA-HRD', null, 'department', 'SIDA-HRD', 'sida'],
        ['13000', 'SIDA-FMR', null, 'department', 'SIDA-FMR', 'sida'],
        ['14000', 'SIDA-R&D', null, 'department', 'SIDA-R&D', 'sida'],
        ['15000', 'REGIONAL BIDS AND AWARDS COMMITTEE', null, 'department', 'RBAC', 'regular'],
    ];

    public function run(): void
    {
        // Parents are listed before their children, so one pass is enough.
        $ids = [];

        foreach ($this->offices as $row) {
            [$code, $name, $parentCode, $type] = $row;

            $office = Office::withTrashed()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'parent_id' => $parentCode ? $ids[$parentCode] : null]
            );

            if ($office->wasRecentlyCreated) {
                $office->update(['type' => $type, 'acronym' => $row[4] ?? null, 'budget_fund' => $row[5] ?? null]);
            }

            $ids[$code] = $office->id;
        }
    }
}
