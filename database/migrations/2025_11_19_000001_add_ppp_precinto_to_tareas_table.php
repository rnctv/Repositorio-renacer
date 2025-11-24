<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            if (!Schema::hasColumn('tareas', 'user_ppp_hotspot')) {
                $table->string('user_ppp_hotspot', 120)->nullable()->after('cliente_id');
            }
            if (!Schema::hasColumn('tareas', 'precinto')) {
                $table->string('precinto', 60)->nullable()->after('user_ppp_hotspot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            if (Schema::hasColumn('tareas', 'precinto')) {
                $table->dropColumn('precinto');
            }
            if (Schema::hasColumn('tareas', 'user_ppp_hotspot')) {
                $table->dropColumn('user_ppp_hotspot');
            }
        });
    }
};
