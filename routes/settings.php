<?php

use App\Http\Controllers\Settings\AutoTagSettingsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TeamController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified', 'onboarded'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::middleware('role:owner,administrator')->group(function () {
        Route::get('settings/auto-tag', [AutoTagSettingsController::class, 'edit'])->name('auto-tag.edit');
        Route::patch('settings/auto-tag', [AutoTagSettingsController::class, 'update'])->name('auto-tag.update');

        Route::get('settings/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('settings/team/invitations', [TeamController::class, 'inviteStore'])->name('team.invitations.store');
        Route::delete('settings/team/invitations/{invitation}', [TeamController::class, 'inviteDestroy'])->name('team.invitations.destroy');
        Route::patch('settings/team/members/{user}', [TeamController::class, 'memberUpdate'])->name('team.members.update');
        Route::delete('settings/team/members/{user}', [TeamController::class, 'memberDestroy'])->name('team.members.destroy');
    });
});
