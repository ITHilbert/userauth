// Rechteformular ohne jQuery (klassisches Skript, globale Funktionen für onclick/onchange in den Views).
var group_display = document.querySelector('input[name="group_display"]');

if (group_display) {
    group_display.addEventListener('change', function () {
        var value = formatDisplayToIntern(this.value);

        document.querySelector('input[name="group_name"]').value = value;
    });
}


function showSinglePermissions() {
    var cb = document.querySelector('input[name="ckPermissionGroup"]').checked;
    var bereich = document.getElementById('group_permissions_show');

    if (cb) {
        bereich.classList.remove('d-none');
    } else {
        bereich.classList.add('d-none');
    }
}


// Startwert: höchste Nummer der vorhandenen neuen Zeilen im DOM (nach einem Validierungsfehler können es mehrere sein)
function hoechsteNeueNummer(tbody) {
    var hoechste = 1;
    if (!tbody) {
        return hoechste;
    }
    tbody.querySelectorAll('tr[id^="new-row-"]').forEach(function (zeile) {
        var nr = parseInt(zeile.getAttribute('nummer'), 10);
        if (!isNaN(nr) && nr > hoechste) {
            hoechste = nr;
        }
    });
    return hoechste;
}

var newID = hoechsteNeueNummer(document.getElementById('tbody'));

function newPermission() {
    var vorlage = document.getElementById('vorlage').cloneNode(true);

    newID++;
    var zeilenNummer = newID;
    vorlage.classList.remove('d-none');
    vorlage.setAttribute('id', 'new-row-' + zeilenNummer);
    vorlage.setAttribute('nummer', zeilenNummer);

    //ID anpassen
    var rowid = vorlage.querySelector('#new-id');
    rowid.setAttribute('id', 'new-id-' + zeilenNummer);
    rowid.append('new_' + zeilenNummer);

    //Recht anpassen
    var tb1 = vorlage.querySelector('#vorlage_display');
    tb1.setAttribute('id', 'display_new_' + zeilenNummer);
    tb1.setAttribute('name', 'permission_display_new_' + zeilenNummer);

    //Recht intern anpassen
    var tb2 = vorlage.querySelector('#vorlage_intern');
    tb2.setAttribute('id', 'intern_new_' + zeilenNummer);
    tb2.setAttribute('name', 'permission_new_' + zeilenNummer);

    //Löschen Button
    var btn = vorlage.querySelector('#btnVorlage');
    btn.removeAttribute('id');
    btn.setAttribute('onclick', "deletePermission('new-row-" + zeilenNummer + "')");

    //Angepasste Vorlage einfügen
    document.getElementById('tbody').appendChild(vorlage);

    tb1.addEventListener('change', function () {
        editIntern('new-row-' + zeilenNummer);
    });
}


function deletePermission(rowID) {
    var element = document.getElementById(rowID);

    //Neuer Eintrag
    if (rowID.indexOf('new-row') == 0) {
        element.parentNode.removeChild(element);
    } else {
        var nr = element.getAttribute('nummer');

        element.classList.add('d-none');

        //Recht anpassen
        element.querySelector('#display_' + nr).setAttribute('name', 'delete_display_' + nr);

        //Recht intern anpassen
        element.querySelector('#intern_' + nr).setAttribute('name', 'delete_intern_' + nr);
    }
}

function editIntern(rowID) {
    var nr = document.getElementById(rowID).getAttribute('nummer');
    var praefix = rowID.indexOf('new-row') == 0 ? 'new_' : '';

    var value = document.getElementById('display_' + praefix + nr).value;
    value = document.querySelector('input[name="group_name"]').value + '_' + formatDisplayToIntern(value);
    document.getElementById('intern_' + praefix + nr).value = value;
}
