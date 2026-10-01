@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
    <x-auth-heading title="Choose a new password" />

    {{-- Session Status --}}
    @if (session('status'))
        <div class="mb-4 text-sm text-success text-center">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
        @csrf

        {{-- Token --}}
        <input type="hidden" name="token" value="{{ request()->route('token') }}">

        {{-- Email --}}
        <x-mary-input
            label="Email address"
            name="email"
            type="email"
            value="{{ request('email') }}"
            required
            autocomplete="email"
            placeholder="email@example.com"
            errorField="email"
        />

        {{-- Password --}}
        <x-mary-input
            label="New password"
            name="password"
            type="password"
            required
            autocomplete="new-password"
            placeholder="New password"
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
            label="Reset password"
            type="submit"
            class="btn-primary btn-capsule w-full"
        />
    </form>
@endsection
