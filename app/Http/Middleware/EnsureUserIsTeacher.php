<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puerta del portal de docentes. No basta con el rol: la cuenta tiene que estar
 * vinculada a un registro de la tabla de docentes, porque ese vínculo es el que
 * dice qué materias son suyas.
 */
class EnsureUserIsTeacher
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            $request->user()?->teacher === null,
            403,
            __('Tu cuenta no está vinculada a un docente. Pídele a un administrador que la vincule.'),
        );

        return $next($request);
    }
}
