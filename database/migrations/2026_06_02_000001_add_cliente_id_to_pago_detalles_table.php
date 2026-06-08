<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pago_detalles', 'cliente_id')) {
            Schema::table('pago_detalles', function (Blueprint $table) {
                $table->foreignId('cliente_id')->nullable()->after('pago_id')->constrained('clientes')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pago_detalles', 'cliente_id')) {
            Schema::table('pago_detalles', function (Blueprint $table) {
                $table->dropForeign(['cliente_id']);
                $table->dropColumn('cliente_id');
            });
        }
    }
};
