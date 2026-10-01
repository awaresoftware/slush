@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <x-auth-heading title="Forgot password?" subtitle="We'll email you a reset link" />

    {{-- Session Status --}}
    @if (session('status'))
        <div class="mb-4 text-sm text-success text-center">
            {{ session('status') }}
        </div>
    @endif

    <p class="text-sm text-base-content/65 mb-5 text-center">
        Enter your email address and we'll send you a link to reset your password.
    </p>

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
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

        {{-- Submit --}}
        <x-mary-button
            label="Send reset link"
            type="submit"
            class="btn-primary btn-capsule w-full"
        />
    </form>

    <p class="text-center text-sm text-base-content/65 mt-6">
        <a href="{{ route('login') }}" class="text-primary hover:underline">Back to log in</a>
    </p>
@endsection
