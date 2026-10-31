@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::permission.header_list'))

@section('content')
    <x-kit::card-main>
        <x-kit::card-main-header>
            <h1>@lang('userauth::permission.header_list')</h1>
            @include('include.breadcrumb')
        </x-kit::card-main-header>
        <x-kit::card-body>
            @include('include.message')

            <table class="table table-bordered data-table table-sm table-hover">
                <thead>
                <tr>
                    <th>@lang('userauth::permission.id')</th>
                    <th>@lang('userauth::permission.permission_display')</th>
                    <th>@lang('userauth::permission.permission')</th>
                    <th width="165px">
                        @hasPermission('permission_create')
                            <x-kit::button-create :route="route('permission.create')">@lang('userauth::button.addPermission')</x-kit::button-create>
                        @endhasPermission
                    </th>
                </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </x-kit::card-body>
    </x-kit::card-main>

    @include('userauth::partials.loeschen', [
        'titel' => 'Recht löschen',
        'body' => 'Wollen Sie wirklich dieses Recht löschen?',
        'route' => route('permission.delete', 0),
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
        ajax: "{{ route('permission') }}",
        columns: [
            { data: 'id', name: 'id' },
            { data: 'group_display', name: 'group_display' },
            { data: 'group_name', name: 'group_name' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ],
    });

  });
</script>
@stop
