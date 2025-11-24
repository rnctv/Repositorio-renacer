<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_month_openings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');      // 2000..2100
            $table->unsignedTinyInteger('month');      // 1..12
            $table->unsignedBigInteger('payment_method_id')->nullable(); // opcional (medio asociado)
            $table->decimal('amount', 15, 2)->default(0); // soporte decimales
            $table->string('source', 20)->default('manual'); // manual|carryover
            $table->timestamps();

            $table->unique(['year','month','payment_method_id'], 'uniq_fin_month_openings');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_month_openings');
    }
};
