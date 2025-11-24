<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarea_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('tareas')->onDelete('cascade');
            $table->string('accion'); // created, moved, updated, etc.
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo')->nullable();
            $table->date('fecha_anterior')->nullable();
            $table->date('fecha_nueva')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarea_logs');
    }
};
