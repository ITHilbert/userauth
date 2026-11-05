<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Support;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test-Middleware: meldet den Zeitpunkt, an dem die Antwort der Route fertig ist (vor `terminate()` und damit vor
 * den `defer()`-Callbacks). Hängt als letztes Glied der Gruppe `web` und läuft nach dem Controller.
 */
final class AntwortMarke
{
    /** @var (Closure(): void)|null */
    public static ?Closure $beiAntwort = null;

    public function handle(Request $request, Closure $next): Response
    {
        $antwort = $next($request);

        if (self::$beiAntwort !== null) {
            (self::$beiAntwort)();
        }

        return $antwort;
    }
}
