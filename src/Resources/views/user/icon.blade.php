@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::user.header_edit'))


@section('content')
<card-main title="@lang('userauth::user.header_edit')">
    <hform action="{{ route('user.update', $user->id) }}" enctype="multipart/form-data" >
    <card-main-header>
        <h1>@lang('userauth::user.header_edit')</h1>
        @include('include.breadcrumb')
    </card-main-header>
    <card-body>
        @include('include.message')

        {{-- image --}}
        @if (config('userauth.user.image'))
            <div class="form-group row mb-2">
                <label for="image" class="col-md-4 col-form-label text-md-right">@lang('userauth::user.image')</label>
                <div class="col-md-6">
                <input-file-img name="image" value="{{ old('image', '') }}"  />
                </div>
            </div>
        @endif
    </card-body>
    <card-footer>
        {{-- Buttons --}}
        <div class="form-group row mb-2">
            <div class="col">
                <button-back route="{{ route('user') }}">@lang('userauth::button.back')</button-back>
            </div>
            <div class="col">
                <button-save class="float-end">@lang('userauth::button.save')</button-save>
            </div>
        </div>
    </card-footer>
    </hform>
</card-main>
@stop
