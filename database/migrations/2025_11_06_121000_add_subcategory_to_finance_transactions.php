<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('finance_transactions','subcategory_id')) {
            Schema::table('finance_transactions', function (Blueprint $table) {
                $table->foreignId('subcategory_id')->nullable()->after('category_id')->constrained('finance_subcategories');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('finance_transactions','subcategory_id')) {
            Schema::table('finance_transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('subcategory_id');
            });
        }
    }
};
