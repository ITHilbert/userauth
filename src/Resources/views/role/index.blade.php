@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::role.header_list'))

@section('content')
    <x-kit::card-main>
        <x-kit::card-main-header>
            <h1>@lang('userauth::role.header_list')</h1>
            @include('include.breadcrumb')
        </x-kit::card-main-header>
        <x-kit::card-body>
            @include('include.message')

            <table class="table table-bordered data-table table-sm table-hover">
                <thead>
                    <tr>
                        <th>@lang('userauth::role.id')</th>
                        <th>@lang('userauth::role.role_display')</th>
                        <th>@lang('userauth::role.role')</th>
                        <th width="165px">
                            @hasPermission('role_create')
                                <x-kit::button-create :route="route('role.create')">@lang('userauth::button.addRole')</x-kit::button-create>
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
        'titel' => 'Rolle löschen',
        'body' => 'Wollen Sie wirklich diese Rolle löschen?',
        'route' => route('role.delete', 0),
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
        ajax: "{{ route('role') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'role_display', name: 'role_display' },
            { data: 'role', name: 'role' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ],
    });

  });
</script>
@stop
