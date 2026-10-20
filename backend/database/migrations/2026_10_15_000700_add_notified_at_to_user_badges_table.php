<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_badges', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('awarded_at');
        });

        // Marcar las insignias existentes como ya notificadas para que no aparezcan repetidas a usuarios existentes
        DB::table('user_badges')->whereNull('notified_at')->update([
            'notified_at' => DB::raw('awarded_at')
        ]);
    }

    public function down(): void
    {
        Schema::table('user_badges', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
