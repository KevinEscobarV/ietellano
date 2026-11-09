<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los tutoriales que la persona ya terminó o saltó. Se guarda en la cuenta
     * y no en el navegador para que no vuelvan a aparecer al cambiar de equipo,
     * que es lo normal en una sala de profesores.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('tutorials_seen')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tutorials_seen');
        });
    }
};
