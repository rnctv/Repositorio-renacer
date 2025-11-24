<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('finance_transactions', 'payment_method_id')) {
                $table->foreignId('payment_method_id')
                    ->nullable()
                    ->after('category_id')
                    ->constrained('finance_payment_methods');
            }
            if (!Schema::hasColumn('finance_transactions', 'modality')) {
                $table->string('modality')->nullable()->after('payment_method_id');
            }
            if (!Schema::hasColumn('finance_transactions', 'worker_name')) {
                $table->string('worker_name')->nullable()->after('modality');
            }
            if (!Schema::hasColumn('finance_transactions', 'vehicle')) {
                $table->string('vehicle')->nullable()->after('worker_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('finance_transactions', 'payment_method_id')) {
                $table->dropConstrainedForeignId('payment_method_id');
            }
            foreach (['modality','worker_name','vehicle'] as $col) {
                if (Schema::hasColumn('finance_transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
