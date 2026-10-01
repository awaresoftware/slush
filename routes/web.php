<?php

use App\Http\Controllers\BrowseDirectoriesController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\PlaylistTrackController;
use App\Http\Controllers\StreamController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', HealthCheckController::class)->name('healthz');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    if (User::count() === 0) {
        return redirect()->route('register');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::home.index')->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::livewire('profile/{user}', 'pages::profile.show')->name('profile.show');
    Route::livewire('library', 'pages::library.index')->name('library.index');
    Route::livewire('library/albums/{album}', 'pages::library.album-show')->name('library.album.show');
    Route::livewire('library/artists/{artist}', 'pages::library.artist-show')
        ->where('artist', '.+')
        ->name('library.artist.show');
    Route::livewire('library/upload/summary', 'pages::library.upload-summary')->name('library.upload.summary');
    Route::livewire('playlists/smart/create', 'pages::playlists.smart-editor')->name('playlists.smart.create');
    Route::livewire('playlists/smart/{playlist}/edit', 'pages::playlists.smart-editor')->name('playlists.smart.edit');
    Route::resource('playlists', PlaylistController::class);
    Route::post('playlists/{playlist}/tracks', [PlaylistTrackController::class, 'store'])->name('playlist-tracks.store');
    Route::delete('playlists/{playlist}/tracks/{track}', [PlaylistTrackController::class, 'destroy'])->name('playlist-tracks.destroy');
    Route::get('stream/{track}', StreamController::class)->name('stream.track');
    Route::get('api/browse-directories', BrowseDirectoriesController::class)->name('browse-directories');
});

require __DIR__.'/settings.php';
