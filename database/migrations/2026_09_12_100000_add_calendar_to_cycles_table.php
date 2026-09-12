<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('previous_cycle_id');
            $table->date('ends_on')->nullable()->after('starts_on');
            // Día de clase de la semana en formato ISO: 1 es lunes y 7 domingo.
            $table->unsignedTinyInteger('class_weekday')->nullable()->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->dropColumn(['starts_on', 'ends_on', 'class_weekday']);
        });
    }
};
