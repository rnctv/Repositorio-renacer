<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // EFECTIVO, TARJETA, CUENTA EMPRESA, TRANSFERENCIA
            $table->boolean('is_cash')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payment_methods');
    }
};
