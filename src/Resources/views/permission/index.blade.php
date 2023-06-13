{{-- resources/views/admin/dashboard.blade.php --}}

@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::permission.header_list'))

{{-- @section('content_header')
@stop --}}

@section('content')
<card-main>
    <card-main-header>
        <h1>@lang('userauth::permission.header_list')</h1>
        @include('include.breadcrumb')
    </card-main-header>
    <card-body>
        @include('include.message')

        <table class="table table-bordered data-table table-sm table-hover">
            <thead>
            <tr>
                <th>@lang('userauth::permission.id')</th>
                <th>@lang('userauth::permission.permission_display')</th>
                <th>@lang('userauth::permission.permission')</th>
                <th width="165px">
                    @hasPermission('permission_create')
                        <button-create route="{{ route('permission.create') }}">@lang('userauth::button.addPermission')</button-create>
                    @endhasPermission
                </th>
            </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </card-body>
</card-main>


<dialog-delete title="Recht löschen" body="Wollen Sie wirklich dieses Recht löschen?" route="{{ route('permission.delete',0) }}" ></dialog-delete>

@stop

@section('js')
<script>
  $(function() {

    var table = $('.data-table').DataTable({
        processing: true,
        serverSide: true,
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
