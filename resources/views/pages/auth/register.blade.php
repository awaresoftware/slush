@extends('layouts.guest')

@section('title', 'Create account')

@section('content')
    <x-auth-heading title="Create your account" subtitle="Set up Rekodo to start streaming your library" />

    <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-5">
        @csrf

        {{-- Name --}}
        <x-mary-input
            label="Full name"
            name="name"
            type="text"
            value="{{ old('name') }}"
            required
            autofocus
            autocomplete="name"
            placeholder="Your name"
            errorField="name"
        />

        {{-- Email --}}
        <x-mary-input
            label="Email address"
            name="email"
            type="email"
            value="{{ old('email') }}"
            required
            autocomplete="username"
            placeholder="email@example.com"
            errorField="email"
        />

        {{-- Password --}}
        <x-mary-input
            label="Password"
            name="password"
            type="password"
            required
            autocomplete="new-password"
            placeholder="Password"
            errorField="password"
        />

        {{-- Confirm Password --}}
        <x-mary-input
            label="Confirm password"
            name="password_confirmation"
            type="password"
            required
            autocomplete="new-password"
            placeholder="Confirm password"
        />

        {{-- Submit --}}
        <x-mary-button
            label="Create Account"
            type="submit"
            class="btn-primary btn-capsule w-full"
        />
    </form>

    <p class="text-center text-sm text-base-content/65 mt-6">
        Already have an account?
        <a href="{{ route('login') }}" class="text-primary hover:underline">Log in</a>
    </p>
@endsection
