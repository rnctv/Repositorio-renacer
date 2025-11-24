<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            // DECIMAL(10,7) es estándar para lat/lng
            $table->decimal('coord_lat', 10, 7)->nullable()->after('contacto_telefono');
            $table->decimal('coord_lng', 10, 7)->nullable()->after('coord_lat');
            $table->index(['coord_lat', 'coord_lng'], 'tareas_coord_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropIndex('tareas_coord_idx');
            $table->dropColumn(['coord_lat', 'coord_lng']);
        });
    }
};
