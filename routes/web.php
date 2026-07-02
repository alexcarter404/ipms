<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommTemplateController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\MatterClassController;
use App\Http\Controllers\MatterController;
use App\Http\Controllers\MatterPartyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RenewalController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WorkflowApplicationController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Clients & contacts
    Route::resource('clients', ClientController::class);
    Route::post('clients/{client}/contacts', [ContactController::class, 'store'])->name('clients.contacts.store');
    Route::patch('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    // Matters
    Route::resource('matters', MatterController::class);
    Route::post('matters/{matter}/parties', [MatterPartyController::class, 'store'])->name('matters.parties.store');
    Route::delete('matters/{matter}/parties/{party}', [MatterPartyController::class, 'destroy'])->name('matters.parties.destroy');
    Route::post('matters/{matter}/classes', [MatterClassController::class, 'store'])->name('matters.classes.store');
    Route::patch('classes/{class}', [MatterClassController::class, 'update'])->name('classes.update');
    Route::delete('classes/{class}', [MatterClassController::class, 'destroy'])->name('classes.destroy');
    Route::post('families', [FamilyController::class, 'store'])->name('families.store');

    // Tasks / actions
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('matters/{matter}/tasks', [TaskController::class, 'store'])->name('matters.tasks.store');
    Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // Renewals
    Route::get('renewals', [RenewalController::class, 'index'])->name('renewals.index');
    Route::post('matters/{matter}/renewals', [RenewalController::class, 'store'])->name('matters.renewals.store');
    Route::post('matters/{matter}/renewals/generate', [RenewalController::class, 'generate'])->name('matters.renewals.generate');
    Route::patch('renewals/{renewal}', [RenewalController::class, 'update'])->name('renewals.update');
    Route::delete('renewals/{renewal}', [RenewalController::class, 'destroy'])->name('renewals.destroy');

    // Workflows
    Route::resource('workflows', WorkflowController::class)->except(['show']);
    Route::post('matters/{matter}/apply-workflow', [WorkflowApplicationController::class, 'store'])->name('matters.workflows.apply');

    // Communication templates & communications
    Route::resource('templates', CommTemplateController::class, ['parameters' => ['templates' => 'template']])->except(['show']);
    Route::post('templates/preview', [CommTemplateController::class, 'preview'])->name('templates.preview');
    Route::post('matters/{matter}/communications', [CommunicationController::class, 'store'])->name('matters.communications.store');
    Route::post('communications/{communication}/send', [CommunicationController::class, 'markSent'])->name('communications.send');
    Route::delete('communications/{communication}', [CommunicationController::class, 'destroy'])->name('communications.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
