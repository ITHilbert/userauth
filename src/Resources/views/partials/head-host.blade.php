{{-- Head-Erweiterung des Hosts (userauth.head_view), z. B. Favicon und Meta-Tags. --}}
@if (filled(config('userauth.head_view')))
    @includeIf(config('userauth.head_view'))
@endif
