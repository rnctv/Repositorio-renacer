<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_month_openings')) {
            Schema::drop('finance_month_openings');
        }
    }

    public function down(): void
    {
        Schema::create('finance_month_openings', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->integer('month');
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('source', 20)->default('manual');
            $table->timestamps();
            $table->unique(['year','month','payment_method_id'], 'uniq_fin_month_openings');
        });
    }
};
