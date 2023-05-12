@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::user.header_list'))

{{-- @section('content_header')
@stop --}}

@section('content')
    <card-main>
        <card-header>@lang('userauth::user.header_list')</card-header>
        <card-body>
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
                            <button-create route="{{ route('user.create') }}">@lang('userauth::button.addUser')</button-create>
                        @endhasPermission()
                    </th>
                </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </card-body>
    </card-main>


    <dialog-delete title="Benutzer löschen" body="Wollen Sie wirklich diesen Benutzer löschen?" route="{{ route('user.delete',0) }}" ></dialog-delete>

@stop

@section('js')
<script>
  $(function() {

    var table = $('.data-table').DataTable({
        processing: true,
        serverSide: true,
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
