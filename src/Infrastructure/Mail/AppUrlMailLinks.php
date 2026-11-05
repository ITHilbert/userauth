<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Mail;

use DateTimeInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Log;
use ITHilbert\UserAuth\Application\Contracts\MailLinks;

/**
 * Baut Mail-Links aus `app.url` plus relativem Routenpfad. Ist `app.url` leer, entsteht der Link wie bisher
 * aus der Anfrage (damit der Versand nicht ausfällt) und es wird gewarnt.
 */
final class AppUrlMailLinks implements MailLinks
{
    public function __construct(
        private readonly UrlGenerator $urls,
        private readonly Repository $config,
    ) {}

    public function fuerRoute(string $routenName, array|string $parameter = []): string
    {
        $basis = $this->basis();

        if ($basis === '') {
            return $this->urls->route($routenName, $parameter);
        }

        return $basis.$this->urls->route($routenName, $parameter, false);
    }

    /**
     * Signiert wird mit einer Kopie des URL-Generators, deren Ursprung `app.url` ist: Der globale Generator
     * (Ursprung der Anfrage) bleibt unverändert.
     */
    public function signiertFuerRoute(string $routenName, array $parameter, DateTimeInterface $ablauf): string
    {
        $basis = $this->basis();

        if ($basis === '') {
            return $this->urls->temporarySignedRoute($routenName, $ablauf, $parameter);
        }

        $generator = clone $this->urls;
        $generator->setRequest(Request::create($basis.'/'));
        $generator->useOrigin($basis);
        $generator->forceScheme((string) parse_url($basis, PHP_URL_SCHEME) ?: null);

        return $generator->temporarySignedRoute($routenName, $ablauf, $parameter);
    }

    private function basis(): string
    {
        $basis = rtrim(trim((string) $this->config->get('app.url', '')), '/');

        if ($basis === '') {
            Log::warning('userauth: app.url ist nicht gesetzt, Links in Mails entstehen aus der Anfrage. APP_URL auf die öffentliche Adresse setzen.');
        }

        return $basis;
    }
}
