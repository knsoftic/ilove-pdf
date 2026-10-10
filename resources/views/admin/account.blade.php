@extends('layouts.admin')

@section('title', 'Account')

@section('content')
    <form method="POST" action="{{ route('admin.account.update') }}" class="card mx-auto max-w-xl space-y-5 p-6">
        @csrf
        @method('PUT')
        <div>
            <label class="label" for="name">Name</label>
            <input id="name" name="name" class="input" value="{{ old('name', $admin->name) }}" required maxlength="100">
        </div>
        <div>
            <label class="label" for="email">E-mail</label>
            <input id="email" type="email" name="email" class="input" value="{{ old('email', $admin->email) }}" required maxlength="190" autocomplete="username">
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="label" for="password">New password</label>
                <input id="password" type="password" name="password" class="input" minlength="10" autocomplete="new-password">
                <p class="help">Leave empty to keep the current password.</p>
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="input" autocomplete="new-password">
            </div>
        </div>
        <div>
            <label class="label" for="current_password">Current password</label>
            <input id="current_password" type="password" name="current_password" class="input" required autocomplete="current-password">
        </div>
        <p class="text-xs text-slate-500">Last sign-in: {{ $admin->last_login_at?->diffForHumans() ?? '—' }}</p>
        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="size-4" /> Save</button>
        </div>
    </form>
@endsection
