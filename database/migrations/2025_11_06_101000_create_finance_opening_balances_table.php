<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('year');
            $table->unsignedTinyInteger('month'); // 1..12
            $table->foreignId('payment_method_id')->constrained('finance_payment_methods');
            $table->decimal('amount', 14, 2)->default(0);
            $table->enum('source', ['manual', 'carryover'])->default('manual');
            $table->timestamps();
            $table->unique(['year', 'month', 'payment_method_id'], 'uniq_opening_per_method_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_opening_balances');
    }
};
