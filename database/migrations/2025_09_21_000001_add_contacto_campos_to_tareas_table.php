<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tareas', function (Blueprint $table) {
            $table->string('contacto_nombre')->nullable()->after('cliente_id');
            $table->string('contacto_direccion')->nullable()->after('contacto_nombre');
            $table->string('contacto_telefono', 50)->nullable()->after('contacto_direccion');
        });
    }

    public function down(): void {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropColumn(['contacto_nombre','contacto_direccion','contacto_telefono']);
        });
    }
};
