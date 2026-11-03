// Tests für die Namensregel im Browser (userauth-rechtenamen, AK2, Randfall 1): permission.js und role.js bilden den
// internen Namen wie der Server. Die Beispiele stehen in tests/Fixtures/rechtenamen.json (dieselbe Tabelle wie der PHP-Test).
// Welche Skriptdateien ein Formular lädt, steht in den Views (Reihenfolge der <script src="…vendor/userauth/js/…">);
// der Ort der gemeinsamen Datei ist nicht festgelegt. Läuft per `node --test` in einem vm-Kontext mit minimalem DOM.
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const hier = path.dirname(fileURLToPath(import.meta.url));
const jsOrdner = path.resolve(hier, '../../src/Resources/assets/js');
const viewOrdner = path.resolve(hier, '../../src/Resources/views');
const tabelle = JSON.parse(fs.readFileSync(path.resolve(hier, '../Fixtures/rechtenamen.json'), 'utf8'));
const beispiele = tabelle.beispiele;
const weitere = tabelle.weitere;
const alle = [...beispiele, ...weitere];

const views = {
    'permission/create': 'permission.js',
    'permission/edit': 'permission.js',
    'role/create': 'role.js',
    'role/edit': 'role.js',
};

const skripteDerView = (view) => {
    const html = fs.readFileSync(path.join(viewOrdner, view + '.blade.php'), 'utf8');
    return [...html.matchAll(/vendor\/userauth\/js\/([\w.\-]+\.js)/g)].map((m) => m[1]);
};

// ---------- Kontext mit minimalem DOM/jQuery ----------
function baueKontext(dateien, { gruppenname = 'grp', anzeigeZeile = undefined } = {}) {
    const element = (wert = '') => ({ value: wert, listeners: {}, addEventListener(t, f) { (this.listeners[t] ??= []).push(f); } });
    const el = {
        group_display: element(),
        group_name: element(gruppenname),
        role_display: element(),
        role: element(),
    };
    const zeile = { value: anzeigeZeile };
    const intern = { value: '' };

    const leer = () => new Proxy(function () {}, { get: (_, k) => (k === 'length' ? 0 : k === Symbol.toPrimitive ? () => '' : leer()), apply: () => leer() });
    const wrapper = (liste) => Object.assign([...liste], {
        find: () => ({ each() {} }),
        val: (v) => {
            if (v === undefined) return liste[0]?.value;
            liste.forEach((e) => { e.value = v; });
            return undefined;
        },
        attr: () => '1',
        addClass: () => leer(),
        removeClass: () => leer(),
    });
    const $ = (sel) => {
        if (sel === 'input[name="group_display"]') return wrapper([el.group_display]);
        if (sel === 'input[name="group_name"]') return wrapper([el.group_name]);
        if (sel === '#display_new_1') return wrapper([zeile]);
        if (sel === '#intern_new_1') return wrapper([intern]);
        if (sel === '#new-row-1') return wrapper([{ value: '' }]);
        return wrapper([]);
    };
    const document = {
        getElementById: (id) => el[id] ?? null,
        querySelector: () => null,
        querySelectorAll: () => [],
        addEventListener() {},
    };
    const ctx = vm.createContext({ $, jQuery: $, document, console });
    ctx.window = ctx;
    for (const datei of dateien) {
        vm.runInContext(fs.readFileSync(path.join(jsOrdner, datei), 'utf8'), ctx, { filename: datei });
    }
    return { ctx, el, zeile, intern };
}

// Rollenformular: Anzeige setzen, setInternValue() aufrufen (wie das Formular) und den Namen lesen.
function rolleBildet(view, anzeige) {
    const k = baueKontext(skripteDerView(view));
    k.el.role_display.value = anzeige;
    k.ctx.setInternValue();
    return k.el.role.value;
}

// Rechtegruppe: change auf group_display feuern, group_name lesen.
function gruppeBildet(view, anzeige) {
    const k = baueKontext(skripteDerView(view));
    k.el.group_display.value = anzeige;
    for (const f of k.el.group_display.listeners.change ?? []) f.call(k.el.group_display, { type: 'change' });
    return k.el.group_name.value;
}

// Einzelrecht: editIntern() einer neuen Zeile.
function einzelrechtBildet(view, anzeige, gruppenname = 'grp') {
    const k = baueKontext(skripteDerView(view), { gruppenname, anzeigeZeile: anzeige });
    k.ctx.editIntern('new-row-1');
    return k.intern.value;
}

