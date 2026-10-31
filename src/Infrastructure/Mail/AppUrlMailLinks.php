<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Mail;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
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
        $basis = rtrim(trim((string) $this->config->get('app.url', '')), '/');

        if ($basis === '') {
            Log::warning('userauth: app.url ist nicht gesetzt, Links in Mails entstehen aus der Anfrage. APP_URL auf die öffentliche Adresse setzen.');

            return $this->urls->route($routenName, $parameter);
        }

        return $basis.$this->urls->route($routenName, $parameter, false);
    }
}
