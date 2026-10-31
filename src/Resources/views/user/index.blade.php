@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::user.header_list'))

@section('content')
    <x-kit::card-main>
        <x-kit::card-main-header>
            <h1>@lang('userauth::user.header_list')</h1>
            @include('include.breadcrumb')
        </x-kit::card-main-header>
        <x-kit::card-body>
            @include('include.message')

            <table class="table table-bordered data-table table-sm table-hover">
                <thead>
                <tr>
                    <th>@lang('userauth::user.id')</th>
                    <th>@lang('userauth::user.name')</th>
                    <th>@lang('userauth::user.email')</th>
                    <th>@lang('userauth::user.role')</th>
                    <th width="165px">
                        @hasPermission('user_create')
                            <x-kit::button-create :route="route('user.create')">@lang('userauth::button.addUser')</x-kit::button-create>
                        @endhasPermission()
                    </th>
                </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </x-kit::card-body>
    </x-kit::card-main>

    @include('userauth::partials.loeschen', [
        'titel' => 'Benutzer löschen',
        'body' => 'Wollen Sie wirklich diesen Benutzer löschen?',
        'route' => route('user.delete', 0),
    ])
@stop

@section('js')
<script>
  $(function() {

    var table = $('.data-table').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        language: { url: "{{ asset("vendor/laravelkit/DataTable_DE.json ") }}" },
        ajax: "{{ route('user') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'email', name: 'email' },
            { data: 'RoleName', name: 'RoleName' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ],
    });

  });
</script>
@stop