// ---------- AK2: derselbe Name wie der Server ----------
// AK2
test('AK2: Rollenformular bildet für jede Beispiel-Anzeige aus AK1 denselben Namen wie der Server', () => {
    for (const b of beispiele) {
        for (const view of ['role/create', 'role/edit']) {
            assert.equal(rolleBildet(view, b.anzeige), b.intern, `${view}: ${JSON.stringify(b.anzeige)}`);
        }
    }
});

// AK2
test('AK2: Rechtegruppen-Formular bildet für jede Beispiel-Anzeige aus AK1 denselben Gruppennamen wie der Server', () => {
    for (const b of beispiele) {
        for (const view of ['permission/create', 'permission/edit']) {
            assert.equal(gruppeBildet(view, b.anzeige), b.intern, `${view}: ${JSON.stringify(b.anzeige)}`);
        }
    }
});

// AK2
test('AK2: Einzelrecht-Zeile bildet für jede Beispiel-Anzeige aus AK1 den Namen wie der Server mit Gruppenpräfix', () => {
    for (const b of beispiele) {
        for (const view of ['permission/create', 'permission/edit']) {
            assert.equal(einzelrechtBildet(view, b.anzeige), 'grp_' + b.intern, `${view}: ${JSON.stringify(b.anzeige)}`);
        }
    }
});

// AK2
test('AK2: weitere Eingaben (Leerraum, Entfernungszeichen, Umlaut-Reihenfolge) ergeben in Rolle, Gruppe und Einzelrecht denselben Namen wie der Server', () => {
    for (const b of weitere) {
        const msg = JSON.stringify(b.anzeige);
        assert.equal(rolleBildet('role/create', b.anzeige), b.intern, `Rolle: ${msg}`);
        assert.equal(gruppeBildet('permission/create', b.anzeige), b.intern, `Gruppe: ${msg}`);
        assert.equal(einzelrechtBildet('permission/create', b.anzeige), 'grp_' + b.intern, `Einzelrecht: ${msg}`);
    }
});

// AK2
test('AK2: ein schon gebildeter Name bleibt in jedem Formular unverändert (zweimal bilden = einmal bilden)', () => {
    for (const b of alle) {
        const einmal = rolleBildet('role/create', b.anzeige);
        assert.equal(einmal, b.intern);
        assert.equal(rolleBildet('role/create', einmal), einmal, `Rolle: ${b.anzeige}`);
        assert.equal(gruppeBildet('permission/create', einmal), einmal, `Gruppe: ${b.anzeige}`);
        assert.equal(einzelrechtBildet('permission/create', einmal), 'grp_' + einmal, `Einzelrecht: ${b.anzeige}`);
    }
});

// ---------- AK2: genau eine Stelle ----------
// AK2
test('AK2: alle vier Formulare laden dieselbe gemeinsame Datei vor ihrem eigenen Skript', () => {
    let gemeinsam = null;
    for (const [view, eigenes] of Object.entries(views)) {
        const liste = skripteDerView(view);
        const pos = liste.indexOf(eigenes);
        assert.ok(pos >= 0, `${view} lädt ${eigenes}`);
        const davor = liste.slice(0, pos).filter((d) => d !== eigenes);
        assert.ok(davor.length >= 1, `${view}: keine gemeinsame Datei vor ${eigenes} (Skripte: ${liste.join(', ')})`);
        const ziel = davor[davor.length - 1];
        gemeinsam ??= ziel;
        assert.equal(ziel, gemeinsam, `${view} lädt eine andere gemeinsame Datei`);
        assert.ok(fs.existsSync(path.join(jsOrdner, ziel)), `${ziel} liegt unter src/Resources/assets/js/`);
    }
    assert.notEqual(gemeinsam, 'permission.js');
    assert.notEqual(gemeinsam, 'role.js');
});

