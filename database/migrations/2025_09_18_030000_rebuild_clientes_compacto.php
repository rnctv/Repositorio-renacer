<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('clientes')) {
            $suffix = date('YmdHis');
            $legacy = 'clientes_legacy_' . $suffix;
            Schema::rename('clientes', $legacy);
        }

        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('id_externo')->nullable();
            $table->string('nombre')->nullable();
            $table->string('direccion')->nullable();
            $table->string('movil')->nullable();
            $table->string('plan')->nullable();
            try { $table->enum('estado', ['ACTIVO','SUSPENDIDO','RETIRADO'])->nullable()->index(); }
            catch (\Throwable $e) { $table->string('estado')->nullable()->index(); }
            try { $table->json('extras')->nullable(); }
            catch (\Throwable $e) { $table->longText('extras')->nullable(); }
            $table->timestamps();
            try { $table->unique('id_externo', 'clientes_id_externo_unique'); } catch (\Throwable $e) {}
        });

        try { DB::statement('ALTER TABLE clientes ROW_FORMAT=DYNAMIC'); } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
