// Tests für src/Resources/assets/js/permission.js (klassisches Browser-Skript ohne jQuery, globale Funktionen).
// Läuft per `node --test` in einem vm-Kontext mit minimalem DOM-Ersatz (keine npm-Abhängigkeit); `$`/`jQuery` sind nicht
// definiert, jeder Zugriff wird gezählt und wirft (userauth-datatable-ohne-jquery, AK4).
// Das Markup entspricht permission/rechtezeilen.blade.php (Zeilen `new-row-<n>`, Vorlage-Zeile `#vorlage`).
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const hier = path.dirname(fileURLToPath(import.meta.url));
const paketDatei = path.resolve(hier, '../../src/Resources/assets/js/permission.js');
const jsOrdner = path.resolve(hier, '../../src/Resources/assets/js');
const skripteDerView = (view) => {
    const html = fs.readFileSync(path.resolve(hier, '../../src/Resources/views', view + '.blade.php'), 'utf8');
    return [...html.matchAll(/vendor\/userauth\/js\/([\w.\-]+\.js)/g)].map((m) => m[1]);
};
const hostDatei = path.resolve(hier, '../../../../public/vendor/userauth/js/permission.js');

// ---------- minimaler DOM ----------
class El {
    constructor(tag, attrs = {}, kinder = []) {
        this.tagName = tag.toUpperCase();
        this.attrs = {};
        this.children = [];
        this.parentNode = null;
        this.listeners = {};
        this.value = '';
        this.textContent = '';
        for (const [k, v] of Object.entries(attrs)) this.attrs[k] = String(v);
        if ('value' in attrs) this.value = String(attrs.value);
        kinder.forEach((k) => this.appendChild(k));
        const self = this;
        this.classList = {
            liste: () => (self.attrs.class ?? '').split(/\s+/).filter(Boolean),
            add: (...n) => { const l = self.classList.liste(); n.forEach((x) => l.includes(x) || l.push(x)); self.attrs.class = l.join(' '); },
            remove: (...n) => { self.attrs.class = self.classList.liste().filter((x) => !n.includes(x)).join(' '); },
            contains: (n) => self.classList.liste().includes(n),
            toggle: (n, erzwingen) => {
                const an = erzwingen ?? !self.classList.contains(n);
                an ? self.classList.add(n) : self.classList.remove(n);
                return an;
            },
        };
    }
    get id() { return this.attrs.id ?? ''; }
    set id(v) { this.attrs.id = String(v); }
    get parentElement() { return this.parentNode; }
    getAttribute(n) { return n in this.attrs ? this.attrs[n] : null; }
    setAttribute(n, v) { this.attrs[n] = String(v); }
    hasAttribute(n) { return n in this.attrs; }
    removeAttribute(n) { delete this.attrs[n]; }
    appendChild(k) { if (k.parentNode) k.parentNode.removeChild(k); k.parentNode = this; this.children.push(k); return k; }
    removeChild(k) { this.children = this.children.filter((x) => x !== k); k.parentNode = null; return k; }
    addEventListener(t, f) { (this.listeners[t] ??= []).push(f); }
    removeEventListener(t, f) { this.listeners[t] = (this.listeners[t] ?? []).filter((x) => x !== f); }
    dispatchEvent(ev) {
        ev.target ??= this;
        for (let el = this; el; el = ev.bubbles ? el.parentNode : null) {
            for (const f of [...(el.listeners[ev.type] ?? [])]) f.call(el, ev);
        }
        return true;
    }
    get name() { return this.attrs.name ?? ''; }
    set name(v) { this.attrs.name = String(v); }
    get className() { return this.attrs.class ?? ''; }
    set className(v) { this.attrs.class = String(v); }
    get firstElementChild() { return this.children[0] ?? null; }
    append(...xs) { xs.forEach((x) => (typeof x === 'string' ? (this.textContent += x) : this.appendChild(x))); }
    remove() { this.parentNode?.removeChild(this); }
    matches(s) { return s.split(',').some((t) => passt(this, t.trim())); }
    closest(s) { for (let x = this; x; x = x.parentNode) if (x.matches(s)) return x; return null; }
    querySelectorAll(s) { return suche([this], s); }
    querySelector(s) { return suche([this], s)[0] ?? null; }
    nachfahren() { return this.children.flatMap((k) => [k, ...k.nachfahren()]); }
    cloneNode() {
        const c = new El(this.tagName, { ...this.attrs }, this.children.map((k) => k.cloneNode()));
        c.value = this.value;
        c.textContent = this.textContent;
        return c;
    }
}