// AK2
test('AK2: permission.js und role.js enthalten keine eigene Kopie der Regel', () => {
    for (const datei of ['permission.js', 'role.js']) {
        const quelle = fs.readFileSync(path.join(jsOrdner, datei), 'utf8');
        assert.doesNotMatch(quelle, /toLowerCase|toLocaleLowerCase/, `${datei} kleinschreibt selbst`);
        assert.doesNotMatch(quelle, /function\s+formatDisplayToIntern\s*\(/, `${datei} definiert eine eigene formatDisplayToIntern`);
        assert.doesNotMatch(quelle, /replace\(\s*['"`]ä['"`]/, `${datei} ersetzt Umlaute selbst`);
    }
});

// AK2
test('AK2: genau eine Datei unter assets/js bildet Kleinbuchstaben (eine gemeinsame Funktion)', () => {
    const treffer = fs.readdirSync(jsOrdner).filter((d) => d.endsWith('.js'))
        .filter((d) => /toLowerCase|toLocaleLowerCase/.test(fs.readFileSync(path.join(jsOrdner, d), 'utf8')));
    assert.equal(treffer.length, 1, `Dateien mit eigener Kleinschreib-Regel: ${treffer.join(', ')}`);
});

// AK2
test('AK2: role.js allein (ohne die gemeinsame Datei) bildet den Namen nicht selbst', () => {
    // Gegenprobe: mit der gemeinsamen Datei, wie die View sie lädt, entsteht der Name
    assert.equal(rolleBildet('role/create', 'Bericht Lesen'), 'bericht_lesen');

    const k = baueKontext(['role.js']);
    k.el.role_display.value = 'Bericht Lesen';
    try { k.ctx.setInternValue(); } catch { /* fehlende Funktion ist erwartet */ }
    assert.notEqual(k.el.role.value, 'bericht_lesen');
});

// AK2
test('AK2: permission.js allein (ohne die gemeinsame Datei) bildet den Namen nicht selbst', () => {
    assert.equal(gruppeBildet('permission/create', 'Bericht Lesen'), 'bericht_lesen');

    const k = baueKontext(['permission.js']);
    k.el.group_display.value = 'Bericht Lesen';
    try {
        for (const f of k.el.group_display.listeners.change ?? []) f.call(k.el.group_display, { type: 'change' });
    } catch { /* fehlende Funktion ist erwartet */ }
    assert.notEqual(k.el.group_name.value, 'bericht_lesen');
});

// ---------- Randfall 1: null/fehlend ----------
const rolleLeer = (wert) => {
    for (const view of ['role/create', 'role/edit']) {
        const k = baueKontext(skripteDerView(view));
        k.el.role.value = 'vorher';
        k.el.role_display.value = wert;
        assert.doesNotThrow(() => k.ctx.setInternValue());
        assert.equal(k.el.role.value, '');
    }
    // Gegenprobe: derselbe Aufbau bildet mit Anzeige einen Namen
    assert.equal(rolleBildet('role/create', 'Rolle A'), 'rolle_a');
};

const gruppeLeer = (wert) => {
    for (const view of ['permission/create', 'permission/edit']) {
        const k = baueKontext(skripteDerView(view));
        k.el.group_name.value = 'vorher';
        k.el.group_display.value = wert;
        assert.ok((k.el.group_display.listeners.change ?? []).length > 0, 'Listener auf group_display');
        assert.doesNotThrow(() => { for (const f of k.el.group_display.listeners.change) f.call(k.el.group_display, { type: 'change' }); });
        assert.equal(k.el.group_name.value, '');
    }
    assert.equal(gruppeBildet('permission/create', 'Gruppe A'), 'gruppe_a');
};

const zeileLeer = (wert) => {
    for (const view of ['permission/create', 'permission/edit']) {
        const k = baueKontext(skripteDerView(view), { anzeigeZeile: wert });
        assert.doesNotThrow(() => k.ctx.editIntern('new-row-1'));
        assert.equal(k.intern.value, 'grp_');
    }
    assert.equal(einzelrechtBildet('permission/create', 'Recht A'), 'grp_recht_a');
};

// Randfall 1
test('Randfall 1: Rolle mit Anzeige null ergibt leeren Namen ohne JS-Fehler', () => rolleLeer(null));
// Randfall 1
test('Randfall 1: Rolle mit Anzeige undefined ergibt leeren Namen ohne JS-Fehler', () => rolleLeer(undefined));
// Randfall 1
test('Randfall 1: Rechtegruppe mit Anzeige null ergibt leeren Gruppennamen ohne JS-Fehler', () => gruppeLeer(null));
// Randfall 1
test('Randfall 1: Rechtegruppe mit Anzeige undefined ergibt leeren Gruppennamen ohne JS-Fehler', () => gruppeLeer(undefined));
// Randfall 1
test('Randfall 1: Einzelrecht-Zeile mit Anzeige null ergibt nur den Gruppenpräfix ohne JS-Fehler', () => zeileLeer(null));
// Randfall 1
test('Randfall 1: Einzelrecht-Zeile mit Anzeige undefined ergibt nur den Gruppenpräfix ohne JS-Fehler', () => zeileLeer(undefined));
