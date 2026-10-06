<?php

namespace Database\Seeders;

use App\Models\Procurement\Office;
use Illuminate\Database\Seeder;

/**
 * The organization tree: [key, office no., name, parent key, type, acronym, budget fund].
 * A department receives the budget (COB, or SIDA for the SIDA departments). One with divisions
 * has no office number: its manager's PPMP is prepared by its "Office of the Manager" division,
 * which carries the number (e.g. 05000). Single-unit departments (e.g. LEGAL) prepare their own.
 *
 * Safe to re-run: matches on office no. (or, for departments without one, their acronym) and
 * only fills name and parent; type, acronym and budget fund are set when a unit is first
 * created, so changes made in Organization are kept.
 */
class OfficeSeeder extends Seeder
{
    protected array $offices = [
        ['01000', '01000', 'OFFICE OF THE SUGAR BOARD', null, 'department', 'OSB', 'regular'],

        ['IAD', null, 'INTERNAL AUDIT DEPARTMENT', null, 'department', 'IAD', 'regular'],
        ['02000', '02000', 'OFFICE OF THE MANAGER', 'IAD', 'division', 'IAD-OM'],
        ['02010', '02010', 'INTERNAL AUDIT DEPT - OPERATIONS AUDIT DIVISION', 'IAD', 'division'],
        ['02020', '02020', 'INTERNAL AUDIT DEPT - FINANCE AUDIT DIVISION', 'IAD', 'division'],

        ['03000', '03000', 'OFFICE OF THE ADMINISTRATOR', null, 'department', 'OA', 'regular'],
        ['04000', '04000', 'LEGAL DEPARTMENT', null, 'department', 'LEGAL', 'regular'],

        ['PPSPD', null, 'PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT', null, 'department', 'PPSPD', 'regular'],
        ['05000', '05000', 'OFFICE OF THE MANAGER', 'PPSPD', 'division', 'PPSPD-OM'],
        ['05010', '05010', 'PLANNING, POLICY AND PROGRAMMING DIVISION', 'PPSPD', 'division', 'PPPD'],
        ['05011', '05011', 'PLANNING AND POLICY RESEARCH SECTION', '05010', 'section', 'PPRS'],
        ['05012', '05012', 'MIS SECTION', '05010', 'section', 'MIS'],
        ['05020', '05020', 'SPECIAL PROJECTS, PROJECT DEVELOPMENT, EVALUATION AND MONITORING DIVISION', 'PPSPD', 'division', 'SPPDEM'],

        ['06000', '06000', 'OFC OF THE DEP. ADMIN - ADMINISTRATION AND FINANCE', null, 'department', 'ODA-AF', 'regular'],
        ['AFD-LM', null, 'AFD-LUZON MINDANAO', '06000', 'department', 'AFD-LM', 'regular'],
        ['06010', '06010', 'OFFICE OF THE MANAGER', 'AFD-LM', 'division', 'AFD-LM-OM'],
        ['06020', '06020', 'AFD-LUZON MINDANAO - GENERAL ADMINISTRATIVE DIVISION', 'AFD-LM', 'division'],
        ['06021', '06021', 'AFD-LUZON MINDANAO - GENERAL ADMINISTRATIVE DIVISION - HRRS', '06020', 'section'],
        ['06022', '06022', 'AFD-LUZON MINDANAO - GENERAL ADMINISTRATIVE DIVISION - PPBTMS', '06020', 'section'],
        ['06030', '06030', 'AFD-LUZON MINDANAO - BUDGET AND TREASURY DIVISION', 'AFD-LM', 'division'],
        ['06040', '06040', 'AFD-LUZON MINDANAO - ACCOUNTING DIVISION', 'AFD-LM', 'division'],
        ['AFD-VIS', null, 'AFD-VISAYAS', '06000', 'department', 'AFD-VIS', 'regular'],
        ['06550', '06550', 'OFFICE OF THE MANAGER', 'AFD-VIS', 'division', 'AFD-VIS-OM'],
        ['06560', '06560', 'AFD-VISAYAS - GENERAL ADMINISTRATIVE DIVISION', 'AFD-VIS', 'division'],
        ['06561', '06561', 'AFD-VISAYAS - GENERAL ADMINISTRATIVE DIVISION - HRRS', '06560', 'section'],
        ['06562', '06562', 'AFD-VISAYAS - GENERAL ADMINISTRATIVE DIVISION - PPBTMS', '06560', 'section'],
        ['06570', '06570', 'AFD-VISAYAS - FINANCE DIVISION', 'AFD-VIS', 'division'],
        ['06571', '06571', 'AFD-VISAYAS - FINANCE DIVISION - ACCOUNTING SECTION', '06570', 'section'],
        ['06572', '06572', 'AFD-VISAYAS - FINANCE DIVISION - BUDGET AND TREASURY SECTION', '06570', 'section'],

        ['07000', '07000', 'OFC OF THE DEP. ADMIN - RESEARCH, DEVELOPMENT AND EXTENSION', null, 'department', 'ODA-RDE', 'regular'],
        ['RDE-LM', null, 'RDE-LM', '07000', 'department', 'RDE-LM', 'regular'],
        ['07020', '07020', 'OFFICE OF THE MANAGER', 'RDE-LM', 'division', 'RDE-LM-OM'],
        ['07010', '07010', 'RDE-LM - FSRD', 'RDE-LM', 'division'],
        ['07011', '07011', 'RDE-LM - FSRD - OAS', '07010', 'section'],
        ['07012', '07012', 'RDE-LM - FSRD - ORS', '07010', 'section'],
        ['07030', '07030', 'RDE-LM - ASSD', 'RDE-LM', 'division'],
        ['07040', '07040', 'RDE-LM - AARD', 'RDE-LM', 'division'],
        ['07050', '07050', 'RDE-LM - ESD', 'RDE-LM', 'division'],
        ['RDE-VIS', null, 'RDE-VIS', '07000', 'department', 'RDE-VIS', 'regular'],
        ['07560', '07560', 'OFFICE OF THE MANAGER', 'RDE-VIS', 'division', 'RDE-VIS-OM'],
        ['07570', '07570', 'RDE-VIS - ASSD', 'RDE-VIS', 'division'],
        ['07580', '07580', 'RDE-VIS - ARD', 'RDE-VIS', 'division'],
        ['07581', '07581', 'RDE-VIS - ARD - SOILS LAB/PTCM', '07580', 'section'],
        ['07582', '07582', 'RDE-VIS - ARD - VIPM', '07580', 'section'],
        ['07583', '07583', 'RDE-VIS - ARD - BIOTECH SECTION', '07580', 'section'],
        ['07590', '07590', 'RDE-VIS - ESD', 'RDE-VIS', 'division'],

        ['08000', '08000', 'OFC OF THE DEP. ADMIN - REGULATIONS', null, 'department', 'ODA-RD', 'regular'],
        ['RD-LM', null, 'RD-LM', '08000', 'department', 'RD-LM', 'regular'],
        ['08010', '08010', 'OFFICE OF THE MANAGER', 'RD-LM', 'division', 'RD-LM-OM'],
        ['08020', '08020', 'RD-LM - STD', 'RD-LM', 'division'],
        ['08021', '08021', 'RD-LM - STD - ISAS', '08020', 'section'],
        ['08022', '08022', 'RD-LM - STD - EDSS', '08020', 'section'],
        ['08030', '08030', 'RD-LM - LMD', 'RD-LM', 'division'],
        ['08040', '08040', 'RD-LM - LABSERV DIVISION', 'RD-LM', 'division'],
        ['08050', '08050', 'RD-LM - SRED', 'RD-LM', 'division'],
        ['RD-VIS', null, 'RD-VIS', '08000', 'department', 'RD-VIS', 'regular'],
        ['08560', '08560', 'OFFICE OF THE MANAGER', 'RD-VIS', 'division', 'RD-VIS-OM'],
        ['08570', '08570', 'RD-VIS - LMD', 'RD-VIS', 'division'],
        ['08580', '08580', 'RD-VIS - SRED', 'RD-VIS', 'division'],
        ['08590', '08590', 'RD-VIS - LABSERV DIVISION', 'RD-VIS', 'division'],

        ['09000', '09000', 'GENDER AND DEVELOPMENT (GAD)', null, 'department', 'GAD', 'regular'],
        ['10000', '10000', 'SIDA-BFP', null, 'department', 'SIDA-BFP', 'sida'],
        ['11000', '11000', 'SIDA-SCP', null, 'department', 'SIDA-SCP', 'sida'],
        ['12000', '12000', 'SIDA-HRD', null, 'department', 'SIDA-HRD', 'sida'],
        ['13000', '13000', 'SIDA-FMR', null, 'department', 'SIDA-FMR', 'sida'],
        ['14000', '14000', 'SIDA-R&D', null, 'department', 'SIDA-R&D', 'sida'],
        ['15000', '15000', 'REGIONAL BIDS AND AWARDS COMMITTEE', null, 'department', 'RBAC', 'regular'],
    ];

    public function run(): void
    {
        // Parents are listed before their children, so one pass is enough.
        $ids = [];

        foreach ($this->offices as $row) {
            [$key, $code, $name, $parentKey, $type] = $row;

            $office = $code
                ? Office::withTrashed()->firstOrNew(['code' => $code])
                : Office::withTrashed()->firstOrNew(['code' => null, 'type' => 'department', 'acronym' => $row[5]]);

            $isNew = ! $office->exists;
            $office->fill(['name' => $name, 'parent_id' => $parentKey ? $ids[$parentKey] : null]);

            if ($isNew) {
                $office->fill(['type' => $type, 'acronym' => $row[5] ?? null, 'budget_fund' => $row[6] ?? null]);
            }

            $office->save();
            $ids[$key] = $office->id;
        }
    }
}
