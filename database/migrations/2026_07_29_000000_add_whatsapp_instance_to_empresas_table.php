<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'whatsapp_api_url')) {
                $table->string('whatsapp_api_url', 255)->nullable()->after('whatsapp_api_key')
                    ->comment('URL Base del API de WhatsApp');
            }
            if (! Schema::hasColumn('empresas', 'whatsapp_instance')) {
                $table->string('whatsapp_instance', 100)->nullable()->after('whatsapp_api_url')
                    ->comment('Nombre de la instancia de WhatsApp en la API');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'whatsapp_instance')) {
                $table->dropColumn('whatsapp_instance');
            }
        });
    }
};
