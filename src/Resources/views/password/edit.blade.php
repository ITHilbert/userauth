@extends('userauth::layouts.afterLogin')

@section('title', Lang::get('userauth::password.header_change'))

@section('content')
<div class="max-w-3xl mx-auto my-10">
    <!-- Premium Card Design -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Header -->
        <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50">
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                <i class="fas fa-key text-indigo-500"></i>
                @lang('userauth::password.header_change')
            </h1>
            <p class="text-slate-500 text-sm mt-1">Hier kannst du dein persönliches Passwort aktualisieren und absichern.</p>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="p-8">
            @csrf

            @include('include.message')

            <div class="space-y-6">
                <!-- Group: Neues Passwort -->
                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-2">
                        @lang('userauth::password.password')
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-slate-400"></i>
                        </div>
                        <input type="password" id="password" name="password" required autocomplete="new-password" 
                               class="pl-10 w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 text-sm py-2.5 transition-all @error('password') border-red-500 ring-2 ring-red-100 @enderror" 
                               placeholder="********">
                    </div>
                    @error('password')
                        <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                            <i class="fas fa-exclamation-circle"></i> {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Group: Passwort wiederholen -->
                <div>
                    <label for="password-confirm" class="block text-sm font-semibold text-slate-700 mb-2">
                        @lang('userauth::password.password-confirm')
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-slate-400"></i>
                        </div>
                        <input type="password" id="password-confirm" name="password_confirmation" required autocomplete="new-password" 
                               class="pl-10 w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 text-sm py-2.5 transition-all" 
                               placeholder="********">
                    </div>
                </div>
            </div>

            <!-- Footer Action -->
            <div class="mt-8 pt-6 border-t border-slate-100 flex justify-end">
                <button type="submit" class="inline-flex items-center px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <i class="fas fa-save mr-2"></i>
                    @lang('userauth::button.editPassword')
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
