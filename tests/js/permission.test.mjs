// Tests für src/Resources/assets/js/permission.js (klassisches Browser-Skript, jQuery, globale Funktionen).
// Läuft per `node --test` in einem vm-Kontext mit minimalem DOM-/jQuery-Ersatz (keine npm-Abhängigkeit).
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
        addEventListener() {},
    };

    const feld = (id, name, value = '') => new El('input', { type: 'text', id, name, class: 'form-control input-text', value });
    body.appendChild(feld('group_display', 'group_display', 'Gruppe'));
    body.appendChild(feld('group_name', 'group_name', 'grp'));

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
    tbody.appendChild(new El('tr', { id: 'vorlage', class: 'd-none', nummer: 'x' }, [
        new El('td', { id: 'new-id', scope: 'row', class: 'd-none' }),
        new El('td', {}, [feld('vorlage_display', '')]),
        new El('td', {}, [feld('vorlage_intern', '')]),
        new El('td', {}, [new El('button', { id: 'btnVorlage' })]),
    ]));

    // minimales jQuery
    function J(elemente) {
        const liste = elemente.filter(Boolean);
        const o = {
            length: liste.length,
            find: (s) => J(suche(liste, s)),
            clone: () => J(liste.map((e) => e.cloneNode())),
            each(f) { liste.forEach((e, i) => f.call(e, i, e)); return o; },
            attr(n, v) {
                if (v === undefined) return liste[0]?.getAttribute(n) ?? undefined;
                liste.forEach((e) => e.setAttribute(n, v));
                return o;
            },
            removeAttr: (n) => { liste.forEach((e) => e.removeAttribute(n)); return o; },
            addClass: (c) => { liste.forEach((e) => e.classList.add(c)); return o; },
            removeClass: (c) => { liste.forEach((e) => e.classList.remove(c)); return o; },
            hasClass: (c) => !!liste[0]?.classList.contains(c),
            append(x) {
                liste.forEach((e) => {
                    if (typeof x === 'string') e.textContent += x;
                    else x.toArray().forEach((k) => e.appendChild(k));
                });
                return o;
            },
            val(v) {
                if (v === undefined) return liste[0]?.value;
                liste.forEach((e) => { e.value = String(v); });
                return o;
            },
            text(v) {
                if (v === undefined) return liste[0]?.textContent;
                liste.forEach((e) => { e.textContent = String(v); });
                return o;
            },
            on(t, f) { liste.forEach((e) => e.addEventListener(t, f)); return o; },
            first: () => J(liste.slice(0, 1)),
            eq: (i) => J(liste.slice(i, i + 1)),
            get: (i) => (i === undefined ? liste : liste[i]),
            toArray: () => liste,
            parent: () => J(liste.map((e) => e.parentNode)),
            closest: (s) => J(liste.map((e) => { for (let x = e; x; x = x.parentNode) if (passt(x, s)) return x; return null; })),
            prop: (n, v) => (v === undefined ? liste[0]?.[n] : (liste.forEach((e) => { e[n] = v; }), o)),
            remove: () => { liste.forEach((e) => e.parentNode?.removeChild(e)); return o; },
        };
        liste.forEach((e, i) => { o[i] = e; });
        return o;
    }
    const $ = (x) => {
        if (typeof x === 'string') return J(suche([doc], x));
        if (x instanceof El) return J([x]);
        if (x && typeof x.toArray === 'function') return x;
        return J([]);
    };
    $.fn = {};

    const ctx = vm.createContext({ $, jQuery: $, document: doc, console });
    ctx.window = ctx;
    // Die Skripte laden wie die Views: erst die gemeinsamen Dateien (Namensregel), dann permission.js.
    for (const datei of skripteDerView('permission/create')) {
        vm.runInContext(fs.readFileSync(path.join(jsOrdner, datei), 'utf8'), ctx, { filename: datei });
    }

    // Zeilen aus dem Markup tragen ein inline onchange="editIntern('new-row-<n>')"
    for (const n of zeilenNummern) {
        doc.getElementById(`display_new_${n}`).addEventListener('change', () => ctx.editIntern(`new-row-${n}`));
    }
    return { ctx, doc, tbody };
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
