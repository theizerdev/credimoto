<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pago;
use App\Models\PagoDetalle;

class SyncPagoDetalleClienteId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:pago-detalle-cliente-id 
                           {--dry-run : Show what would be updated without making changes}
                           {--chunk-size=1000 : Number of records to process at a time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize cliente_id in pago_detalles table with the corresponding pago record';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting synchronization of cliente_id in pago_detalles table...');

        $chunkSize = $this->option('chunk-size');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No changes will be made to the database.');
        }

        $totalToUpdate = 0;
        $totalUpdated = 0;

        // Count total records that need updating
        $query = PagoDetalle::leftJoin('pagos', 'pago_detalles.pago_id', '=', 'pagos.id')
            ->whereNotNull('pagos.cliente_id')
            ->whereColumn('pago_detalles.cliente_id', '!=', 'pagos.cliente_id')
            ->orWhereNull('pago_detalles.cliente_id');

        $totalToUpdate = $query->count();

        if ($totalToUpdate === 0) {
            $this->info('No records need to be synchronized.');
            return 0;
        }

        $this->info("Found {$totalToUpdate} records that need synchronization.");

        if ($dryRun) {
            $this->info('These records would be updated:');
        } else {
            $this->info('Updating records...');
        }

        // Process records in chunks
        $query->select('pago_detalles.id', 'pago_detalles.pago_id', 'pago_detalles.cliente_id as detalle_cliente_id', 'pagos.cliente_id as pago_cliente_id')
            ->orderBy('pago_detalles.id')
            ->chunk($chunkSize, function ($records, $page) use ($dryRun, &$totalUpdated) {
                $this->output->write("Processing chunk {$page}... ");

                $updates = 0;

                foreach ($records as $record) {
                    if ($dryRun) {
                        $this->line("  Record ID: {$record->id}, Current: {$record->detalle_cliente_id}, New: {$record->pago_cliente_id}");
                    } else {
                        PagoDetalle::where('id', $record->id)
                            ->update(['cliente_id' => $record->pago_cliente_id]);
                        $updates++;
                    }
                }

                $totalUpdated += $updates;
                
                if ($dryRun) {
                    $this->info("Would update {$updates} records in this chunk.");
                } else {
                    $this->info("Updated {$updates} records in this chunk.");
                }
            });

        if ($dryRun) {
            $this->info("Would have updated a total of {$totalUpdated} records.");
        } else {
            $this->info("Successfully updated {$totalUpdated} records.");
        }

        $this->info('Synchronization completed.');

        return 0;
    }
}