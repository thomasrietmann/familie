<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ChildController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentImportController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\FamilyEventController;
use App\Http\Controllers\FamilyParentController;
use App\Http\Controllers\FamilyPublicLinkController;
use App\Http\Controllers\ImportedEventSuggestionController;
use App\Http\Controllers\PublicFamilyController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/public/family/{token}', [PublicFamilyController::class, 'show'])->name('public.family');

Route::middleware('auth')->scopeBindings()->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('families', FamilyController::class);
    Route::resource('families.children', ChildController::class)->except(['show'])->parameters(['children' => 'child']);
    Route::resource('families.events', FamilyEventController::class)->parameters(['events' => 'event']);
    Route::resource('families.document-imports', DocumentImportController::class)->only(['index', 'create', 'store', 'show'])->parameters(['document-imports' => 'documentImport']);

    Route::get('/document-imports/{documentImport}/review', [DocumentImportController::class, 'review'])->name('document-imports.review');
    Route::put('/imported-event-suggestions/{suggestion}', [ImportedEventSuggestionController::class, 'update'])->name('imported-event-suggestions.update');
    Route::post('/imported-event-suggestions/{suggestion}/accept', [ImportedEventSuggestionController::class, 'accept'])->name('imported-event-suggestions.accept');
    Route::post('/imported-event-suggestions/{suggestion}/reject', [ImportedEventSuggestionController::class, 'reject'])->name('imported-event-suggestions.reject');

    Route::get('/families/{family}/public-link', [FamilyPublicLinkController::class, 'show'])->name('families.public-link.show');
    Route::post('/families/{family}/public-link/enable', [FamilyPublicLinkController::class, 'enable'])->name('families.public-link.enable');
    Route::post('/families/{family}/public-link/regenerate', [FamilyPublicLinkController::class, 'regenerate'])->name('families.public-link.regenerate');
    Route::post('/families/{family}/public-link/disable', [FamilyPublicLinkController::class, 'disable'])->name('families.public-link.disable');
    Route::post('/families/{family}/parents/invite', [FamilyParentController::class, 'invite'])->name('families.parents.invite');
});