function passt(el, einfach) {
    let rest = einfach;
    while (rest) {
        let m;
        if ((m = rest.match(/^\[([\w-]+)(?:([\^$*]?)="([^"]*)")?\]/))) {
            if (!el.hasAttribute(m[1])) return false;
            const ist = el.getAttribute(m[1]);
            if (m[3] !== undefined) {
                if (m[2] === '^' ? !ist.startsWith(m[3]) : m[2] === '$' ? !ist.endsWith(m[3]) : m[2] === '*' ? !ist.includes(m[3]) : ist !== m[3]) return false;
            }
        } else if ((m = rest.match(/^#([\w-]+)/))) {
            if (el.id !== m[1]) return false;
        } else if ((m = rest.match(/^\.([\w-]+)/))) {
            if (!el.classList.contains(m[1])) return false;
        } else if ((m = rest.match(/^([a-zA-Z][\w-]*)/))) {
            if (el.tagName !== m[1].toUpperCase()) return false;
        } else {
            throw new Error('Selektor nicht unterstützt: ' + einfach);
        }
        rest = rest.slice(m[0].length);
    }
    return true;
}

function suche(wurzeln, selektor) {
    const treffer = [];
    for (const teil of selektor.split(',')) {
        const kette = teil.trim().match(/(?:[^\s\[]|\[[^\]]*\])+/g);
        let kandidaten = wurzeln;
        kette.forEach((einfach, i) => {
            const pool = kandidaten.flatMap((w) => (i === 0 && wurzeln === kandidaten && w.isDoc ? w.nachfahren() : w.nachfahren()));
            kandidaten = pool.filter((e) => passt(e, einfach));
        });
        kandidaten.forEach((e) => treffer.includes(e) || treffer.push(e));
    }
    return treffer;
}

function baueKontext(zeilenNummern) {
    const html = new El('html');
    const body = new El('body');
    html.appendChild(body);
    const doc = {
        isDoc: true,
        body,
        documentElement: html,
        nachfahren: () => html.nachfahren(),
        getElementById: (id) => html.nachfahren().find((e) => e.id === id) ?? null,
        createElement: (t) => new El(t),
        listeners: {},
        readyState: 'interactive',
        addEventListener(t, f) { (doc.listeners[t] ??= []).push(f); },
        querySelectorAll: (sel) => suche([doc], sel),
        querySelector: (sel) => suche([doc], sel)[0] ?? null,
    };

    const feld = (id, name, value = '') => new El('input', { type: 'text', id, name, class: 'form-control input-text', value });
    body.appendChild(feld('group_display', 'group_display', 'Gruppe'));
    body.appendChild(feld('group_name', 'group_name', 'grp'));
    const checkbox = new El('input', { type: 'checkbox', name: 'ckPermissionGroup' });
    checkbox.checked = false;
    body.appendChild(checkbox);
    body.appendChild(new El('div', { id: 'group_permissions_show', class: 'd-none' }));

    const tbody = new El('tbody', { id: 'tbody' });
    body.appendChild(new El('table', {}, [tbody]));
    for (const n of zeilenNummern) {
        tbody.appendChild(new El('tr', { id: `new-row-${n}`, nummer: n }, [
            new El('td', { scope: 'row', class: 'd-none' }),
            new El('td', {}, [feld(`display_new_${n}`, `permission_display_new_${n}`)]),
            new El('td', {}, [feld(`intern_new_${n}`, `permission_new_${n}`)]),
            new El('td', {}, [new El('button', { onclick: `deletePermission('new-row-${n}')` })]),
        ]));
    }
    // vorhandene (gespeicherte) Zeile aus rechtezeilen.blade.php
    tbody.appendChild(new El('tr', { id: 'perm-7', nummer: 7 }, [
        new El('td', {}, [feld('display_7', 'permission_display_7', 'Sieben')]),
        new El('td', {}, [feld('intern_7', 'permission_7', 'grp_sieben')]),
    ]));
    tbody.appendChild(new El('tr', { id: 'vorlage', class: 'd-none', nummer: 'x' }, [
        new El('td', { id: 'new-id', scope: 'row', class: 'd-none' }),
        new El('td', {}, [feld('vorlage_display', '')]),
        new El('td', {}, [feld('vorlage_intern', '')]),
        new El('td', {}, [new El('button', { id: 'btnVorlage' })]),
    ]));

    // Der Browser ohne jQuery: jeder Zugriff auf `$` oder `jQuery` (auch `typeof`) wird gezählt und wirft (AK4).
    const jqueryZugriffe = [];
    const ctx = vm.createContext({ document: doc, console });
    ctx.window = ctx;
    for (const name of ['$', 'jQuery']) {
        Object.defineProperty(ctx, name, {
            configurable: true,
            get() {
                jqueryZugriffe.push(name);
                throw new ReferenceError(`${name} is not defined`);
            },
        });
    }
    // Die Skripte laden wie die Views: erst die gemeinsamen Dateien (Namensregel), dann permission.js.
    for (const datei of skripteDerView('permission/create')) {
        vm.runInContext(fs.readFileSync(path.join(jsOrdner, datei), 'utf8'), ctx, { filename: datei });
    }
    // defer-Skript: DOMContentLoaded steht noch aus, danach feuern die registrierten Listener.
    doc.listeners.DOMContentLoaded?.forEach((f) => f({ type: 'DOMContentLoaded' }));

    // Zeilen aus dem Markup tragen ein inline onchange="editIntern('new-row-<n>')"
    for (const n of zeilenNummern) {
        doc.getElementById(`display_new_${n}`).addEventListener('change', () => ctx.editIntern(`new-row-${n}`));
    }
    return { ctx, doc, tbody, jqueryZugriffe };
}

const aendern = (doc, id, wert) => {
    const el = doc.getElementById(id);
    el.value = wert;
    el.dispatchEvent({ type: 'change', bubbles: true });
};
const zeile = (tbody, n) => tbody.children.find((z) => z.id === `new-row-${n}`);
const idsIn = (el) => [el, ...el.nachfahren()].map((e) => e.id).filter(Boolean);
const internWert = (doc, n) => doc.getElementById(`intern_new_${n}`).value;

function dreiErgaenzen(zeilenNummern = [1]) {
    const k = baueKontext(zeilenNummern);
    for (let i = 0; i < 3; i++) k.ctx.newPermission();
    return k;
}

// AK1
test('AK1: Änderung der Anzeige der ersten ergänzten Zeile füllt nur deren internen Namen', () => {
    const { doc } = dreiErgaenzen();
    ['intern_new_1', 'intern_new_3', 'intern_new_4'].forEach((id) => { doc.getElementById(id).value = 'vorher_' + id; });
    aendern(doc, 'display_new_2', 'Mein Recht');
    assert.equal(internWert(doc, 2), 'grp_mein_recht', 'Zeile 2 bildet ihren internen Namen aus ihrer Anzeige');
    assert.equal(internWert(doc, 1), 'vorher_intern_new_1');
    assert.equal(internWert(doc, 3), 'vorher_intern_new_3', 'Zeile 3 bleibt unverändert');
    assert.equal(internWert(doc, 4), 'vorher_intern_new_4', 'Zeile 4 bleibt unverändert');
});

// AK1
test('AK1: Änderung der Anzeige der mittleren ergänzten Zeile füllt nur deren internen Namen', () => {
    const { doc } = dreiErgaenzen();
    ['intern_new_1', 'intern_new_2', 'intern_new_4'].forEach((id) => { doc.getElementById(id).value = 'vorher_' + id; });
    aendern(doc, 'display_new_3', 'Rechte lesen');
    assert.equal(internWert(doc, 3), 'grp_rechte_lesen', 'Zeile 3 bildet ihren internen Namen aus ihrer Anzeige');
    assert.equal(internWert(doc, 1), 'vorher_intern_new_1');
    assert.equal(internWert(doc, 2), 'vorher_intern_new_2', 'Zeile 2 bleibt unverändert');
    assert.equal(internWert(doc, 4), 'vorher_intern_new_4', 'Zeile 4 bleibt unverändert');
});

// AK1
test('AK1: Änderung der Anzeige der letzten ergänzten Zeile füllt weiter deren internen Namen', () => {
    const { doc } = dreiErgaenzen();
    ['intern_new_1', 'intern_new_2', 'intern_new_3'].forEach((id) => { doc.getElementById(id).value = 'vorher_' + id; });
    aendern(doc, 'display_new_4', 'Löschen');
    assert.equal(internWert(doc, 4), 'grp_loeschen');
    ['1', '2', '3'].forEach((n) => assert.equal(internWert(doc, n), 'vorher_intern_new_' + n));
});

// AK2
test('AK2: In jeder ergänzten Zeile kommt jede id nur einmal vor', () => {
    const { tbody } = dreiErgaenzen();
    for (const n of [2, 3, 4]) {
        const z = zeile(tbody, n);
        assert.ok(z, `Zeile new-row-${n} vorhanden`);
        const ids = idsIn(z);
        assert.ok(ids.length >= 3, 'Zeile trägt ids');
        assert.deepEqual([...new Set(ids)].sort(), [...ids].sort(), `ids in Zeile ${n} doppelt: ${ids.join(', ')}`);
    }
});

// AK2
test('AK2: ids sind auch über alle Zeilen hinweg eindeutig (Vorlage mit Ausnahme der eigenen ids)', () => {
    const { tbody } = dreiErgaenzen();
    const alle = [2, 3, 4].flatMap((n) => idsIn(zeile(tbody, n)));
    assert.deepEqual([...new Set(alle)].sort(), [...alle].sort(), 'doppelte id zwischen Zeilen');
});

// AK2
test('AK2: Eingabefeld der Anzeige trägt display_new_<n>, das des internen Namens intern_new_<n>', () => {
    const { tbody } = dreiErgaenzen();
    for (const n of [2, 3, 4]) {
        const z = zeile(tbody, n);
        const eingaben = z.nachfahren().filter((e) => e.tagName === 'INPUT');
        const anzeige = eingaben.find((e) => e.getAttribute('name') === `permission_display_new_${n}`);
        const intern = eingaben.find((e) => e.getAttribute('name') === `permission_new_${n}`);
        assert.ok(anzeige && intern, `Eingabefelder in Zeile ${n}`);
        assert.equal(anzeige.id, `display_new_${n}`);
        assert.equal(intern.id, `intern_new_${n}`);
        // getElementById findet genau dieses Feld
        assert.equal(z.nachfahren().filter((e) => e.id === `display_new_${n}`).length, 1);
    }
});

// Randfall
test('Randfall: Zeilen aus old() (Nummern 1 und 3) behalten ihr Verhalten', () => {
    const { doc } = baueKontext([1, 3]);
    doc.getElementById('intern_new_1').value = 'v1';
    doc.getElementById('intern_new_3').value = 'v3';
    aendern(doc, 'display_new_3', 'Test Recht');
    assert.equal(internWert(doc, 3), 'grp_test_recht');
    assert.equal(internWert(doc, 1), 'v1', 'Zeile 1 unverändert');
    aendern(doc, 'display_new_1', 'Anderes');
    assert.equal(internWert(doc, 1), 'grp_anderes');
    assert.equal(internWert(doc, 3), 'grp_test_recht', 'Zeile 3 unverändert');
});

// Randfall
test('Randfall: neu ergänzte Zeilen nach old()-Zeilen bekommen freie Nummern und füllen ihre eigene Zeile', () => {
    const { ctx, doc, tbody } = baueKontext([1, 3]);
    ctx.newPermission();
    ctx.newPermission();
    assert.ok(zeile(tbody, 4), 'erste ergänzte Zeile hat Nummer 4');
    assert.ok(zeile(tbody, 5), 'zweite ergänzte Zeile hat Nummer 5');
    assert.equal(tbody.children.filter((z) => z.id === 'new-row-3').length, 1, 'Nummer 3 nicht doppelt vergeben');
    doc.getElementById('intern_new_5').value = 'v5';
    aendern(doc, 'display_new_4', 'Vier');
    assert.equal(internWert(doc, 4), 'grp_vier');
    assert.equal(internWert(doc, 5), 'v5', 'Zeile 5 unverändert');
    assert.equal(internWert(doc, 3), '', 'old()-Zeile unverändert');
});

// AK3
test('AK3: public/vendor/userauth/js/permission.js im Host ist gleich der Paketdatei', () => {
    assert.ok(fs.existsSync(hostDatei), 'Host-Kopie fehlt: ' + hostDatei);
    assert.equal(fs.readFileSync(hostDatei, 'utf8'), fs.readFileSync(paketDatei, 'utf8'));
});

// AK4
test('AK4: permission.js ruft weder `$` noch `jQuery` auf (Gegenprobe: die Falle zählt jeden Zugriff)', () => {
    const { ctx, jqueryZugriffe } = baueKontext([1]);
    ctx.newPermission();
    ctx.deletePermission('perm-7');
    ctx.editIntern('perm-7');
    ctx.showSinglePermissions();
    assert.equal(typeof ctx.newPermission, 'function', 'Aufbau: Skript ist geladen');
    assert.deepEqual(jqueryZugriffe, [], 'Zugriffe auf $/jQuery');

    // Gegenprobe: ein Skript, das `$` nutzt, löst die Falle aus.
    assert.throws(() => vm.runInContext("$('x')", ctx), { name: 'ReferenceError' });
    assert.deepEqual(jqueryZugriffe, ['$'], 'Aufbau: die Falle erkennt Zugriffe');
});

// AK4
test('AK4: Änderung von group_display setzt group_name', () => {
    const { doc } = baueKontext([]);
    assert.equal(doc.getElementById('group_name').value, 'grp', 'Aufbau: Startwert');
    aendern(doc, 'group_display', 'Meine Gruppe');
    assert.equal(doc.getElementById('group_name').value, 'meine_gruppe');
});

// AK4
test('AK4: showSinglePermissions blendet #group_permissions_show je nach Checkbox mit d-none aus und ein', () => {
    const { ctx, doc } = baueKontext([]);
    const box = doc.querySelector('input[name="ckPermissionGroup"]');
    const bereich = doc.getElementById('group_permissions_show');
    assert.ok(bereich.classList.contains('d-none'), 'Aufbau: Bereich startet ausgeblendet');
    box.checked = true;
    ctx.showSinglePermissions();
    assert.ok(!bereich.classList.contains('d-none'), 'sichtbar bei gesetzter Checkbox');
    box.checked = false;
    ctx.showSinglePermissions();
    assert.ok(bereich.classList.contains('d-none'), 'wieder ausgeblendet');
});

// AK4
test('AK4: newPermission klont die Vorlage: sichtbar, Nummer, Namen, Löschen-Button', () => {
    const { ctx, doc, tbody } = baueKontext([1]);
    ctx.newPermission();
    const z = zeile(tbody, 2);
    assert.ok(z, 'Zeile new-row-2 steht im tbody');
    assert.ok(!z.classList.contains('d-none'), 'Zeile ist sichtbar');
    assert.equal(z.getAttribute('nummer'), '2');
    assert.equal(doc.getElementById('new-id-2').textContent, 'new_2');
    assert.equal(doc.getElementById('display_new_2').getAttribute('name'), 'permission_display_new_2');
    assert.equal(doc.getElementById('intern_new_2').getAttribute('name'), 'permission_new_2');
    const knopf = z.nachfahren().find((e) => e.tagName === 'BUTTON');
    assert.equal(knopf.getAttribute('onclick'), "deletePermission('new-row-2')");
    assert.ok(!knopf.hasAttribute('id'), 'id btnVorlage entfernt');
    assert.ok(doc.getElementById('vorlage').classList.contains('d-none'), 'Vorlage bleibt unverändert ausgeblendet');
});

// AK4
test('AK4: deletePermission entfernt neue Zeilen und blendet vorhandene aus (delete_*-Namen)', () => {
    const { ctx, doc, tbody } = baueKontext([1]);
    assert.ok(zeile(tbody, 1), 'Aufbau: neue Zeile da');
    ctx.deletePermission('new-row-1');
    assert.equal(zeile(tbody, 1), undefined, 'neue Zeile entfernt');

    const alt = doc.getElementById('perm-7');
    assert.ok(!alt.classList.contains('d-none'), 'Aufbau: vorhandene Zeile sichtbar');
    ctx.deletePermission('perm-7');
    assert.ok(doc.getElementById('perm-7'), 'vorhandene Zeile bleibt im DOM');
    assert.ok(alt.classList.contains('d-none'));
    assert.equal(doc.getElementById('display_7').getAttribute('name'), 'delete_display_7');
    assert.equal(doc.getElementById('intern_7').getAttribute('name'), 'delete_intern_7');
});

// AK4
test('AK4: editIntern bildet den internen Namen einer vorhandenen Zeile aus group_name und Anzeige', () => {
    const { ctx, doc } = baueKontext([]);
    doc.getElementById('display_7').value = 'Neues Recht';
    ctx.editIntern('perm-7');
    assert.equal(doc.getElementById('intern_7').value, 'grp_neues_recht');
});
