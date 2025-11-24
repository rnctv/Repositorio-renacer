<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // id_externo UNIQUE
            if (!Schema::hasColumn('clientes', 'id_externo')) {
                $table->string('id_externo')->nullable()->after('id');
                try { $table->unique('id_externo', 'clientes_id_externo_unique'); } catch (\Throwable $e) {}
            }
            // columnas fijas básicas
            foreach (['nombre','direccion','movil','plan','estado'] as $col) {
                if (!Schema::hasColumn('clientes', $col)) {
                    $table->string($col)->nullable();
                }
            }
            // extras como JSON si el motor lo permite; si no, longText
            if (!Schema::hasColumn('clientes','extras')) {
                try { $table->json('extras')->nullable(); }
                catch (\Throwable $e) { $table->longText('extras')->nullable(); }
            }
            // índice en estado (si es string todavía)
            try { $table->index('estado'); } catch (\Throwable $e) {}
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes','extras')) { $table->dropColumn('extras'); }
        });
    }
};
