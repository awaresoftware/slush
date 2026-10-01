@extends('layouts.guest')

@section('title', 'Log in')

@section('content')
    <x-auth-heading title="Welcome back" subtitle="Sign in to your Rekodo account" />

    {{-- Session Status --}}
    @if (session('status'))
        <div class="mb-4 text-sm text-success text-center">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
        @csrf

        {{-- Email --}}
        <x-mary-input
            label="Email address"
            name="email"
            type="email"
            value="{{ old('email') }}"
            required
            autofocus
            autocomplete="email"
            placeholder="email@example.com"
            errorField="email"
        />

        {{-- Password --}}
        <div>
            <x-mary-input
                label="Password"
                name="password"
                type="password"
                required
                autocomplete="current-password"
                placeholder="Password"
                errorField="password"
            />
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-xs text-base-content/65 hover:text-primary mt-1 block text-right">
                    Forgot password?
                </a>
            @endif
        </div>

        {{-- Remember Me --}}
        <label class="flex items-center gap-2 cursor-pointer">
            <input
                type="checkbox"
                name="remember"
                class="checkbox checkbox-sm checkbox-primary"
                @if (old('remember')) checked @endif
            >
            <span class="text-sm text-base-content/65">Remember me</span>
        </label>

        {{-- Submit --}}
        <x-mary-button
            label="Log in"
            type="submit"
            class="btn-primary btn-capsule w-full"
        />
    </form>

    {{-- Create account link — only when no users exist --}}
    @if (\App\Models\User::count() === 0)
        <p class="text-center text-sm text-base-content/65 mt-6">
            <a href="{{ route('register') }}" class="text-primary hover:underline">Create account</a>
        </p>
    @endif
@endsection
