<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('finance_subcategories')) {
            Schema::create('finance_subcategories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('finance_categories')->onDelete('cascade');
                $table->string('name');
                $table->timestamps();
                $table->unique(['category_id','name'], 'uniq_subcat_per_category');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_subcategories');
    }
};
