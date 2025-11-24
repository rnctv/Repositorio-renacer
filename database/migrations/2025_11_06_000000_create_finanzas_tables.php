<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['ingreso','egreso'])->index();
            $table->string('color', 12)->nullable();
            $table->timestamps();
        });

        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->enum('type', ['ingreso','egreso'])->index();
            $table->decimal('amount', 14, 2);
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
            $table->unsignedBigInteger('cliente_id')->nullable()->index(); // opcional: referencia a clientes importados
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['date','type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_transactions');
        Schema::dropIfExists('finance_categories');
    }
};
