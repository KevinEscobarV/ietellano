<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un semestre cerrado es un semestre terminado: sus notas y su asistencia
     * dejan de ser editables para el docente. La fecha sirve de constancia de
     * cuándo se cerró.
     */
    public function up(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('class_weekday');
        });
    }

    public function down(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });
    }
};
