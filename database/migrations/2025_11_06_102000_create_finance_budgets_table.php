<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('category_id')->constrained('finance_categories');
            $table->decimal('amount', 14, 2);
            $table->string('worker_name')->nullable(); // Para sueldos por trabajador
            $table->timestamps();
            $table->unique(['year','month','category_id','worker_name'], 'uniq_budget_per_cat_worker_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_budgets');
    }
};
