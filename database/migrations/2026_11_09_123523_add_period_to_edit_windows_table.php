<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una ventana también puede abrir un módulo entero de un ciclo, no solo
     * una materia o el semestre completo. Vacío significa los dos módulos.
     */
    public function up(): void
    {
        Schema::table('edit_windows', function (Blueprint $table) {
            $table->unsignedTinyInteger('period')->nullable()->after('course_id');
        });
    }

    public function down(): void
    {
        Schema::table('edit_windows', function (Blueprint $table) {
            $table->dropColumn('period');
        });
    }
};
