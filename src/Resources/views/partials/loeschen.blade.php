{{-- Lösch-Dialog der Listen: Kit-Insel dialog-delete (Theme tailwind) und das Formular #formDelete, das der Dialog absendet. --}}
<div data-vue="dialog-delete" data-props="{{ json_encode(['titel' => $titel, 'body' => $body, 'route' => $route]) }}"></div>

<form id="formDelete" method="POST">
    @csrf
    @method('DELETE')
</form>
