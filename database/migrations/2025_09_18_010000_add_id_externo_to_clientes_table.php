<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'id_externo')) {
                $table->string('id_externo')->nullable()->after('id');
                // Índice único (permite múltiples NULL en MariaDB)
                try { $table->unique('id_externo', 'clientes_id_externo_unique'); } catch (\Throwable $e) {}
            } else {
                // Asegurar índice único si no existe (mejor esfuerzo)
                try { $table->unique('id_externo', 'clientes_id_externo_unique'); } catch (\Throwable $e) {}
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            try { $table->dropUnique('clientes_id_externo_unique'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('clientes', 'id_externo')) {
                $table->dropColumn('id_externo');
            }
        });
    }
};
