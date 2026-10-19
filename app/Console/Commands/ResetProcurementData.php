<?php

namespace App\Console\Commands;

use App\Services\Procurement\PpmpAttachmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Development only: empties the procurement records (PPMPs, Division PPMPs, APPs, budgets, PR / JRs,
 * RFQs, AOQs, their attachments, notifications and the activity log) and keeps everything needed to log in
 * and start again: users, roles and permissions, User Access overrides, offices, menus, settings
 * (Signatories & BAC included) and the lookups (modes, units, fund sources, item categories).
 */
class ResetProcurementData extends Command
{
    protected $signature = 'procurement:reset
                            {--catalog : Also empty the Standard Items catalog}
                            {--force : Do not ask for confirmation}';

    protected $description = 'Empty the procurement data but keep users, access, offices, menus, settings and lookups';

    /** Children first. */
    protected const TABLES = [
        'aoq_quotes', 'aoq_suppliers', 'aoqs',
        'rfq_items', 'rfqs',
        'purchase_request_items', 'purchase_requests',
        'app_item_ppmp_item', 'app_items', 'annual_procurement_plans',
        'division_ppmp_ppmp', 'division_ppmps',
        'ppmp_item_attachments', 'ppmp_item_distributions', 'ppmp_items', 'ppmp_paps', 'ppmps',
        'document_signatories',
        'budget_allocation_changes', 'budget_allocations',
        'notifications', 'activity_log',
    ];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('The application is in production. Run with --force if you really mean it.');

            return self::FAILURE;
        }

        $tables = array_values(array_filter(
            array_merge(self::TABLES, $this->option('catalog') ? ['items'] : []),
            fn ($table) => Schema::hasTable($table)
        ));

        $this->table(['Table', 'Rows'], array_map(fn ($t) => [$t, DB::table($t)->count()], $tables));
        $this->line('Kept: users, roles / permissions, User Access overrides, offices, menus, settings, lookups'
            . ($this->option('catalog') ? '.' : ', Standard Items.'));

        if (! $this->option('force') && ! $this->confirm('Empty these tables? This cannot be undone.')) {
            return self::SUCCESS;
        }

        // Attached PDFs: the files, then the rows
        $paths = Schema::hasTable('ppmp_item_attachments') ? DB::table('ppmp_item_attachments')->pluck('path')->unique() : collect();
        $disk = Storage::disk(PpmpAttachmentService::DISK);
        $paths->each(fn ($path) => $disk->delete($path));
        $disk->deleteDirectory('ppmp-attachments');

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $table) {
                DB::table($table)->truncate();   // also resets the ids
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info('Procurement data emptied (' . count($tables) . ' tables, ' . $paths->count() . ' attached files).');

        return self::SUCCESS;
    }
}
