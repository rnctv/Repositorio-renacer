<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('clientes')) {
            Schema::create('clientes', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->nullable()->index();
                $table->enum('estado', ['ACTIVO','SUSPENDIDO','RETIRADO'])->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('clientes');
    }
};
