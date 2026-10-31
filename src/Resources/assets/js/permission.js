var group_display = $('input[name="group_display"]')[0];
var group_name = $('input[name="group_name"]')[0];


group_display.addEventListener("change", function() {
    var value = this.value; //$('input[name="group_display"]')[0].val();

    value = formatDisplayToIntern(value);

    $('input[name="group_name"]')[0].value = value;
});


function showSinglePermissions() {
    var cb = $('input[name="ckPermissionGroup"]')[0].checked;

    if (cb) {
        $('#group_permissions_show').removeClass('d-none');
    } else {
        $('#group_permissions_show').addClass('d-none');
    }
}



// Startwert: höchste Nummer der vorhandenen neuen Zeilen im DOM (nach einem Validierungsfehler können es mehrere sein)
function hoechsteNeueNummer(tbody) {
    var hoechste = 1;
    tbody.find('tr[id^="new-row-"]').each(function () {
        var nr = parseInt($(this).attr('nummer'), 10);
        if (!isNaN(nr) && nr > hoechste) {
            hoechste = nr;
        }
    });
    return hoechste;
}

var newID = hoechsteNeueNummer($('#tbody'));

function newPermission() {
    var vorlage = $('#vorlage').clone()

    newID++;
    var zeilenNummer = newID;
    vorlage.removeClass('d-none');
    vorlage.attr('id', 'new-row-' + zeilenNummer)
    vorlage.attr('nummer', zeilenNummer);

    //ID anpassen
    var rowid = vorlage.find('#new-id');
    rowid.attr('id', 'new-id-' + zeilenNummer);
    rowid.append('new_' + zeilenNummer);

    //Recht anpassen
    var rowid = vorlage.find('#vorlage_display');
    rowid.attr('id', 'display_new_' + zeilenNummer);
    rowid.attr('name', 'permission_display_new_' + zeilenNummer);

    //Recht anpassen
    var tb1 = vorlage.find('#vorlage_display');
    tb1.attr('id', 'display_new_' + zeilenNummer);
    tb1.attr('name', 'permission_display_new_' + zeilenNummer);

    //Recht intern anpassen
    var tb2 = vorlage.find('#vorlage_intern');
    tb2.attr('id', 'intern_new_' + zeilenNummer);
    tb2.attr('name', 'permission_new_' + zeilenNummer);

    //Löschen Button
    var btn = vorlage.find('#btnVorlage');
    btn.removeAttr('id');
    btn.attr('onclick', "deletePermission('new-row-" + zeilenNummer + "')");

    //Angepasste Vorlage einfügen
    $('#tbody').append(vorlage);

    var tb = document.getElementById('display_new_' + zeilenNummer)
    tb.addEventListener("change", function() {
        editIntern('new-row-' + zeilenNummer);
    });
}


function deletePermission(rowID) {

    //Neuer Eintrag
    if (rowID.indexOf('new-row') == 0) {
        var element = document.getElementById(rowID)
        element.parentNode.removeChild(element);
    } else {
        var element = $('#' + rowID);
        var nr = element.attr('nummer');

        element.addClass('d-none');


        //Recht anpassen
        var tb1 = element.find('#display_' + nr);
        tb1.attr('name', 'delete_display_' + nr);

        //Recht intern anpassen
        var tb2 = element.find('#intern_' + nr);
        tb2.attr('name', 'delete_intern_' + nr);
    }




}

function editIntern(rowID) {
    //console.log('editIntern(' + rowID + ')');

    var nr = $('#' + rowID).attr('nummer');
    //console.log('Nr: ' + nr);

    //Neuer Eintrag
    if (rowID.indexOf('new-row') == 0) {
        //console.log(1);
        var value = $('#display_new_' + nr).val();
        value = $('input[name="group_name"]')[0].value + '_' + formatDisplayToIntern(value);
        $('#intern_new_' + nr).val(value);
    } else {
        //console.log(2);
        var value = $('#display_' + nr).val();
        value = $('input[name="group_name"]')[0].value + '_' + formatDisplayToIntern(value);
        $('#intern_' + nr).val(value);
    }
}

function formatDisplayToIntern(value) {
    if (value === undefined) return '';

    value = value.toLowerCase();
    value = value.replace(' ', '_');
    value = value.replace('ä', 'ae');
    value = value.replace('ö', 'oe');
    value = value.replace('ü', 'ue');
    value = value.replace('ß', 'ss');
    value = value.replace(':', '');
    value = value.replace(';', '');
    value = value.replace('"', '');
    value = value.replace("'", '');
    value = value.replace("'", '');
    value = value.replace("`", '');
    value = value.replace("~", '');
    value = value.replace("<", '');
    value = value.replace(">", '');
    value = value.replace("|", '');

    return value;
}
