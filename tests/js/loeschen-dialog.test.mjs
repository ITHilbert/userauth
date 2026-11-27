// Verhalten des Lösch-Dialogs der Verwaltungslisten (AK8, loesch-dialog-kit-component #2449).
// Geprüft wird das echte Kit-Modul `initLoeschDialog` (laravel-kit js/loesch-dialog.js, das userauth über die Asset-Route
// `userauth.assets.kit.loesch-dialog` ausliefert) gegen das Fake-DOM des Kits mit dem Markup der Component (Theme kern).
// Pfad: im Host-Repo liegt das Kit unter packages/laravel-kit, daneben packages/userauth.
// Escape kommt als keydown (key 'Escape') am document an.
import assert from 'node:assert/strict';
import { beforeEach, describe, test } from 'node:test';
import { FakeEvent, h, installiere } from '../../../laravel-kit/tests/js/fake-dom.mjs';

let doc;

// Import je Test, damit ein fehlendes Modul den einzelnen Test rot macht.
const laden = () => import('../../../laravel-kit/js/loesch-dialog.js');

const ROUTE = 'http://host.test/user/delete/0';
const knopf = (id) => h('button', { 'data-kit-delete': id });
const escape = () => doc.dispatchEvent(new FakeEvent('keydown', { key: 'Escape' }));

// Markup wie <x-kit::dialog-delete> im Theme kern, danach das Formular #formDelete.
function baue(route = ROUTE) {
    const gesendet = [];
    const formular = h('form', { id: 'formDelete', method: 'POST' });
    formular.submit = () => gesendet.push(formular.getAttribute('action'));
    const overlay = h('div', { class: 'kit-dialog__overlay', 'data-kit-delete-abbrechen': '' });
    const abbrechen = h('button', { type: 'button', class: 'kit-button', 'data-variant': 'cancel', 'data-kit-delete-abbrechen': '' }, 'Abbrechen');
    const bestaetigen = h('button', { type: 'button', class: 'kit-button', 'data-variant': 'delete', 'data-kit-delete-bestaetigen': '' }, 'Löschen');
    const wurzel = h(
        'div',
        { class: 'kit-dialog', 'data-kit-delete-dialog': '', 'data-route': route, hidden: '' },
        overlay,
        h('div', { class: 'kit-dialog__content', role: 'dialog', 'aria-modal': 'true' }, h('h2', { class: 'kit-dialog__title' }, 'Benutzer löschen'), h('p', {}, 'Text'), abbrechen, bestaetigen),
    );
    doc.body.append(wurzel, formular);
    return { wurzel, formular, abbrechen, bestaetigen, overlay, gesendet };
}

beforeEach(() => {
    doc = installiere();
});

describe('Lösch-Dialog der Verwaltung (Kit-Modul)', () => {
    // AK8
    test('Klick auf [data-kit-delete="5"] öffnet, Löschen setzt die Route mit ID 5 und sendet einmal', async () => {
        const { initLoeschDialog } = await laden();
        const d = baue();
        const b = knopf('5');
        doc.body.append(b);
        initLoeschDialog(doc);

        assert.equal(d.wurzel.hidden, true, 'Gegenprobe: vor dem Klick zu');
        b.click();
        assert.equal(d.wurzel.hidden, false, 'Dialog ist nach dem Klick offen');
        assert.deepEqual(d.gesendet, [], 'noch nichts gesendet');

        d.bestaetigen.click();
        assert.equal(d.formular.getAttribute('action'), 'http://host.test/user/delete/5');
        assert.deepEqual(d.gesendet, ['http://host.test/user/delete/5']);
        assert.equal(d.wurzel.hidden, true, 'Dialog ist nach dem Senden zu');

        d.bestaetigen.click();
        assert.equal(d.gesendet.length, 1, 'ein zweiter Klick auf „Löschen“ sendet nicht noch einmal');
    });

    // AK8
    for (const weg of ['abbrechen', 'overlay', 'escape']) {
        test(`${weg} schließt ohne zu senden`, async () => {
            const { initLoeschDialog } = await laden();
            const d = baue();
            const b = knopf('5');
            doc.body.append(b);
            initLoeschDialog(doc);

            b.click();
            assert.equal(d.wurzel.hidden, false, 'Gegenprobe: Dialog war offen');
            if (weg === 'abbrechen') d.abbrechen.click();
            else if (weg === 'overlay') d.overlay.click();
            else escape();

            assert.equal(d.wurzel.hidden, true);
            assert.deepEqual(d.gesendet, []);
        });
    }

    // AK8
    test('ein Button, der später eingefügt wird (Zeilen der DataTable), öffnet ebenfalls und die Query der Route bleibt', async () => {
        const { initLoeschDialog } = await laden();
        const d = baue('/user/0?x=1');
        initLoeschDialog(doc);

        const spaeter = knopf('11');
        const kind = h('span');
        spaeter.append(kind);
        doc.body.append(spaeter);
        kind.click();

        assert.equal(d.wurzel.hidden, false);
        d.bestaetigen.click();
        assert.deepEqual(d.gesendet, ['/user/11?x=1']);
    });
});
