@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::role.header_list'))

{{-- @section('content_header')
@stop --}}

@section('content')
<card-main>
    <card-main-header>
        <h1>@lang('userauth::role.header_list')</h1>
        @include('include.breadcrumb')
    </card-main-header>
    <card-body>
        @include('include.message')

        <table class="table table-bordered data-table table-sm table-hover">
            <thead>
                <tr>
                    <th>@lang('userauth::role.id')</th>
                    <th>@lang('userauth::role.role_display')</th>
                    <th>@lang('userauth::role.role')</th>
                    <th width="165px">
                        @hasPermission('role_create')
                            <button-create route="{{ route('role.create') }}">@lang('userauth::button.addRole')</button-create>
                        @endhasPermission()
                    </th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </card-body>
</card-main>

<dialog-delete title="Rolle löschen" body="Wollen Sie wirklich diese Rolle löschen?" route="{{ route('role.delete',0) }}" ></dialog-delete>

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
