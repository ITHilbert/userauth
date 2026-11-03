function setInternValue() {
    var value = document.getElementById('role_display').value;

    value = formatDisplayToIntern(value);

    document.getElementById('role').value = value;
}
