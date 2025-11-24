<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id')->nullable()->index();
            $table->string('tipo', 30); // ASISTENCIA | INSTALACION | OTRO (libre)
            $table->string('titulo', 180)->nullable();
            $table->dateTime('fecha_inicio')->index();
            $table->dateTime('fecha_fin')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente | hecho | cancelado (a futuro)
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->foreign('cliente_id')->references('id')->on('clientes')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('actividades');
    }
};
