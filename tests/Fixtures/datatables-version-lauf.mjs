// Führt das Inline-Skript einer Listenseite mit einem DataTable-Stand der angegebenen Version aus (datatables-optik, AK1).
// Aufruf: node datatables-version-lauf.mjs <skriptdatei> <version>
// Ausgabe: JSON {fehler, optionen} — die Optionen des einen `new DataTable(...)`-Aufrufs.
import fs from 'node:fs';
import vm from 'node:vm';

const code = fs.readFileSync(process.argv[2], 'utf8');
const version = process.argv[3];
const ergebnis = { fehler: null, optionen: [] };

function DataTable(selektor, opt) {
    ergebnis.optionen.push(opt);
}
DataTable.version = version;

const document = { readyState: 'complete', addEventListener: () => {}, querySelector: () => null, querySelectorAll: () => [] };
const fenster = { document, DataTable, addEventListener: () => {} };
const kontext = vm.createContext({ document, window: fenster, DataTable, console, setTimeout: (fn) => fn() });

try {
    vm.runInContext(code, kontext);
} catch (e) {
    ergebnis.fehler = `${e.name}: ${e.message}`;
}
console.log(JSON.stringify(ergebnis));
