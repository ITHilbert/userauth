<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;

/**
 * Gemeinsame Hilfen der Tests zu `userauth.anmeldung_per` (userauth-anmeldung-per, Dashboard #1311).
 *
 * Die Spalte `benutzername` und das Entfernen des Unique-Index auf `users.email` stellen die Tests selbst her
 * (unabhängig davon, ob die Paket-Migration schon läuft). Die Sitzung wird über ein festes Sitzungs-Cookie
 * getragen: Flash-Daten und offene 2FA überleben so mehrere Anfragen eines Tests.
 */
trait AnmeldungPerHilfen
{
    use NachrichtenAbfangen;

    protected const PW = 'password';

    protected string $sitzungId = '';

    private int $ipZaehler = 20;

    /**
     * Feld der Kennung je Modus (Begriffe der Spec).
     */
    public static function kennungsFeld(string $modus): string
    {
        return ['email' => 'email', 'benutzername' => 'benutzername', 'beides' => 'login'][$modus];
    }

    protected function grundeinstellung(string $modus = 'email'): void
    {
        $this->nachrichtenAbfangen();
        app()->setLocale('de');
        View::addLocation(dirname(__DIR__).'/Fixtures/views');
        config()->set('userauth.redirect_after_login', '/');
        config()->set('userauth.two_factor_enabled', false);
        config()->set('userauth.audit_log_enabled', false);
        config()->set('userauth.password_policy.enabled', false);
        config()->set('userauth.theme', 'tailwind');
        config()->set('userauth.anmeldung_per', $modus);
        $this->neueSitzung();
    }

    protected function modus(mixed $wert): void
    {
        config()->set('userauth.anmeldung_per', $wert);
    }

    protected function neueSitzung(): void
    {
        $this->sitzungId = Str::random(40);
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withCookie((string) config('session.cookie'), $this->sitzungId);
    }

    /**
     * @param  array<string, mixed>  $daten
     */
    protected function senden(string $methode, string $uri, array $daten = []): TestResponse
    {
        $this->app['cookie']->flushQueuedCookies();
        $antwort = $this->{$methode}($uri, $daten);

        $this->sitzungId = session()->getId();
        $this->withCookie((string) config('session.cookie'), $this->sitzungId);

        return $antwort;
    }

    protected function benutzernameSpalteAnlegen(): void
    {
        if (! Schema::hasColumn('users', 'benutzername')) {
            Schema::table('users', function (Blueprint $t): void {
                $t->string('benutzername', 255)->nullable()->unique();
            });
        }
    }

    protected function benutzernameSpalteEntfernen(): void
    {
        if (! Schema::hasColumn('users', 'benutzername')) {
            return;
        }

        foreach (Schema::getIndexes('users') as $index) {
            if (in_array('benutzername', $index['columns'], true)) {
                Schema::table('users', fn (Blueprint $t) => $t->dropIndex($index['name']));
            }
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('benutzername'));
    }

    /**
     * Gemeinsame E-Mails zulassen: Unique-Index auf users.email entfernen, normalen Index setzen (README-Vorgabe).
     */
    protected function emailIndexEntfernen(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->dropUnique('users_email_unique');
            $t->index('email');
        });
    }

    /**
     * Konto mit Benutzername; das Passwort ist `password`, solange keines angegeben wird.
     *
     * @param  array<string, mixed>  $attribute
     */
    protected function konto(string $benutzername, string $email, array $attribute = [], ?string $passwort = null): User
    {
        return User::factory()->create(array_merge([
            'benutzername' => $benutzername,
            'email' => $email,
            'password' => $passwort === null ? User::factory()->make()->password : Hash::make($passwort),
        ], $attribute));
    }

    protected function neueIp(): string
    {
        return '198.51.100.'.(++$this->ipZaehler);
    }

    /**
     * POST /login mit dem Feld der Kennung, wie der Browser (Standard: Formular, JSON bei $json).
     *
     * @param  array<string, mixed>  $extra
     */
    protected function anmelden(string $feld, ?string $kennung, string $passwort = self::PW, array $extra = [], bool $json = false): TestResponse
    {
        $daten = array_merge([$feld => $kennung, 'password' => $passwort], $extra);

        return $this->senden($json ? 'postJson' : 'post', '/login', $daten);
    }

    protected function loginSeite(): string
    {
        return (string) $this->get('/login')->assertOk()->getContent();
    }

    protected function dom(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($dom);
    }

    protected function eingabe(string $html, string $name): ?DOMElement
    {
        $knoten = $this->dom($html)->query('//input[@name="'.$name.'"]');

        return $knoten !== false && $knoten->length > 0 ? $knoten->item(0) : null;
    }

    /**
     * Text des Labels zum Feld (über for=id), leer ohne Label.
     */
    protected function labelText(string $html, string $name): string
    {
        $eingabe = $this->eingabe($html, $name);
        if ($eingabe === null || $eingabe->getAttribute('id') === '') {
            return '';
        }
        $label = $this->dom($html)->query('//label[@for="'.$eingabe->getAttribute('id').'"]');

        return $label !== false && $label->length > 0 ? trim((string) preg_replace('~\s+~u', ' ', $label->item(0)->textContent)) : '';
    }

    /**
     * Felder des Formulars, dessen action auf `$actionEndetMit` endet: alle mit name, nicht disabled; letzter Wert gewinnt.
     *
     * @return array<string, string>
     */
    protected function formularFelder(string $html, string $actionEndetMit): array
    {
        $xpath = $this->dom($html);
        $felder = [];
        foreach ($xpath->query('//form') as $formular) {
            $pfad = (string) parse_url((string) $formular->getAttribute('action'), PHP_URL_PATH);
            if (! str_ends_with($pfad, $actionEndetMit)) {
                continue;
            }
            foreach ($xpath->query('.//input[@name] | .//textarea[@name] | .//select[@name]', $formular) as $feld) {
                if ($feld->hasAttribute('disabled')) {
                    continue;
                }
                $typ = strtolower((string) $feld->getAttribute('type'));
                if (in_array($typ, ['submit', 'button', 'image', 'file'], true)) {
                    continue;
                }
                if (in_array($typ, ['checkbox', 'radio'], true) && ! $feld->hasAttribute('checked')) {
                    continue;
                }
                $felder[$feld->getAttribute('name')] = $feld->nodeName === 'textarea' ? $feld->textContent : (string) $feld->getAttribute('value');
            }
        }

        return $felder;
    }

    protected function auditTabelleBereitstellen(): void
    {
        if (! Schema::hasTable('user_auth_audit_logs')) {
            Schema::create('user_auth_audit_logs', function (Blueprint $t): void {
                $t->bigIncrements('id');
                $t->bigInteger('user_id')->nullable();
                $t->string('email')->nullable();
                $t->string('event');
                $t->string('ip_address')->nullable();
                $t->string('user_agent')->nullable();
                $t->timestamps();
            });
        }
    }

    /**
     * Passwort-Hash der Zeile, direkt aus der Datenbank.
     */
    protected function hashVon(int $id): string
    {
        return (string) User::withTrashed()->whereKey($id)->value('password');
    }
}
