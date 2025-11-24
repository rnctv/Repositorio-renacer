<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('clientes', function (Blueprint $t) {
            // Identificador fijo para upsert
            if (!Schema::hasColumn('clientes','id_externo')) {
                $t->string('id_externo')->nullable()->after('id');
                try { $t->unique('id_externo','clientes_id_externo_unique'); } catch (\Throwable $e) {}
            }

            // Columnas seleccionadas
            $cols = [
                'nombre','direccion','dia_pago','correo','telefono','plan','movil','instalado',
                'cedula','user_ppp_hotspot','coordenadas','status','precinto','valor_plan','el_plan','fecha_pago'
            ];
            foreach ($cols as $c) {
                if (!Schema::hasColumn('clientes',$c)) $t->string($c)->nullable();
            }

            // Estado derivado (opcional; índice para filtros)
            if (!Schema::hasColumn('clientes','estado')) {
                try { $t->enum('estado',['ACTIVO','SUSPENDIDO','RETIRADO'])->nullable()->index(); }
                catch (\Throwable $e) { $t->string('estado')->nullable()->index(); }
            }

            // Por si aún existiera 'extras', se puede comentar la siguiente línea si prefieres mantenerla:
            if (Schema::hasColumn('clientes','extras')) { $t->dropColumn('extras'); }
        });
    }
    public function down(): void {}
};
