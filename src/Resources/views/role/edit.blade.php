@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::role.header_edit'))

@section('content')
    <x-kit::card-main>
        <form method="POST" action="{{ route('role.update', $role->id) }}">
            @csrf
            <x-kit::card-main-header>
                <h1>@lang('userauth::role.header_edit')</h1>
                @include('include.breadcrumb')
            </x-kit::card-main-header>
            <x-kit::card-body>
                @include('include.message')

                @include('userauth::role.formularfelder', ['role' => $role])
            </x-kit::card-body>
            <x-kit::card-footer>
                <div class="form-group row mb-2">
                    <div class="col">
                        <x-kit::button-back :route="route('role')">@lang('userauth::button.back')</x-kit::button-back>
                        <x-kit::button-save class="float-end">@lang('userauth::button.save')</x-kit::button-save>
                    </div>
                </div>
            </x-kit::card-footer>
        </form>
    </x-kit::card-main>
@stop

@push('userauth-scripts')
    <script defer src="{{ asset('vendor/userauth/js/role.js') }}"></script>
@endpush
