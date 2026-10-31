<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use ITHilbert\LaravelKit\Helpers\Breadcrumb;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Verwaltungs-Views (Benutzer, Rollen, Rechte, Benutzerbild) auf Kit-Blade-Components statt Vue-Tags.
 * Abgedeckt: AK1–AK4, AK5 (Insel und Formular), AK7–AK9 und alle Randfälle (userauth-admin-views, Dashboard #115).
 * AK5 (HButton) und AK6 stehen in den Kit-Tests (T1).
 *
 * Alle Seiten laufen mit der Kit-Provider-Registrierung der Basis-TestCase. Das Testlayout gibt zusätzlich
 * `@yield('js')` aus, damit die DataTable-Skripte (AK8) im HTML stehen.
 */
final class AdminViewsKitTest extends TestCase
{
    use RefreshDatabase;

    private const THEMES = ['tailwind', 'bootstrap5'];

    private const SEITEN = [
        'user.index', 'user.create', 'user.edit', 'user.icon',
        'role.index', 'role.create', 'role.edit',
        'permission.index', 'permission.create', 'permission.edit',
    ];

    private const VIEWS = [
        'user/index', 'user/create', 'user/edit', 'user/icon',
        'role/index', 'role/create', 'role/edit',
        'permission/index', 'permission/create', 'permission/edit',
    ];

    private const VUE_TAGS = [
        'card-main-header', 'card-main', 'card-body', 'card-footer', 'hform',
        'input-text', 'input-email', 'input-password', 'input-euro', 'input-date', 'input-file-img', 'input-hidden', 'input-number',
        'text-area', 'checkbox', 'combobox', 'tabs-header', 'tab-header', 'tabs-body', 'tab-body', 'dialog-delete',
        'button-save', 'button-back', 'button-create', 'button-delete', 'button-edit', 'button-cancel', 'button-show', 'button-submit',
        'breadcrumb',
    ];

    /** Konfigurationsschlüssel `userauth.user.*` → Feldname im Formular. */
    private const CONFIG_FELDER = [
        'anrede' => 'anrede_id', 'title' => 'title', 'firstname' => 'firstname', 'lastname' => 'lastname',
        'smallname' => 'smallname', 'name' => 'name', 'role' => 'role_id', 'email' => 'email',
        'private_email' => 'private_email', 'street' => 'street', 'postcode' => 'postcode', 'city' => 'city',
        'country' => 'country', 'signature_rule' => 'signature_rule_id', 'ustid' => 'ustid', 'phone' => 'phone',
        'phone2' => 'phone2', 'mobile' => 'mobile', 'fax' => 'fax', 'skype' => 'skype', 'hourly_rate' => 'hourly_rate',
        'birthday' => 'birthday', 'comment' => 'comment', 'image' => 'image',
    ];

    private const ROLLE_EDITOR = 4;

    /** @var array<string, int> */
    private array $rechte = [];

    private User $admin;

    private User $ziel;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        View::getFinder()->prependLocation(__DIR__.'/../Fixtures/adminviews');
        config(['laravelkit.theme' => 'tailwind']);

        $jetzt = now();
        foreach ([[1, 'dev', 'Dev'], [2, 'admin', 'Admin'], [3, 'user', 'User'], [self::ROLLE_EDITOR, 'editor', 'Editor']] as [$id, $name, $anzeige]) {
            DB::table('roles')->insert(['id' => $id, 'role' => $name, 'role_display' => $anzeige, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        }

        $this->gruppe(1, 'user', 'Gruppe Nutzer', 0, ['create', 'read', 'edit', 'delete']);
        $this->gruppe(2, 'role', 'Gruppe Rollen', 0, ['create', 'read', 'edit', 'delete']);
        $this->gruppe(3, 'extras', 'Gruppe Extras', 1, ['a', 'b']);

        DB::table('role_permission')->insert([
            ['role_id' => self::ROLLE_EDITOR, 'permission_id' => $this->rechte['user_read']],
            ['role_id' => self::ROLLE_EDITOR, 'permission_id' => $this->rechte['extras_a']],
        ]);

        $this->admin = User::factory()->create(['role_id' => 1, 'name' => 'Admin Nutzer', 'email' => 'admin@example.test']);
        $this->ziel = User::factory()->create(['role_id' => self::ROLLE_EDITOR, 'name' => 'Ziel Nutzer', 'email' => 'ziel@example.test']);
    }

    /**
     * @param  list<string>  $arten  crud-Arten bzw. Namen der Einzelrechte bei is_group 1
     */
    private function gruppe(int $id, string $name, string $anzeige, int $istGruppe, array $arten): void
    {
        $jetzt = now();
        DB::table('permissions_groups')->insert([
            'id' => $id, 'group_name' => $name, 'group_display' => $anzeige, 'is_group' => $istGruppe,
            'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);

        foreach ($arten as $art) {
            $recht = $name.'_'.$art;
            $rechtId = DB::table('permissions')->insertGetId([
                'permission' => $recht, 'permission_display' => 'Recht '.$recht,
                'group_id' => $id, 'crud' => $istGruppe === 1 ? 'single' : $art, 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
            $this->rechte[$recht] = $rechtId;
        }
    }

    // ------------------------------------------------------------------ Datenanbieter

    /** @return array<string, array{0: string}> */
    public static function views(): array
    {
        $faelle = [];
        foreach (self::VIEWS as $view) {
            $faelle[$view] = [$view];
        }

        return $faelle;
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function themesMitSeiten(): array
    {
        $faelle = [];
        foreach (self::THEMES as $theme) {
            foreach (self::SEITEN as $seite) {
                $faelle[$theme.' '.$seite] = [$theme, $seite];
            }
        }

        return $faelle;
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function themesMitFormularSeiten(): array
    {
        $faelle = [];
        foreach (self::THEMES as $theme) {
            foreach (['user.create', 'user.edit', 'user.icon', 'role.create', 'role.edit', 'permission.create', 'permission.edit'] as $seite) {
                $faelle[$theme.' '.$seite] = [$theme, $seite];
            }
        }

        return $faelle;
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function themesMitRollenSeiten(): array
    {
        $faelle = [];
        foreach (self::THEMES as $theme) {
            foreach (['role.create', 'role.edit'] as $seite) {
                $faelle[$theme.' '.$seite] = [$theme, $seite];
            }
        }

        return $faelle;
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function themesMitEditSeiten(): array
    {
        $faelle = [];
        foreach (self::THEMES as $theme) {
            foreach (['user.edit', 'role.edit', 'permission.edit'] as $seite) {
                $faelle[$theme.' '.$seite] = [$theme, $seite];
            }
        }

        return $faelle;
    }

    /** @return array<string, array{0: string}> */
    public static function listen(): array
    {
        return ['Benutzer' => ['user'], 'Rollen' => ['role'], 'Rechte' => ['permission']];
    }

    /** @return array<string, array{0: string}> */
    public static function nutzerFormulare(): array
    {
        return ['anlegen' => ['anlegen'], 'bearbeiten' => ['bearbeiten']];
    }

    /** @return array<string, array{0: string, 1: mixed}> */
    public static function ausgeblendeteFelder(): array
    {
        $faelle = [];
        foreach (array_keys(self::CONFIG_FELDER) as $schluessel) {
            // `lastname` hängt in den heutigen Views am Schlüssel `firstname` (nicht eigenständig prüfbar).
            if ($schluessel === 'lastname') {
                continue;
            }
            $faelle[$schluessel.' = false'] = [$schluessel, false];
            $faelle[$schluessel.' = nicht gesetzt'] = [$schluessel, null];
        }

        return $faelle;
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function listenWerte(): array
    {
        $faelle = [];
        foreach (['anlegen', 'bearbeiten'] as $formular) {
            foreach (['email', 'role_id', 'hourly_rate', 'birthday'] as $feld) {
                $faelle[$formular.' '.$feld] = [$formular, $feld];
            }
        }

        return $faelle;
    }

    // ------------------------------------------------------------------ Hilfen

    private function url(string $seite): string
    {
        return match ($seite) {
            'user.index' => route('user'),
            'user.create' => route('user.create'),
            'user.edit' => route('user.edit', $this->ziel->id),
            'user.icon' => route('usericon.edit'),
            'role.index' => route('role'),
            'role.create' => route('role.create'),
            'role.edit' => route('role.edit', self::ROLLE_EDITOR),
            'permission.index' => route('permission'),
            'permission.create' => route('permission.create'),
            'permission.edit' => route('permission.edit', 1),
        };
    }

    private function seite(string $seite, string $theme = 'tailwind'): string
    {
        config(['laravelkit.theme' => $theme]);

        return $this->actingAs($this->admin)->get($this->url($seite))->assertOk()->getContent();
    }

    private function dom(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    /**
     * @return list<DOMElement>
     */
    private function elemente(DOMXPath $xp, string $abfrage, ?\DOMNode $kontext = null): array
    {
        $liste = [];
        foreach ($xp->query($abfrage, $kontext) ?: [] as $knoten) {
            if ($knoten instanceof DOMElement) {
                $liste[] = $knoten;
            }
        }

        return $liste;
    }

    private function formular(DOMXPath $xp, string $aktion): DOMElement
    {
        $formulare = $this->elemente($xp, '//form[@action="'.$aktion.'"]');
        $this->assertCount(1, $formulare, 'Genau ein Formular mit action '.$aktion);

        return $formulare[0];
    }

    /**
     * Namen aller Steuerelemente des Formulars (ohne disabled, Schaltflächen, _token, _method).
     *
     * @return list<string>
     */
    private function feldnamen(string $html, string $aktion): array
    {
        $xp = $this->dom($html);
        $namen = [];
        foreach ($this->elemente($xp, './/input[@name]|.//select[@name]|.//textarea[@name]', $this->formular($xp, $aktion)) as $el) {
            if ($el->hasAttribute('disabled') || in_array($el->getAttribute('type'), ['submit', 'button', 'image', 'reset'], true)) {
                continue;
            }
            $namen[] = $el->getAttribute('name');
        }
        $namen = array_values(array_diff(array_unique($namen), ['_token', '_method']));
        sort($namen);

        return $namen;
    }

    /**
     * Was der Browser beim Absenden schickt (wie `tech-laravel/testing.md`): enabled, angehakt, bei gleichem Namen der letzte Wert.
     *
     * @return array<string, string>
     */
    private function formularFelder(string $html, string $aktion): array
    {
        $xp = $this->dom($html);
        $felder = [];
        foreach ($this->elemente($xp, './/input[@name]|.//select[@name]|.//textarea[@name]', $this->formular($xp, $aktion)) as $el) {
            if ($el->hasAttribute('disabled')) {
                continue;
            }
            $name = $el->getAttribute('name');
            if ($el->tagName === 'input') {
                $typ = strtolower($el->getAttribute('type') ?: 'text');
                if (in_array($typ, ['submit', 'button', 'image', 'reset', 'file'], true)) {
                    continue;
                }
                if (in_array($typ, ['checkbox', 'radio'], true)) {
                    if ($el->hasAttribute('checked')) {
                        $felder[$name] = $el->hasAttribute('value') ? $el->getAttribute('value') : 'on';
                    }

                    continue;
                }
                $felder[$name] = $el->getAttribute('value');
            } elseif ($el->tagName === 'select') {
                $optionen = $this->elemente($xp, './/option', $el);
                $wert = null;
                foreach ($optionen as $option) {
                    if ($option->hasAttribute('selected')) {
                        $wert = $option->getAttribute('value');
                    }
                }
                $wert ??= $optionen !== [] ? $optionen[0]->getAttribute('value') : null;
                if ($wert !== null) {
                    $felder[$name] = $wert;
                }
            } else {
                $felder[$name] = $el->textContent;
            }
        }

        return $felder;
    }

    /**
     * @param  array<string, string>  $felder  flache Namen wie `permission[3]`
     * @return array<string, mixed>
     */
    private function alsDaten(array $felder): array
    {
        parse_str(http_build_query($felder), $daten);

        return $daten;
    }

    private function wertVon(string $html, string $name): ?string
    {
        $xp = $this->dom($html);
        foreach ($this->elemente($xp, '//input[@name="'.$name.'"]|//textarea[@name="'.$name.'"]') as $el) {
            return $el->tagName === 'textarea' ? $el->textContent : $el->getAttribute('value');
        }

        return null;
    }

    private function gewaehlteOption(string $html, string $name): ?string
    {
        $xp = $this->dom($html);
        foreach ($this->elemente($xp, '//select[@name="'.$name.'"]/option[@selected]') as $option) {
            return $option->getAttribute('value');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $aus  Konfigurationsschlüssel → Wert, alle übrigen gleich `$an`
     */
    private function userFelder(bool $an, array $aus = []): void
    {
        foreach (array_keys(self::CONFIG_FELDER) as $schluessel) {
            config(['userauth.user.'.$schluessel => $an]);
        }
        foreach ($aus as $schluessel => $wert) {
            config(['userauth.user.'.$schluessel => $wert]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function nutzerWerte(): array
    {
        return [
            'anrede_id' => '3', 'title' => 'Dr.', 'firstname' => 'Erika', 'lastname' => 'Muster', 'name' => 'Erika Muster',
            'smallname' => 'EM', 'email' => 'erika@example.test', 'private_email' => 'privat@example.test',
            'role_id' => (string) self::ROLLE_EDITOR, 'street' => 'Hauptstr. 1', 'postcode' => '12345', 'city' => 'Berlin',
            'country' => 'DE', 'signature_rule_id' => '3', 'ustid' => 'DE123456789', 'phone' => '030 1', 'phone2' => '030 2',
            'mobile' => '0171 3', 'fax' => '030 4', 'skype' => 'erika.skype', 'hourly_rate' => '12.50', 'birthday' => '1990-05-17',
            'comment' => 'Notiz Zeile',
        ];
    }

    private const NUTZER_FELDNAMEN = [
        'anrede_id', 'birthday', 'city', 'comment', 'country', 'email', 'fax', 'firstname', 'hourly_rate', 'image', 'lastname',
        'mobile', 'name', 'password', 'password2', 'phone', 'phone2', 'postcode', 'private_email', 'role_id', 'signature_rule_id',
        'skype', 'smallname', 'street', 'title', 'ustid',
    ];

    /**
     * Quelltext einer View ohne Blade-Ausdrücke, -Kommentare und PHP.
     */
    private function quelltext(string $view): string
    {
        $text = (string) file_get_contents(__DIR__.'/../../src/Resources/views/'.$view.'.blade.php');
        $text = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $text);
        $text = (string) preg_replace('/<\?php.*?\?>/s', '', $text);
        $text = (string) preg_replace('/\{!!.*?!!\}/s', 'X', $text);

        return (string) preg_replace('/\{\{.*?\}\}/s', 'X', $text);
    }

    /**
     * Fundstellen von Vue-Tags der Kit-Komponenten und Vue-Attributen. `:prop=` an `<x-…>`-Tags ist Blade-Syntax und gilt nicht.
     *
     * @return list<string>
     */
    private function vueFundstellen(string $text): array
    {
        $text = (string) preg_replace('#<script\b.*?</script>#is', '', $text);
        $funde = [];
        if (preg_match_all('/<('.implode('|', array_map('preg_quote', self::VUE_TAGS)).')(?=[\s\/>])/i', $text, $treffer)) {
            foreach ($treffer[0] as $fund) {
                $funde[] = 'Tag '.$fund;
            }
        }
        if (preg_match_all('/<([a-zA-Z][\w:.-]*)((?:\s[^<>]*)?)>/', $text, $tags, PREG_SET_ORDER)) {
            foreach ($tags as [$ganz, $name, $attribute]) {
                $istBlade = str_starts_with(strtolower($name), 'x-');
                $muster = $istBlade
                    ? '/(?:^|\s)(v-[\w:.-]+|@[A-Za-z][\w.-]*\s*=)/'
                    : '/(?:^|\s)(v-[\w:.-]+|:[A-Za-z][\w.-]*\s*=|@[A-Za-z][\w.-]*\s*=)/';
                if (preg_match($muster, $attribute, $a)) {
                    $funde[] = 'Attribut '.trim($a[1]).' in '.$name;
                }
            }
        }

        return $funde;
    }

    // ================================================================== AK1

    // AK1
    #[DataProvider('views')]
    public function test_ak1_quelltext_der_view_enthaelt_keine_vue_tags_der_kit_komponenten(string $view): void
    {
        $this->assertSame([], array_values(array_filter($this->vueFundstellen($this->quelltext($view)), fn (string $f) => str_starts_with($f, 'Tag '))));
    }

    // AK1
    #[DataProvider('views')]
    public function test_ak1_quelltext_der_view_enthaelt_keine_vue_attribute(string $view): void
    {
        $this->assertSame([], array_values(array_filter($this->vueFundstellen($this->quelltext($view)), fn (string $f) => str_starts_with($f, 'Attribut '))));
    }

    // AK1
    #[DataProvider('views')]
    public function test_ak1_bausteine_der_view_kommen_als_x_kit(string $view): void
    {
        $this->assertStringContainsString('<x-kit::', $this->quelltext($view));
    }

    // AK1
    public function test_ak1_erkennung_schlaegt_bei_vue_beispielen_an_und_laesst_blade_syntax_durch(): void
    {
        // Gegenprobe: ohne sie wären die Quelltext-Tests auch grün, wenn die Erkennung nichts findet.
        $this->assertNotSame([], $this->vueFundstellen('<card-main><input-text name="a" v-model="b" :value="c" @click="d" /></card-main>'));
        $this->assertCount(1, array_filter($this->vueFundstellen('<div @click="x">'), fn (string $f) => str_starts_with($f, 'Attribut ')));
        $this->assertSame([], $this->vueFundstellen('<x-kit::combobox name="a" :options="$roles" /><option value="1" @selected($x)>@lang(\'a\')</option><x-kit::checkbox name="p" />'));
    }

    // AK1
    #[DataProvider('themesMitSeiten')]
    public function test_ak1_gerenderte_seite_enthaelt_keine_vue_tags_und_keine_vue_attribute(string $theme, string $seite): void
    {
        $html = $this->seite($seite, $theme);

        // Gegenprobe: die Seite ist die Verwaltungsseite mit Formular bzw. Tabelle
        $this->assertStringContainsString('<h1', $html, 'Aufbau: Verwaltungsseite ist gerendert');

        $this->assertSame([], $this->vueFundstellen($html));
        $this->assertStringNotContainsString('x-kit::', $html, 'Kit-Tags sind gerendert');
    }

    // ================================================================== AK2

    // AK2
    #[DataProvider('themesMitSeiten')]
    public function test_ak2_seite_antwortet_mit_200(string $theme, string $seite): void
    {
        config(['laravelkit.theme' => $theme]);

        $this->actingAs($this->admin)->get($this->url($seite))->assertOk();
    }

    // ================================================================== AK3 — Feldnamen

    // AK3
    public function test_ak3_benutzer_anlegen_sendet_die_heutigen_feldnamen(): void
    {
        $this->userFelder(true);

        $namen = $this->feldnamen($this->seite('user.create'), route('user.store'));

        $this->assertSame(self::NUTZER_FELDNAMEN, $namen);
    }

    // AK3
    public function test_ak3_benutzer_bearbeiten_sendet_die_heutigen_feldnamen(): void
    {
        $this->userFelder(true);

        $namen = $this->feldnamen($this->seite('user.edit'), route('user.update', $this->ziel->id));

        $this->assertSame(self::NUTZER_FELDNAMEN, $namen);
    }

    // AK3
    #[DataProvider('nutzerFormulare')]
    public function test_ak3_benutzer_formular_mit_standardkonfiguration_sendet_nur_die_sichtbaren_felder(string $formular): void
    {
        $seite = $formular === 'anlegen' ? 'user.create' : 'user.edit';
        $aktion = $formular === 'anlegen' ? route('user.store') : route('user.update', $this->ziel->id);

        $this->assertSame(['email', 'image', 'name', 'password', 'password2', 'role_id'], $this->feldnamen($this->seite($seite), $aktion));
    }

    // AK3
    public function test_ak3_benutzerbild_formular_sendet_nur_image(): void
    {
        $html = $this->seite('user.icon');

        $this->assertSame(['image'], $this->feldnamen($html, route('usericon.update')));
        $formular = $this->formular($this->dom($html), route('usericon.update'));
        $this->assertSame('post', strtolower($formular->getAttribute('method')));
        $this->assertStringContainsString('multipart/form-data', $formular->getAttribute('enctype'));
    }

    // AK3
    public function test_ak3_rolle_formulare_senden_die_heutigen_feldnamen(): void
    {
        $alleRechte = array_map(fn (int $id) => 'permission['.$id.']', array_values($this->rechte));
        $erwartet = array_merge($alleRechte, ['role', 'role_display']);
        sort($erwartet);

        $this->assertSame($erwartet, $this->feldnamen($this->seite('role.create'), route('role.store')));
        $this->assertSame($erwartet, $this->feldnamen($this->seite('role.edit'), route('role.update', self::ROLLE_EDITOR)));
    }

    // AK3
    public function test_ak3_recht_anlegen_sendet_die_heutigen_feldnamen(): void
    {
        $this->assertSame(
            ['ckPermissionGroup', 'group_display', 'group_name', 'permission_display_new_1', 'permission_new_1'],
            $this->feldnamen($this->seite('permission.create'), route('permission.store'))
        );
    }

    // AK3
    public function test_ak3_recht_bearbeiten_sendet_die_heutigen_feldnamen_je_recht(): void
    {
        // Einzelrechte-Gruppe (id 3): bestehende Rechte 9, 10 plus die neue Zeile; das Gruppen-Kästchen ist gesperrt.
        $html = $this->actingAs($this->admin)->get(route('permission.edit', 3))->assertOk()->getContent();

        $namen = $this->feldnamen($html, route('permission.update', 3));

        $erwartet = ['group_display', 'group_name', 'permission_display_new_1', 'permission_new_1'];
        foreach ([$this->rechte['extras_a'], $this->rechte['extras_b']] as $id) {
            $erwartet[] = 'permission_display_'.$id;
            $erwartet[] = 'permission_'.$id;
        }
        sort($erwartet);
        $this->assertSame($erwartet, $namen);

        // Gruppen-Kästchen ist gesperrt: der Browser sendet es nicht
        $this->assertArrayNotHasKey('ckPermissionGroup', $this->formularFelder($html, route('permission.update', 3)));
    }

    // AK3
    #[DataProvider('themesMitFormularSeiten')]
    public function test_ak3_formulare_senden_per_post_an_die_echte_route_mit_csrf_token(string $theme, string $seite): void
    {
        $formulare = [
            'user.create' => route('user.store'),
            'user.edit' => route('user.update', $this->ziel->id),
            'user.icon' => route('usericon.update'),
            'role.create' => route('role.store'),
            'role.edit' => route('role.update', self::ROLLE_EDITOR),
            'permission.create' => route('permission.store'),
            'permission.edit' => route('permission.update', 1),
        ];
        $xp = $this->dom($this->seite($seite, $theme));
        $form = $this->formular($xp, $formulare[$seite]);

        $this->assertSame('post', strtolower($form->getAttribute('method')));
        $token = $this->elemente($xp, './/input[@name="_token"]', $form);
        $this->assertCount(1, $token);
        $this->assertNotSame('', $token[0]->getAttribute('value'));
        $this->assertCount(1, $this->elemente($xp, './/button[@type="submit"]|.//input[@type="submit"]', $form), 'Speichern-Schaltfläche');
    }

    // ================================================================== AK3 — Werte und old()

    // AK3
    public function test_ak3_benutzer_bearbeiten_zeigt_die_gespeicherten_werte_und_die_rolle(): void
    {
        $this->userFelder(true);
        DB::table('users')->where('id', $this->ziel->id)->update(['street' => 'Altstr. 9', 'city' => 'Hamburg', 'comment' => 'Altnotiz']);

        $html = $this->seite('user.edit');

        $this->assertSame('Ziel Nutzer', $this->wertVon($html, 'name'));
        $this->assertSame('ziel@example.test', $this->wertVon($html, 'email'));
        $this->assertSame('Altstr. 9', $this->wertVon($html, 'street'));
        $this->assertSame('Hamburg', $this->wertVon($html, 'city'));
        $this->assertSame('Altnotiz', $this->wertVon($html, 'comment'));
        $this->assertSame((string) self::ROLLE_EDITOR, $this->gewaehlteOption($html, 'role_id'));
    }

    // AK3
    #[DataProvider('nutzerFormulare')]
    public function test_ak3_benutzer_formular_bietet_jede_rolle_zur_auswahl_an(string $formular): void
    {
        $html = $this->seite($formular === 'anlegen' ? 'user.create' : 'user.edit');

        $optionen = [];
        foreach ($this->elemente($this->dom($html), '//select[@name="role_id"]/option') as $option) {
            $optionen[$option->getAttribute('value')] = trim($option->textContent);
        }

        $this->assertSame(['1' => 'Dev', '2' => 'Admin', '3' => 'User', '4' => 'Editor'], $optionen);
    }

    // AK3
    public function test_ak3_benutzer_anlegen_zeigt_nach_validierungsfehler_die_eingegebenen_werte(): void
    {
        $this->userFelder(true);
        $url = route('user.create');
        $werte = array_merge($this->nutzerWerte(), ['email' => 'kein-email', 'password' => 'x', 'password2' => 'y']);

        $this->actingAs($this->admin)->from($url)->post(route('user.store'), $werte)->assertSessionHasErrors('email');
        $html = $this->actingAs($this->admin)->from($url)->followingRedirects()->post(route('user.store'), $werte)->assertOk()->getContent();

        $this->assertOldWerteDesNutzers($html, $werte);
        $this->assertSame(0, DB::table('users')->where('name', 'Erika Muster')->count(), 'Gegenprobe: nichts gespeichert');
    }

    // AK3
    public function test_ak3_benutzer_bearbeiten_zeigt_nach_validierungsfehler_die_eingegebenen_werte(): void
    {
        $this->userFelder(true);
        $url = route('user.edit', $this->ziel->id);
        $werte = array_merge($this->nutzerWerte(), ['email' => 'kein-email']);

        $this->actingAs($this->admin)->from($url)->post(route('user.update', $this->ziel->id), $werte)->assertSessionHasErrors('email');
        $html = $this->actingAs($this->admin)->from($url)->followingRedirects()->post(route('user.update', $this->ziel->id), $werte)->assertOk()->getContent();

        $this->assertOldWerteDesNutzers($html, $werte);
        $this->assertSame('Ziel Nutzer', DB::table('users')->where('id', $this->ziel->id)->value('name'), 'Gegenprobe: nichts gespeichert');
    }

    /**
     * @param  array<string, string>  $werte
     */
    private function assertOldWerteDesNutzers(string $html, array $werte): void
    {
        foreach (array_diff_key($werte, ['password' => 1, 'password2' => 1]) as $feld => $wert) {
            if (in_array($feld, ['anrede_id', 'signature_rule_id', 'role_id'], true)) {
                $this->assertSame($wert, $this->gewaehlteOption($html, $feld), 'Auswahl '.$feld);
            } else {
                $this->assertSame($wert, $this->wertVon($html, $feld), 'Feld '.$feld);
            }
        }
    }

    // AK3
    public function test_ak3_benutzer_anlegen_legt_mit_den_formularfeldern_an(): void
    {
        $this->userFelder(true);
        $html = $this->seite('user.create');
        $felder = $this->formularFelder($html, route('user.store'));
        $werte = array_merge($this->nutzerWerte(), ['password' => 'Sehr-Langes-Passwort-2026!', 'password2' => 'Sehr-Langes-Passwort-2026!']);

        $this->actingAs($this->admin)
            ->post(route('user.store'), $this->alsDaten(array_merge($felder, $werte)))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user'));

        $zeile = (array) DB::table('users')->where('email', 'erika@example.test')->first();
        $this->assertNotEmpty($zeile, 'Benutzer angelegt');
        foreach (array_diff_key($this->nutzerWerte(), ['hourly_rate' => 1, 'birthday' => 1]) as $feld => $wert) {
            $this->assertSame($wert, (string) $zeile[$feld], 'Spalte '.$feld);
        }
        $this->assertEqualsWithDelta(12.5, (float) $zeile['hourly_rate'], 0.001);
        $this->assertStringStartsWith('1990-05-17', (string) $zeile['birthday']);
    }

    // AK3
    public function test_ak3_benutzer_bearbeiten_aendert_mit_den_formularfeldern(): void
    {
        $this->userFelder(true);
        $html = $this->seite('user.edit');
        $felder = $this->formularFelder($html, route('user.update', $this->ziel->id));
        $werte = array_merge($this->nutzerWerte(), ['password' => '', 'password2' => '']);

        $this->actingAs($this->admin)
            ->post(route('user.update', $this->ziel->id), $this->alsDaten(array_merge($felder, $werte)))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user'));

        $zeile = (array) DB::table('users')->where('id', $this->ziel->id)->first();
        foreach (array_diff_key($this->nutzerWerte(), ['hourly_rate' => 1, 'birthday' => 1]) as $feld => $wert) {
            $this->assertSame($wert, (string) $zeile[$feld], 'Spalte '.$feld);
        }
        $this->assertEqualsWithDelta(12.5, (float) $zeile['hourly_rate'], 0.001);
        $this->assertSame(1, DB::table('users')->where('email', 'erika@example.test')->count());
    }

    // AK3
    public function test_ak3_rolle_anlegen_legt_mit_den_formularfeldern_an_und_vergibt_die_rechte(): void
    {
        $html = $this->seite('role.create');
        $felder = $this->formularFelder($html, route('role.store'));
        $felder = array_merge($felder, [
            'role_display' => 'Neue Rolle', 'role' => 'neue_rolle',
            'permission['.$this->rechte['user_read'].']' => (string) $this->rechte['user_read'],
            'permission['.$this->rechte['extras_b'].']' => (string) $this->rechte['extras_b'],
        ]);

        $this->actingAs($this->admin)->post(route('role.store'), $this->alsDaten($felder))
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $rolle = DB::table('roles')->where('role_display', 'Neue Rolle')->first();
        $this->assertNotNull($rolle);
        $this->assertSame('neue_rolle', $rolle->role);
        $ids = DB::table('role_permission')->where('role_id', $rolle->id)->pluck('permission_id')->sort()->values()->all();
        $erwartet = [$this->rechte['user_read'], $this->rechte['extras_b']];
        sort($erwartet);
        $this->assertEquals($erwartet, $ids);
    }

    // AK3
    public function test_ak3_rolle_bearbeiten_aendert_mit_den_formularfeldern_und_behaelt_angehakte_rechte(): void
    {
        $html = $this->seite('role.edit');
        $felder = $this->formularFelder($html, route('role.update', self::ROLLE_EDITOR));

        // Ausgangslage aus dem Formular: die Rechte der Rolle sind angehakt
        $this->assertArrayHasKey('permission['.$this->rechte['user_read'].']', $felder);
        $this->assertArrayHasKey('permission['.$this->rechte['extras_a'].']', $felder);

        $felder = array_merge($felder, ['role_display' => 'Editor Neu', 'role' => 'editor_neu']);
        $this->actingAs($this->admin)->post(route('role.update', self::ROLLE_EDITOR), $this->alsDaten($felder))
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $this->assertSame('Editor Neu', DB::table('roles')->where('id', self::ROLLE_EDITOR)->value('role_display'));
        $ids = DB::table('role_permission')->where('role_id', self::ROLLE_EDITOR)->pluck('permission_id')->sort()->values()->all();
        $erwartet = [$this->rechte['user_read'], $this->rechte['extras_a']];
        sort($erwartet);
        $this->assertEquals($erwartet, $ids, 'angehakte Rechte bleiben erhalten');
    }

    // Randfall „CRUD-Recht fehlt“
    #[DataProvider('themesMitRollenSeiten')]
    public function test_randfall_fehlendes_crud_recht_rendert_ohne_fehler_und_laesst_das_kaestchen_entfallen(string $theme, string $seite): void
    {
        DB::table('permissions')->where('id', $this->rechte['user_edit'])->update(['deleted_at' => now()]);

        $html = $this->seite($seite, $theme);
        $aktion = $seite === 'role.edit' ? route('role.update', self::ROLLE_EDITOR) : route('role.store');
        $this->assertNotSame([], $this->feldnamen($html, $aktion), 'Formular ist da');

        $this->assertStringNotContainsString('id="permission_'.$this->rechte['user_edit'].'"', $html);
        // Gegenprobe: die übrigen Kästchen der Gruppe sind da
        foreach (['user_create', 'user_read', 'user_delete', 'role_create', 'role_edit'] as $recht) {
            $this->assertStringContainsString('id="permission_'.$this->rechte[$recht].'"', $html, $recht);
        }
    }

    // AK3
    #[DataProvider('themesMitRollenSeiten')]
    public function test_ak3_rolle_bearbeiten_hakt_genau_die_rechte_der_rolle_an(string $theme, string $seite): void
    {
        $felder = $this->formularFelder($this->seite($seite, $theme), $seite === 'role.edit' ? route('role.update', self::ROLLE_EDITOR) : route('role.store'));
        $angehakt = array_values(array_filter(array_keys($felder), fn (string $n) => str_starts_with($n, 'permission[')));
        sort($angehakt);

        if ($seite === 'role.create') {
            $this->assertSame([], $angehakt, 'neue Rolle: nichts angehakt');

            return;
        }

        $erwartet = ['permission['.$this->rechte['user_read'].']', 'permission['.$this->rechte['extras_a'].']'];
        sort($erwartet);
        $this->assertSame($erwartet, $angehakt);
    }

    // AK3
    public function test_ak3_rolle_anlegen_zeigt_nach_validierungsfehler_die_eingegebenen_werte(): void
    {
        $url = route('role.create');
        $daten = ['role_display' => 'Editor', 'role' => 'neue_rolle'];

        $this->actingAs($this->admin)->from($url)->post(route('role.store'), $daten)->assertSessionHasErrors('role_display');
        $html = $this->actingAs($this->admin)->from($url)->followingRedirects()->post(route('role.store'), $daten)->assertOk()->getContent();

        $this->assertSame('Editor', $this->wertVon($html, 'role_display'));
        $this->assertSame('neue_rolle', $this->wertVon($html, 'role'));
    }

    // AK3
    public function test_ak3_rolle_bearbeiten_zeigt_nach_validierungsfehler_die_eingegebenen_werte(): void
    {
        $url = route('role.edit', self::ROLLE_EDITOR);
        $daten = ['role_display' => 'Dev', 'role' => 'neuer_name'];

        $this->actingAs($this->admin)->from($url)->post(route('role.update', self::ROLLE_EDITOR), $daten)->assertSessionHasErrors('role_display');
        $html = $this->actingAs($this->admin)->from($url)->followingRedirects()->post(route('role.update', self::ROLLE_EDITOR), $daten)->assertOk()->getContent();

        $this->assertSame('Dev', $this->wertVon($html, 'role_display'));
        $this->assertSame('neuer_name', $this->wertVon($html, 'role'));
    }

    // AK3
    public function test_ak3_recht_anlegen_legt_gruppe_mit_crud_rechten_an(): void
    {
        $felder = $this->formularFelder($this->seite('permission.create'), route('permission.store'));
        $felder = array_merge($felder, ['group_display' => 'Lager', 'group_name' => 'lager']);
        unset($felder['ckPermissionGroup']);

        $this->actingAs($this->admin)->post(route('permission.store'), $this->alsDaten($felder))
            ->assertSessionHasNoErrors()->assertRedirect(route('permission'));

        $gruppe = DB::table('permissions_groups')->where('group_name', 'lager')->first();
        $this->assertNotNull($gruppe);
        $this->assertSame(0, (int) $gruppe->is_group);
        $this->assertEquals(
            ['lager_create', 'lager_delete', 'lager_edit', 'lager_read'],
            DB::table('permissions')->where('group_id', $gruppe->id)->pluck('permission')->sort()->values()->all()
        );
    }

    // AK3
    public function test_ak3_recht_anlegen_legt_gruppe_mit_einzelrechten_an(): void
    {
        $felder = $this->formularFelder($this->seite('permission.create'), route('permission.store'));
        $felder = array_merge($felder, [
            'group_display' => 'Berichte', 'group_name' => 'berichte', 'ckPermissionGroup' => 'on',
            'permission_display_new_1' => 'Export', 'permission_new_1' => 'export',
        ]);

        $this->actingAs($this->admin)->post(route('permission.store'), $this->alsDaten($felder))
            ->assertSessionHasNoErrors()->assertRedirect(route('permission'));

        $gruppe = DB::table('permissions_groups')->where('group_name', 'berichte')->first();
        $this->assertNotNull($gruppe);
        $this->assertSame(1, (int) $gruppe->is_group);
        $recht = DB::table('permissions')->where('group_id', $gruppe->id)->first();
        $this->assertSame('Export', $recht->permission_display);
        $this->assertSame('export', $recht->permission);
    }

    // AK3
    public function test_ak3_recht_bearbeiten_aendert_gruppe_und_einzelrechte(): void
    {
        $html = $this->actingAs($this->admin)->get(route('permission.edit', 3))->assertOk()->getContent();
        $felder = $this->formularFelder($html, route('permission.update', 3));
        $idA = $this->rechte['extras_a'];
        $felder = array_merge($felder, [
            'group_display' => 'Extras Neu', 'group_name' => 'extras',
            'permission_display_'.$idA => 'A geändert', 'permission_'.$idA => 'extras_a',
            'permission_display_new_1' => 'Neues Recht', 'permission_new_1' => 'extras_neu',
        ]);

        $this->actingAs($this->admin)->post(route('permission.update', 3), $this->alsDaten($felder))
            ->assertSessionHasNoErrors()->assertRedirect(route('permission'));

        $this->assertSame('Extras Neu', DB::table('permissions_groups')->where('id', 3)->value('group_display'));
        $this->assertSame('A geändert', DB::table('permissions')->where('id', $idA)->value('permission_display'));
        $this->assertSame(1, DB::table('permissions')->where('group_id', 3)->where('permission', 'extras_neu')->count());
    }

    // AK3
    public function test_ak3_recht_bearbeiten_aendert_crud_gruppe(): void
    {
        $felder = $this->formularFelder($this->seite('permission.edit'), route('permission.update', 1));
        $felder = array_merge($felder, ['group_display' => 'Nutzer Neu', 'group_name' => 'nutzer']);

        $this->actingAs($this->admin)->post(route('permission.update', 1), $this->alsDaten($felder))
            ->assertSessionHasNoErrors()->assertRedirect(route('permission'));

        $this->assertSame('Nutzer Neu', DB::table('permissions_groups')->where('id', 1)->value('group_display'));
        $this->assertSame('nutzer_read', DB::table('permissions')->where('id', $this->rechte['user_read'])->value('permission'));
    }

    // AK3
    public function test_ak3_recht_anlegen_zeigt_nach_validierungsfehler_die_eingegebenen_werte(): void
    {
        $url = route('permission.create');
        $daten = ['group_display' => 'Gruppe Nutzer', 'group_name' => 'neue_gruppe', 'ckPermissionGroup' => 'on'];

        $this->actingAs($this->admin)->from($url)->post(route('permission.store'), $daten)->assertSessionHasErrors('group_display');
        $html = $this->actingAs($this->admin)->from($url)->followingRedirects()->post(route('permission.store'), $daten)->assertOk()->getContent();

        $this->assertSame('Gruppe Nutzer', $this->wertVon($html, 'group_display'));
        $this->assertSame('neue_gruppe', $this->wertVon($html, 'group_name'));
        $this->assertArrayHasKey('ckPermissionGroup', $this->formularFelder($html, route('permission.store')), 'Kästchen bleibt angehakt');
    }

    // AK3
    public function test_ak3_recht_bearbeiten_zeigt_nach_validierungsfehler_die_eingegebenen_werte(): void
    {
        $url = route('permission.edit', 3);
        $idA = $this->rechte['extras_a'];
        $daten = ['group_display' => '', 'group_name' => 'neuer_name', 'permission_display_'.$idA => 'Neue Anzeige', 'permission_'.$idA => 'neu_intern'];

        $this->actingAs($this->admin)->from($url)->post(route('permission.update', 3), $daten)->assertSessionHasErrors('group_display');
        $html = $this->actingAs($this->admin)->from($url)->followingRedirects()->post(route('permission.update', 3), $daten)->assertOk()->getContent();

        $this->assertSame('neuer_name', $this->wertVon($html, 'group_name'));
        $this->assertSame('Neue Anzeige', $this->wertVon($html, 'permission_display_'.$idA));
        $this->assertSame('neu_intern', $this->wertVon($html, 'permission_'.$idA));
    }

    // AK3
    #[DataProvider('listenWerte')]
    public function test_ak3_liste_statt_einzelwert_fuehrt_zu_validierungsfehler_und_folgeseite_ohne_500(string $formular, string $feld): void
    {
        $this->userFelder(true);
        $anlegen = $formular === 'anlegen';
        $url = $anlegen ? route('user.create') : route('user.edit', $this->ziel->id);
        $aktion = $anlegen ? route('user.store') : route('user.update', $this->ziel->id);
        $werte = array_merge($this->nutzerWerte(), ['password' => 'Sehr-Langes-Passwort-2026!', 'password2' => 'Sehr-Langes-Passwort-2026!']);
        $werte[$feld] = ['a', 'b'];

        $this->actingAs($this->admin)->from($url)->post($aktion, $werte)->assertSessionHasErrors($feld);
        $antwort = $this->actingAs($this->admin)->from($url)->followingRedirects()->post($aktion, $werte);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertOk();
    }

    // ================================================================== AK3 — Randfall 3

    // AK3
    // Randfall 3
    #[DataProvider('ausgeblendeteFelder')]
    public function test_randfall3_per_config_ausgeblendetes_feld_bleibt_in_beiden_formularen_ausgeblendet(string $schluessel, mixed $wert): void
    {
        $this->userFelder(true);
        $feld = self::CONFIG_FELDER[$schluessel];

        // Gegenprobe: bei eingeschaltetem Schlüssel ist das Feld da (Aufbau greift)
        $this->assertContains($feld, $this->feldnamen($this->seite('user.create'), route('user.store')));
        $this->assertContains($feld, $this->feldnamen($this->seite('user.edit'), route('user.update', $this->ziel->id)));

        // (userFelder setzt nur einmal: erneut mit ausgeschaltetem Schlüssel)
        config(['userauth.user.'.$schluessel => $wert]);
        $this->assertNotContains($feld, $this->feldnamen($this->seite('user.create'), route('user.store')));
        $this->assertNotContains($feld, $this->feldnamen($this->seite('user.edit'), route('user.update', $this->ziel->id)));
    }

    // ================================================================== AK4

    // AK4
    #[DataProvider('themesMitEditSeiten')]
    public function test_ak4_template_ausdruck_im_namen_steht_als_text_im_html(string $theme, string $seite): void
    {
        $ausdruck = '{{ 7*7 }}';
        DB::table('users')->where('id', $this->ziel->id)->update(['name' => $ausdruck]);
        DB::table('roles')->where('id', self::ROLLE_EDITOR)->update(['role_display' => $ausdruck, 'role' => $ausdruck]);
        DB::table('permissions_groups')->where('id', 1)->update(['group_display' => $ausdruck]);
        DB::table('permissions_groups')->where('id', 3)->update(['group_display' => $ausdruck]);
        DB::table('permissions')->where('id', $this->rechte['extras_a'])->update(['permission_display' => $ausdruck]);
        $this->userFelder(true);

        config(['laravelkit.theme' => $theme]);
        $html = $seite === 'permission.edit'
            ? $this->actingAs($this->admin)->get(route('permission.edit', 3))->assertOk()->getContent()
            : $this->seite($seite, $theme);

        $this->assertStringContainsString($ausdruck, $html, 'Ausdruck steht als Text da');

        $xp = $this->dom($html);
        $namen = match ($seite) {
            'user.edit' => ['name'],
            'role.edit' => ['role_display', 'role'],
            'permission.edit' => ['group_display', 'permission_display_'.$this->rechte['extras_a']],
        };
        foreach ($namen as $name) {
            $this->assertSame($ausdruck, $this->wertVon($html, $name), 'Feld '.$name);
        }
        foreach ($this->elemente($xp, '//input[@value]') as $el) {
            $this->assertNotSame('49', $el->getAttribute('value'), 'kein ausgewerteter Ausdruck in '.$el->getAttribute('name'));
        }
    }

    // AK4
    public function test_ak4_rolle_mit_ausdruck_erscheint_als_text_in_der_rollenauswahl_des_benutzers(): void
    {
        DB::table('roles')->where('id', self::ROLLE_EDITOR)->update(['role_display' => '{{ 7*7 }}']);

        foreach (self::THEMES as $theme) {
            $texte = [];
            foreach ($this->elemente($this->dom($this->seite('user.edit', $theme)), '//select[@name="role_id"]/option') as $option) {
                $texte[] = trim($option->textContent);
            }
            $this->assertContains('{{ 7*7 }}', $texte, $theme);
            $this->assertNotContains('49', $texte, $theme);
        }
    }

    // AK4
    public function test_ak4_gruppe_und_recht_mit_ausdruck_erscheinen_als_text_in_der_rollenmaske(): void
    {
        DB::table('permissions_groups')->where('id', 1)->update(['group_display' => '{{ 7*7 }}']);
        DB::table('permissions_groups')->where('id', 3)->update(['group_display' => '{{ 7*7 }}']);
        DB::table('permissions')->where('id', $this->rechte['extras_a'])->update(['permission_display' => '{{ 7*7 }}']);

        foreach (self::THEMES as $theme) {
            $texte = [];
            foreach ($this->elemente($this->dom($this->seite('role.edit', $theme)), '//th|//td') as $zelle) {
                $texte[] = trim($zelle->textContent);
            }
            $this->assertGreaterThanOrEqual(3, count(array_filter($texte, fn (string $t) => $t === '{{ 7*7 }}')), $theme.': Gruppe (CRUD), Gruppe (Einzel) und Recht');
            $this->assertNotContains('49', $texte, $theme);
        }
    }

    // ================================================================== AK5 — Insel und Formular

    // AK5
    #[DataProvider('listen')]
    public function test_ak5_liste_enthaelt_den_loesch_dialog_als_insel_mit_props(string $liste): void
    {
        $html = $this->seite($liste.'.index');

        $xp = $this->dom($html);
        $inseln = $this->elemente($xp, '//*[@data-vue="dialog-delete"]');
        $this->assertCount(1, $inseln);
        $props = json_decode($inseln[0]->getAttribute('data-props'), true);
        $this->assertIsArray($props);
        foreach (['titel', 'body', 'route'] as $prop) {
            $this->assertIsString($props[$prop] ?? null, 'Prop '.$prop);
            $this->assertNotSame('', $props[$prop], 'Prop '.$prop);
        }
        $this->assertSame(route($liste.'.delete', 0), $props['route']);
    }

    // AK5
    #[DataProvider('listen')]
    public function test_ak5_liste_enthaelt_formular_form_delete_mit_methode_delete_und_csrf_token(string $liste): void
    {
        $xp = $this->dom($this->seite($liste.'.index'));

        $formulare = $this->elemente($xp, '//form[@id="formDelete"]');
        $this->assertCount(1, $formulare);
        $form = $formulare[0];

        $this->assertSame('post', strtolower($form->getAttribute('method')), 'DELETE wird per POST mit _method gesendet');
        $methode = $this->elemente($xp, './/input[@name="_method"]', $form);
        $this->assertCount(1, $methode);
        $this->assertSame('DELETE', strtoupper($methode[0]->getAttribute('value')));
        $token = $this->elemente($xp, './/input[@name="_token"]', $form);
        $this->assertCount(1, $token);
        $this->assertNotSame('', $token[0]->getAttribute('value'));
        $this->assertCount(0, $this->elemente($xp, 'ancestor::form', $form), 'nicht in einem anderen Formular verschachtelt');
    }

    // AK5
    #[DataProvider('listen')]
    public function test_ak5_aktionsspalte_der_liste_hat_data_kit_delete_und_behaelt_open_delete_form(string $liste): void
    {
        // Gegenprobe-Aufbau: Zeile mit einer ID über 2 (Rollen zeigen die Aktionen nur dafür)
        $id = match ($liste) {
            'user' => $this->ziel->id,
            'role' => self::ROLLE_EDITOR,
            'permission' => 4,
        };
        if ($liste === 'permission') {
            $this->gruppe(4, 'lager', 'Gruppe Lager', 0, ['create', 'read', 'edit', 'delete']);
        }

        $antwort = $this->actingAs($this->admin)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get(route($liste))->assertOk();
        $zeilen = array_filter($antwort->json('data'), fn (array $z) => (int) $z['id'] === $id);

        $this->assertCount(1, $zeilen, 'Zeile der Liste vorhanden');
        $aktion = array_values($zeilen)[0]['action'];
        $this->assertStringContainsString('data-kit-delete="'.$id.'"', $aktion);
        $this->assertStringContainsString('OpenDeleteForm('.$id.')', $aktion);
    }

    // ================================================================== AK7

    // AK7
    #[DataProvider('themesMitRollenSeiten')]
    public function test_ak7_tabs_der_rollenmaske_nutzen_die_kit_bausteine(string $theme, string $seite): void
    {
        $xp = $this->dom($this->seite($seite, $theme));

        $this->assertCount(1, $this->elemente($xp, '//ul[@data-kit-tabs]'), 'tabs-header');
        $reiter = $this->elemente($xp, '//ul[@data-kit-tabs]//button[@data-kit-tab]');
        $this->assertSame(['home', 'profile'], array_map(fn (DOMElement $e) => $e->getAttribute('data-kit-tab'), $reiter));
        $this->assertCount(1, $this->elemente($xp, '//div[@data-kit-tabs-body]'), 'tabs-body');
        $koerper = $this->elemente($xp, '//div[@data-kit-tab-body]');
        $this->assertSame(['home', 'profile'], array_map(fn (DOMElement $e) => $e->getAttribute('data-kit-tab-body'), $koerper));
    }

    // AK7
    #[DataProvider('themesMitRollenSeiten')]
    public function test_ak7_erster_reiter_ist_aktiv_und_der_zweite_nicht(string $theme, string $seite): void
    {
        $xp = $this->dom($this->seite($seite, $theme));
        $reiter = $this->elemente($xp, '//button[@data-kit-tab]');
        $koerper = $this->elemente($xp, '//div[@data-kit-tab-body]');

        $this->assertCount(2, $reiter);
        $this->assertCount(2, $koerper);
        $aktiv = fn (DOMElement $e): bool => in_array('active', preg_split('/\s+/', $e->getAttribute('class')) ?: [], true);

        $this->assertTrue($aktiv($reiter[0]), 'erster Reiter aktiv');
        $this->assertSame('true', $reiter[0]->getAttribute('aria-selected'));
        $this->assertTrue($aktiv($koerper[0]), 'erster Körper aktiv');
        // Gegenprobe der Verneinung: der zweite Reiter ist da und geschieden vom ersten
        $this->assertSame('profile', $reiter[1]->getAttribute('data-kit-tab'));
        $this->assertFalse($aktiv($reiter[1]), 'zweiter Reiter nicht aktiv');
        $this->assertSame('false', $reiter[1]->getAttribute('aria-selected'));
        $this->assertFalse($aktiv($koerper[1]), 'zweiter Körper nicht aktiv');
    }

    // AK7
    public function test_ak7_checkboxen_liegen_in_den_passenden_reitern(): void
    {
        $xp = $this->dom($this->seite('role.edit'));

        $home = $this->elemente($xp, '//div[@data-kit-tab-body="home"]//input[@type="checkbox"]');
        $profil = $this->elemente($xp, '//div[@data-kit-tab-body="profile"]//input[@type="checkbox"]');

        $this->assertCount(8, $home, 'CRUD-Rechte (2 Gruppen à 4)');
        $this->assertCount(2, $profil, 'Einzelrechte');
    }

    // ================================================================== AK8

    /**
     * @return array{spalten: list<string>, ajax: string, entry: array<string, string>}
     */
    private function dataTable(string $html): array
    {
        $this->assertSame(1, preg_match('/\.DataTable\(\{(.*?)\}\);\s*\}\);/s', $html, $block), 'DataTable-Initialisierung');
        $this->assertSame(1, preg_match('/ajax:\s*["\']([^"\']+)["\']/', $block[1], $ajax), 'ajax');
        $this->assertSame(1, preg_match('/columns:\s*\[(.*)\]/s', $block[1], $spalten), 'columns');
        preg_match_all('/\{([^{}]*)\}/', $spalten[1], $eintraege);
        $namen = [];
        $roh = [];
        foreach ($eintraege[1] as $eintrag) {
            preg_match('/data:\s*["\']([^"\']+)["\']/', $eintrag, $d);
            $namen[] = $d[1];
            $roh[$d[1]] = $eintrag;
        }

        return ['spalten' => $namen, 'ajax' => $ajax[1], 'entry' => $roh];
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function dataTables(): array
    {
        return [
            'Benutzer' => ['user', ['id', 'name', 'email', 'RoleName', 'action']],
            'Rollen' => ['role', ['id', 'role_display', 'role', 'action']],
            'Rechte' => ['permission', ['id', 'group_display', 'group_name', 'action']],
        ];
    }

    /**
     * @param  list<string>  $spalten
     */
    #[DataProvider('dataTables')]
    public function test_ak8_liste_nutzt_jquery_datatable_mit_denselben_spalten_und_derselben_ajax_route(string $liste, array $spalten): void
    {
        $html = $this->seite($liste.'.index');
        $tabelle = $this->dataTable($html);

        $this->assertSame($spalten, $tabelle['spalten']);
        $this->assertSame(route($liste), $tabelle['ajax']);
        $this->assertMatchesRegularExpression('/\$\(\s*[\'"]\.data-table[\'"]\s*\)\.DataTable\(/', $html);
        $this->assertMatchesRegularExpression('/serverSide:\s*true/', $html);
        $this->assertMatchesRegularExpression('/processing:\s*true/', $html);
        $this->assertMatchesRegularExpression('/orderable:\s*false/', $tabelle['entry']['action']);
        $this->assertMatchesRegularExpression('/searchable:\s*false/', $tabelle['entry']['action']);
        $kopf = $this->elemente($this->dom($html), '//table[contains(@class,"data-table")]/thead//th');
        $this->assertCount(count($spalten), $kopf, 'eine Kopfzelle je Spalte');
    }

    /**
     * @param  list<string>  $spalten
     */
    #[DataProvider('dataTables')]
    public function test_ak8_ajax_route_liefert_zeilen_mit_den_spalten_der_tabelle(string $liste, array $spalten): void
    {
        $antwort = $this->actingAs($this->admin)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get(route($liste))->assertOk();

        $zeilen = $antwort->json('data');
        $this->assertNotEmpty($zeilen);
        foreach ($spalten as $spalte) {
            $this->assertArrayHasKey($spalte, $zeilen[0]);
        }
    }

    // ================================================================== Randfälle 1 und 2

    // Randfall 1
    #[DataProvider('leereRollenlisten')]
    public function test_randfall1_leere_rollenliste_gibt_combobox_ohne_optionen_ohne_fehler(mixed $rollen): void
    {
        $this->userFelder(true);
        config(['userauth.user.role' => true]);

        $html = view('userauth::user.create', ['roles' => $rollen, 'breadcrumb' => new Breadcrumb, 'errors' => new ViewErrorBag])->render();

        $selects = $this->elemente($this->dom($html), '//select[@name="role_id"]');
        $this->assertCount(1, $selects, 'Auswahl für die Rolle ist da');
        $this->assertCount(0, $this->elemente($this->dom($html), '//select[@name="role_id"]/option'));
    }

    /** @return array<string, array{0: mixed}> */
    public static function leereRollenlisten(): array
    {
        return ['JSON-Text []' => ['[]'], 'leeres Array' => [[]]];
    }

    // Randfall 2
    public function test_randfall2_benutzerbild_seite_ohne_bild_rendert_mit_leerer_vorschau(): void
    {
        $this->assertTrue(in_array($this->admin->image, [null, ''], true), 'Aufbau: Benutzer ohne Bild');

        foreach (self::THEMES as $theme) {
            $html = $this->seite('user.icon', $theme);
            $xp = $this->dom($html);

            $this->assertCount(1, $this->elemente($xp, '//input[@type="file"][@name="image"]'), $theme.': Auswahlfeld ist da');
            $this->assertCount(0, $this->elemente($xp, '//form//img'), $theme.': keine Vorschau');
        }
    }

    // ================================================================== AK9

    private function upgradeAbschnitt(): string
    {
        $readme = (string) file_get_contents(__DIR__.'/../../README.md');
        $this->assertSame(1, preg_match('/^## Upgrade-Hinweise \(nach 3\.0\.0.*?(?=^## )/ms', $readme, $abschnitt), 'Abschnitt „Upgrade-Hinweise (nach 3.0.0 …)“');

        return $abschnitt[0];
    }

    // AK9
    public function test_ak9_readme_nennt_die_host_einbindung_von_init_tabs_init_loeschen_und_mount_inseln(): void
    {
        $abschnitt = $this->upgradeAbschnitt();

        $this->assertStringContainsString('initTabs()', $abschnitt);
        $this->assertStringContainsString('initLoeschen()', $abschnitt);
        $this->assertStringContainsString('mountInseln()', $abschnitt);
        $this->assertStringContainsString('dialog-delete', $abschnitt);
        $this->assertStringContainsString('laravel-kit', $abschnitt);
    }

    // AK9
    public function test_ak9_readme_verlangt_neu_veroeffentlichte_verwaltungs_views(): void
    {
        $this->assertMatchesRegularExpression('/neu\s+ver[öo]ffentlich|erneut\s+ver[öo]ffentlich/iu', $this->upgradeAbschnitt());
        $this->assertMatchesRegularExpression('/Views/u', $this->upgradeAbschnitt());
    }

    // AK9
    public function test_ak9_readme_sagt_dass_whole_dom_mount_nicht_mehr_noetig_ist(): void
    {
        $this->assertMatchesRegularExpression('/Whole-DOM[^\n]*nicht mehr (nötig|erforderlich|notwendig)|nicht mehr (nötig|erforderlich|notwendig)[^\n]*Whole-DOM/u', $this->upgradeAbschnitt());
    }

    // AK9
    public function test_ak9_readme_nennt_den_mindeststand_von_laravel_kit_mit_versionsnummer(): void
    {
        $this->assertMatchesRegularExpression('/(Mindest|mindestens|ab Version|ab `?\^?\d)[^\n]*laravel-kit[^\n]*\d+\.\d+|laravel-kit[^\n]*(Mindest|mindestens|ab Version|ab `?\^?\d)[^\n]*\d+\.\d+/iu', $this->upgradeAbschnitt());
    }

    // ------------------------------------------------------------------ T7: AK1 Kästchen, AK3 Rechtezeilen

    /** @return array<string, array{0: string}> */
    public static function kaestchenViews(): array
    {
        return [
            'role/recht' => ['role/recht'],
            'permission/create' => ['permission/create'],
            'permission/edit' => ['permission/edit'],
        ];
    }

    // AK1
    #[DataProvider('kaestchenViews')]
    public function test_ak1_kaestchen_enthalten_kein_rohes_input_checkbox(string $view): void
    {
        $text = $this->quelltext($view);

        $this->assertDoesNotMatchRegularExpression('/<input\b[^>]*\btype\s*=\s*["\']?checkbox/i', $text);
        $this->assertDoesNotMatchRegularExpression('/<input\b[^>]*\bcheckbox\b/i', $text);
    }

    // AK1
    #[DataProvider('kaestchenViews')]
    public function test_ak1_kaestchen_kommen_ueber_x_kit_checkbox(string $view): void
    {
        $this->assertStringContainsString('<x-kit::checkbox', $this->quelltext($view));
    }

    // AK1
    public function test_ak1_kaestchen_erkennung_schlaegt_bei_rohem_input_an(): void
    {
        // Gegenprobe zu den Quelltext-Tests oben
        $this->assertMatchesRegularExpression('/<input\b[^>]*\btype\s*=\s*["\']?checkbox/i', '<input type="checkbox" name="a">');
        $this->assertDoesNotMatchRegularExpression('/<input\b[^>]*\btype\s*=\s*["\']?checkbox/i', '<x-kit::checkbox name="a" />');
    }

    /**
     * Daten eines abgelehnten Rechteformulars (group_display leer) mit zusätzlichen Zeilen.
     *
     * @return array<string, string>
     */
    private function neueZeilen(): array
    {
        return [
            'permission_display_new_1' => 'Eins "<i>x</i>"', 'permission_new_1' => 'eins_intern',
            'permission_display_new_2' => 'Zwei', 'permission_new_2' => 'zwei_intern',
            'permission_display_new_3' => 'Drei & Co', 'permission_new_3' => 'drei_intern',
        ];
    }

    // AK3
    public function test_ak3_recht_anlegen_zeigt_nach_validierungsfehler_alle_per_skript_ergaenzten_zeilen(): void
    {
        $url = route('permission.create');
        $daten = array_merge(['group_display' => '', 'group_name' => 'neue_gruppe', 'ckPermissionGroup' => '1'], $this->neueZeilen());

        $this->actingAs($this->admin)->from($url)->post(route('permission.store'), $daten)->assertSessionHasErrors('group_display');
        $html = $this->actingAs($this->admin)->from($url)->followingRedirects()->post(route('permission.store'), $daten)->assertOk()->getContent();

        foreach ($this->neueZeilen() as $name => $wert) {
            $this->assertSame($wert, $this->wertVon($html, $name), $name.' fehlt oder hat den falschen Wert');
        }
        $this->assertStringNotContainsString('<i>x</i>', $html, 'Wert ist escaped');
    }

    // AK3
    public function test_ak3_recht_anlegen_ergaenzte_zeilen_werden_beim_erneuten_speichern_angelegt(): void
    {
        $url = route('permission.create');
        $daten = array_merge(['group_display' => '', 'group_name' => 'neue_gruppe', 'ckPermissionGroup' => '1'], $this->neueZeilen());
        $this->actingAs($this->admin)->from($url)->post(route('permission.store'), $daten)->assertSessionHasErrors('group_display');
        $html = $this->actingAs($this->admin)->from($url)->followingRedirects()->post(route('permission.store'), $daten)->getContent();

        $felder = array_merge($this->formularFelder($html, route('permission.store')), ['group_display' => 'Neue Gruppe']);
        $this->actingAs($this->admin)->post(route('permission.store'), $this->alsDaten($felder))->assertSessionHasNoErrors();

        $gruppe = DB::table('permissions_groups')->where('group_name', 'neue_gruppe')->first();
        $this->assertNotNull($gruppe);
        $interne = DB::table('permissions')->where('group_id', $gruppe->id)->pluck('permission')->all();
        sort($interne);
        $this->assertSame(['drei_intern', 'eins_intern', 'zwei_intern'], $interne);
    }

    // AK3
    public function test_ak3_recht_anlegen_ohne_ergaenzte_zeilen_bleibt_bei_der_ersten_leeren_zeile(): void
    {
        $url = route('permission.create');
        $daten = ['group_display' => '', 'group_name' => 'x', 'ckPermissionGroup' => '1'];
        $this->actingAs($this->admin)->from($url)->post(route('permission.store'), $daten)->assertSessionHasErrors('group_display');
        $html = $this->actingAs($this->admin)->from($url)->followingRedirects()->post(route('permission.store'), $daten)->getContent();

        $this->assertSame(['permission_display_new_1', 'permission_new_1'], array_values(array_filter(
            array_keys($this->formularFelder($html, route('permission.store'))),
            fn (string $n) => str_contains($n, 'permission') && $n !== 'ckPermissionGroup'
        )));
    }

    // AK3
    public function test_ak3_recht_bearbeiten_zeigt_nach_validierungsfehler_alle_per_skript_ergaenzten_zeilen(): void
    {
        $url = route('permission.edit', 3);
        $idA = $this->rechte['extras_a'];
        $daten = array_merge(['group_display' => '', 'group_name' => 'extras', 'permission_display_'.$idA => 'Geändert', 'permission_'.$idA => 'extras_a'], $this->neueZeilen());

        $this->actingAs($this->admin)->from($url)->post(route('permission.update', 3), $daten)->assertSessionHasErrors('group_display');
        $html = $this->actingAs($this->admin)->from($url)->followingRedirects()->post(route('permission.update', 3), $daten)->assertOk()->getContent();

        foreach ($this->neueZeilen() as $name => $wert) {
            $this->assertSame($wert, $this->wertVon($html, $name), $name.' fehlt oder hat den falschen Wert');
        }
        $this->assertSame('Geändert', $this->wertVon($html, 'permission_display_'.$idA), 'bestehende Zeile behält ihren Wert');
    }

    // AK3
    public function test_ak3_recht_bearbeiten_entfernte_zeile_bleibt_nach_validierungsfehler_entfernt(): void
    {
        $url = route('permission.edit', 3);
        $idA = $this->rechte['extras_a'];
        $idB = $this->rechte['extras_b'];

        // Gegenprobe: ohne Fehler zeigt die Seite beide bestehenden Zeilen als sendende Felder
        $normal = $this->formularFelder($this->actingAs($this->admin)->get($url)->assertOk()->getContent(), route('permission.update', 3));
        $this->assertArrayHasKey('permission_'.$idA, $normal);
        $this->assertArrayHasKey('permission_'.$idB, $normal);

        // So sendet permission.js eine per Löschen-Knopf entfernte Zeile: delete_display_<id>, delete_intern_<id>
        $daten = [
            'group_display' => '', 'group_name' => 'extras',
            'delete_display_'.$idA => 'Recht extras_a', 'delete_intern_'.$idA => 'extras_a',
            'permission_display_'.$idB => 'Recht extras_b', 'permission_'.$idB => 'extras_b',
        ];
        $this->actingAs($this->admin)->from($url)->post(route('permission.update', 3), $daten)->assertSessionHasErrors('group_display');
        $html = $this->actingAs($this->admin)->from($url)->followingRedirects()->post(route('permission.update', 3), $daten)->assertOk()->getContent();

        $felder = $this->formularFelder($html, route('permission.update', 3));
        $this->assertArrayNotHasKey('permission_'.$idA, $felder, 'entfernte Zeile sendet kein Recht mehr');
        $this->assertArrayNotHasKey('permission_display_'.$idA, $felder);
        $this->assertArrayHasKey('permission_'.$idB, $felder, 'die übrige Zeile bleibt');
        $this->assertArrayHasKey('delete_intern_'.$idA, $felder, 'die Löschung bleibt beim erneuten Speichern erhalten');
    }

    // AK3
    public function test_ak3_recht_bearbeiten_entfernte_zeile_wird_beim_erneuten_speichern_geloescht(): void
    {
        $url = route('permission.edit', 3);
        $idA = $this->rechte['extras_a'];
        $idB = $this->rechte['extras_b'];
        $daten = [
            'group_display' => '', 'group_name' => 'extras',
            'delete_display_'.$idA => 'Recht extras_a', 'delete_intern_'.$idA => 'extras_a',
            'permission_display_'.$idB => 'Recht extras_b', 'permission_'.$idB => 'extras_b',
        ];
        $this->actingAs($this->admin)->from($url)->post(route('permission.update', 3), $daten)->assertSessionHasErrors('group_display');
        $html = $this->actingAs($this->admin)->from($url)->followingRedirects()->post(route('permission.update', 3), $daten)->getContent();

        $felder = array_merge($this->formularFelder($html, route('permission.update', 3)), ['group_display' => 'Extras']);
        $this->actingAs($this->admin)->post(route('permission.update', 3), $this->alsDaten($felder))->assertSessionHasNoErrors();

        $this->assertNotNull(DB::table('permissions')->where('id', $idA)->value('deleted_at'), 'Recht ist (soft) gelöscht');
        $this->assertNull(DB::table('permissions')->where('id', $idB)->value('deleted_at'), 'das übrige Recht bleibt');
    }

    // AK3
    public function test_ak3_permission_js_beginnt_die_nummerierung_nicht_fest_bei_1(): void
    {
        $js = (string) file_get_contents(__DIR__.'/../../src/Resources/assets/js/permission.js');

        $this->assertDoesNotMatchRegularExpression('/\b(var|let)\s+newID\s*=\s*1\s*;/', $js, 'Startwert muss aus dem DOM kommen');
        $this->assertMatchesRegularExpression('/\bnewID\s*=[^;]*(\$\(|document\.|querySelector|data\(|getAttribute|attr\()/', $js, 'Startwert von newID kommt aus dem DOM bzw. einem data-Attribut');
    }

    // AK3
    public function test_ak3_permission_js_vergibt_nummer_ueber_dem_startwert(): void
    {
        $js = (string) file_get_contents(__DIR__.'/../../src/Resources/assets/js/permission.js');

        // Gegenprobe: newPermission zählt vor dem Vergeben weiter (newID++), so ist die erste neue Zeile Startwert + 1
        $this->assertMatchesRegularExpression('/function\s+newPermission\s*\(\)\s*\{[^}]*newID\+\+/s', $js);
    }
}
