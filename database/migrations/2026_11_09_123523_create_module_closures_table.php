<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El cierre pasa del semestre entero a cada módulo. Un ciclo tiene dos
     * módulos y cada uno se cierra por su lado: el Módulo 1 queda en solo
     * lectura mientras el 2 todavía se califica. El semestre está cerrado
     * cuando lo están los dos.
     *
     * La fecha puede estar en el futuro: es un cierre programado, que se
     * cumple solo al llegar la hora.
     */
    public function up(): void
    {
        Schema::create('module_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('period');
            $table->timestamp('closes_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cycle_id', 'period']);
        });

        // Un semestre que ya estaba cerrado queda con sus dos módulos cerrados
        // en la misma fecha: para el docente no cambia nada.
        $now = now();

        $closures = DB::table('cycles')
            ->whereNotNull('closed_at')
            ->get(['id', 'closed_at'])
            ->flatMap(fn (object $cycle) => collect([1, 2])->map(fn (int $period) => [
                'cycle_id' => $cycle->id,
                'period' => $period,
                'closes_at' => $cycle->closed_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

        if ($closures->isNotEmpty()) {
            DB::table('module_closures')->insert($closures->all());
        }

        Schema::table('cycles', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('class_weekday');
        });

        // De vuelta al cierre por semestre: solo cuenta como cerrado el ciclo
        // que ya tenía los dos módulos cerrados.
        DB::table('module_closures')
            ->where('closes_at', '<=', now())
            ->selectRaw('cycle_id, max(closes_at) as closed_at, count(*) as modules')
            ->groupBy('cycle_id')
            ->get()
            ->filter(fn (object $row) => (int) $row->modules >= 2)
            ->each(fn (object $row) => DB::table('cycles')->where('id', $row->cycle_id)->update(['closed_at' => $row->closed_at]));

        Schema::dropIfExists('module_closures');
    }
};
