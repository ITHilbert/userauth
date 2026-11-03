<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ITHilbert\UserAuth\Http\Middleware\EnforcePasswordPolicy;
use ITHilbert\UserAuth\Http\Middleware\EnsureRegistrationEnabled;
use ITHilbert\UserAuth\Http\Middleware\hasPermission;
use ITHilbert\UserAuth\Http\Middleware\hasPermissionAnd;
use ITHilbert\UserAuth\Http\Middleware\hasPermissionOr;
use ITHilbert\UserAuth\Http\Middleware\hasRole;
use ITHilbert\UserAuth\Http\Middleware\isAdmin;
use ITHilbert\UserAuth\Http\Middleware\isDev;
use ITHilbert\UserAuth\Http\Middleware\TwoFactorMiddleware;

/**
 * Stellung der userauth-Middlewares in der Middleware-Priorität von Router und HTTP-Kernel.
 *
 * Die Rechte-Middlewares laufen vor dem Route-Model-Binding (`SubstituteBindings`), sonst verrät die Antwort
 * (404 statt Ablehnung), welche IDs existieren. Die Vorprüfungen, die userauth in die Gruppe `web` einträgt
 * (Passwort-Ablauf, Abmelden alter Sitzungen ohne 2FA-Markierung), laufen weiterhin vor der Rechteprüfung.
 *
 * Die Schalter-Middlewares (z. B. Registrierung aus → 404) laufen direkt nach dem Sitzungsstart und damit vor der
 * CSRF-Prüfung der Gruppe `web` (die nicht in der Priorität steht): Eine abgeschaltete Funktion antwortet auf
 * jede Methode mit 404 statt mit 419.
 */
final class MiddlewarePrioritaet
{
    /** @var list<class-string> Middlewares, die userauth in die Gruppe `web` einträgt */
    public const VORPRUEFUNGEN = [EnforcePasswordPolicy::class, TwoFactorMiddleware::class];

    /** @var list<class-string> Middlewares, die eine abgeschaltete Funktion mit 404 beantworten */
    public const SCHALTER = [EnsureRegistrationEnabled::class];

    /** @var list<class-string> */
    public const RECHTE = [hasPermission::class, hasPermissionAnd::class, hasPermissionOr::class, hasRole::class, isAdmin::class, isDev::class];

    /**
     * Liste mit den userauth-Einträgen an ihrer Stelle: die Schalter direkt vor `ShareErrorsFromSession` (fehlt es,
     * direkt nach `StartSession`, fehlt auch das, am Anfang), die Vorprüfungen und danach die Rechte direkt vor
     * `SubstituteBindings` (fehlt es, am Ende). Vorhandene userauth-Einträge werden entfernt und neu gesetzt
     * (kein doppelter Eintrag, auch ein falsch platzierter Host-Eintrag landet an der richtigen Stelle);
     * alle anderen Einträge bleiben in ihrer Reihenfolge.
     *
     * @param  array<int, string>  $prioritaet
     * @return list<string>
     */
    public static function einordnen(array $prioritaet): array
    {
        $eigene = [...self::VORPRUEFUNGEN, ...self::RECHTE];
        $fremde = array_values(array_filter(
            $prioritaet,
            static fn (string $eintrag): bool => ! in_array($eintrag, [...self::SCHALTER, ...$eigene], true),
        ));

        $stelle = array_search(SubstituteBindings::class, $fremde, true);
        array_splice($fremde, $stelle === false ? count($fremde) : $stelle, 0, $eigene);

        array_splice($fremde, self::stelleDerSchalter($fremde), 0, self::SCHALTER);

        return $fremde;
    }

    /**
     * @param  list<string>  $prioritaet
     */
    private static function stelleDerSchalter(array $prioritaet): int
    {
        $fehler = array_search(ShareErrorsFromSession::class, $prioritaet, true);
        if ($fehler !== false) {
            return $fehler;
        }

        $sitzung = array_search(StartSession::class, $prioritaet, true);

        return $sitzung === false ? 0 : $sitzung + 1;
    }

    /**
     * Ordnet die userauth-Einträge in die Priorität des Routers ein und übernimmt das Ergebnis in den Kernel.
     *
     * Grundlage ist die Router-Liste: Jede Änderung über den Kernel landet auch dort, umgekehrt nicht – Pakete,
     * die vor userauth booten, tragen sich bei einem vor dem Boot erzeugten Kernel (`public/index.php`) nur am
     * Router ein. Der Kernel schreibt beim Setzen seine Priorität, Gruppen und Aliase auf den Router zurück;
     * die Gruppen und Aliase des Routers (dort von anderen Paketen ergänzt) werden deshalb wiederhergestellt.
     */
    public static function inRouterUndKernel(Router $router, HttpKernel $kernel): void
    {
        $gruppen = $router->getMiddlewareGroups();
        $aliase = $router->getMiddleware();

        $kernel->setMiddlewarePriority(self::einordnen($router->middlewarePriority));

        foreach ($gruppen as $name => $middlewares) {
            $router->middlewareGroup($name, $middlewares);
        }

        foreach ($aliase as $name => $middleware) {
            $router->aliasMiddleware($name, $middleware);
        }
    }
}
