<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un día de clase del ciclo. Que exista la fila significa que ese día
        // hubo clase: un festivo se resuelve borrándola.
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['cycle_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
