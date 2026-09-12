<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El permiso temporal que un administrador le da a un docente para volver
     * a tocar un semestre cerrado. Sin curso vale para todo el ciclo; con
     * curso, solo para esa materia.
     */
    public function up(): void
    {
        Schema::create('edit_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('closes_at');
            $table->string('note')->nullable();
            // Quién la autorizó. Es lo primero que se pregunta cuando una nota
            // cambia después del cierre.
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cycle_id', 'closes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edit_windows');
    }
};
