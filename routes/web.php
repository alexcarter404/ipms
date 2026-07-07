<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\BillingAgreementController;
use App\Http\Controllers\BillingSettingsController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ChargeController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientEntityController;
use App\Http\Controllers\ConflictCheckController;
use App\Http\Controllers\UserAccessController;
use App\Http\Controllers\DisbursementController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\WipController;
use App\Http\Controllers\CommTemplateController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\MailroomController;
use App\Http\Controllers\MatterClassController;
use App\Http\Controllers\MatterContactController;
use App\Http\Controllers\MatterImportController;
use App\Http\Controllers\MatterController;
use App\Http\Controllers\OfficeMessageController;
use App\Http\Controllers\OfficeSubmissionController;
use App\Http\Controllers\MatterPartyController;
use App\Http\Controllers\MatterTakeOnController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RenewalController;
use App\Http\Controllers\RenewalRuleController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WorkflowApplicationController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Client portal: separate guard, hard-scoped to the client
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('login', [PortalController::class, 'login'])->name('login');
    Route::post('login', [PortalController::class, 'authenticate'])->name('login.attempt');

    Route::middleware('auth:portal')->group(function () {
        Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::post('renewals/{renewal}/instruct', [PortalController::class, 'instructRenewal'])->name('renewals.instruct');
        Route::get('documents/{document}/download', [PortalController::class, 'downloadDocument'])->name('documents.download');
        Route::post('logout', [PortalController::class, 'logout'])->name('logout');
    });
});

Route::middleware(['auth:web', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', App\Http\Controllers\SearchController::class)->name('search');

    // Clients & contacts
    Route::resource('clients', ClientController::class);
    Route::post('clients/{client}/contacts', [ContactController::class, 'store'])->name('clients.contacts.store');
    Route::post('clients/{client}/entities', [ClientEntityController::class, 'store'])->name('clients.entities.store');
    Route::patch('entities/{entity}', [ClientEntityController::class, 'update'])->name('entities.update');
    Route::delete('entities/{entity}', [ClientEntityController::class, 'destroy'])->name('entities.destroy');
    Route::patch('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    // Matters (take-on routes precede the resource so 'take-on' isn't captured as {matter})
    Route::get('matters/take-on', [MatterTakeOnController::class, 'create'])->name('matters.take-on');
    Route::post('matters/take-on', [MatterTakeOnController::class, 'store'])->name('matters.take-on.store');
    Route::resource('matters', MatterController::class);
    Route::post('matters/{matter}/contacts', [MatterContactController::class, 'store'])->name('matters.contacts.store');
    Route::delete('matters/{matter}/contacts/{contact}', [MatterContactController::class, 'destroy'])->name('matters.contacts.destroy');
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

    // Renewals & schedule rules
    Route::get('renewals', [RenewalController::class, 'index'])->name('renewals.index');
    Route::resource('renewal-rules', RenewalRuleController::class)
        ->only(['index'])
        ->parameters(['renewal-rules' => 'renewalRule']);
    Route::resource('renewal-rules', RenewalRuleController::class)
        ->except(['index', 'show'])
        ->parameters(['renewal-rules' => 'renewalRule'])
        ->middleware('can:manage-settings');
    Route::post('matters/{matter}/renewals', [RenewalController::class, 'store'])->name('matters.renewals.store');
    Route::post('matters/{matter}/renewals/generate', [RenewalController::class, 'generate'])->name('matters.renewals.generate');
    Route::patch('renewals/{renewal}', [RenewalController::class, 'update'])->name('renewals.update');
    Route::delete('renewals/{renewal}', [RenewalController::class, 'destroy'])->name('renewals.destroy');

    // Workflows
    Route::resource('workflows', WorkflowController::class)->only(['index']);
    Route::resource('workflows', WorkflowController::class)
        ->except(['index', 'show'])
        ->middleware('can:manage-settings');
    Route::post('matters/{matter}/apply-workflow', [WorkflowApplicationController::class, 'store'])->name('matters.workflows.apply');

    // Billing: agreements & WIP (time, disbursements, charges)
    Route::post('matters/{matter}/agreement', [BillingAgreementController::class, 'save'])->name('matters.agreement.save');
    Route::delete('matters/{matter}/agreement', [BillingAgreementController::class, 'destroy'])->name('matters.agreement.destroy');
    Route::post('entities/{entity}/agreement', [BillingAgreementController::class, 'saveForEntity'])->name('entities.agreement.save');
    Route::post('matters/{matter}/time', [TimeEntryController::class, 'store'])->name('matters.time.store');
    Route::patch('time-entries/{timeEntry}', [TimeEntryController::class, 'update'])->name('time-entries.update');
    Route::patch('time-entries/{timeEntry}/status', [TimeEntryController::class, 'updateStatus'])->name('time-entries.status');
    Route::delete('time-entries/{timeEntry}', [TimeEntryController::class, 'destroy'])->name('time-entries.destroy');
    Route::post('matters/{matter}/disbursements', [DisbursementController::class, 'store'])->name('matters.disbursements.store');
    Route::patch('disbursements/{disbursement}', [DisbursementController::class, 'update'])->name('disbursements.update');
    Route::patch('disbursements/{disbursement}/status', [DisbursementController::class, 'updateStatus'])->name('disbursements.status');
    Route::delete('disbursements/{disbursement}', [DisbursementController::class, 'destroy'])->name('disbursements.destroy');
    Route::post('matters/{matter}/charges', [ChargeController::class, 'store'])->name('matters.charges.store');
    Route::patch('charges/{charge}', [ChargeController::class, 'update'])->name('charges.update');
    Route::post('agreement-stages/{stage}/charge', [ChargeController::class, 'raiseStage'])->name('agreement-stages.charge');
    Route::delete('charges/{charge}', [ChargeController::class, 'destroy'])->name('charges.destroy');

    // Integrations: IP office exchange
    Route::get('integrations', [OfficeMessageController::class, 'index'])->name('integrations.index');
    Route::post('integrations/poll', [OfficeMessageController::class, 'poll'])->name('integrations.poll');
    Route::patch('office-messages/{officeMessage}/assign', [OfficeMessageController::class, 'assign'])->name('office-messages.assign');
    Route::post('office-messages/{officeMessage}/process', [OfficeMessageController::class, 'process'])->name('office-messages.process');
    Route::post('office-messages/{officeMessage}/dismiss', [OfficeMessageController::class, 'dismiss'])->name('office-messages.dismiss');
    Route::post('office-submissions', [OfficeSubmissionController::class, 'store'])->name('office-submissions.store');
    Route::post('office-submissions/{officeSubmission}/submit', [OfficeSubmissionController::class, 'submit'])->name('office-submissions.submit');
    Route::delete('office-submissions/{officeSubmission}', [OfficeSubmissionController::class, 'destroy'])->name('office-submissions.destroy');

    // Audit log: roll a record back/forward across an update entry
    Route::post('audits/{audit}/transition', [AuditController::class, 'transition'])->name('audits.transition');

    // Register import & reconciliation
    Route::post('matters/import', [MatterImportController::class, 'store'])->name('matters.import');
    Route::post('integrations/reconcile', [MatterImportController::class, 'reconcile'])->name('integrations.reconcile');
    Route::post('register-checks/{registerCheck}/accept', [MatterImportController::class, 'accept'])->name('register-checks.accept');
    Route::post('register-checks/{registerCheck}/dismiss', [MatterImportController::class, 'dismiss'])->name('register-checks.dismiss');

    // Users & access (admin): roles and ethical walls
    Route::get('settings/users', [UserAccessController::class, 'index'])
        ->name('users.index')->middleware('can:manage-users');
    Route::patch('settings/users/{user}', [UserAccessController::class, 'update'])
        ->name('users.update')->middleware('can:manage-users');
    Route::put('clients/{client}/wall', [UserAccessController::class, 'syncWall'])
        ->name('clients.wall')->middleware('can:manage-users');
    Route::post('clients/{client}/portal-users', [UserAccessController::class, 'storePortalUser'])
        ->name('clients.portal-users.store')->middleware('can:manage-users');
    Route::delete('portal-users/{portalUser}', [UserAccessController::class, 'destroyPortalUser'])
        ->name('portal-users.destroy')->middleware('can:manage-users');

    // Conflict check at intake
    Route::get('conflict-check', ConflictCheckController::class)->name('conflict-check');

    // Mailroom: inbound email capture onto matters
    Route::get('mailroom', [MailroomController::class, 'index'])->name('mailroom.index');
    Route::post('mailroom/ingest', [MailroomController::class, 'ingest'])->name('mailroom.ingest');
    Route::patch('mailroom/{communication}/assign', [MailroomController::class, 'assign'])->name('mailroom.assign');

    // Documents: uploads, office-filed and template-generated files
    Route::post('matters/{matter}/documents', [DocumentController::class, 'store'])->name('matters.documents.store');
    Route::post('matters/{matter}/documents/generate', [DocumentController::class, 'generate'])->name('matters.documents.generate');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('documents/{document}/replace', [DocumentController::class, 'replace'])->name('documents.replace');
    Route::patch('documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // Billing: budgets
    Route::get('billing/budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::post('matters/{matter}/budgets', [BudgetController::class, 'store'])->name('matters.budgets.store');
    Route::patch('budgets/{budget}', [BudgetController::class, 'update'])->name('budgets.update');
    Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');

    // Billing: WIP dashboard & invoices
    Route::get('billing/wip', [WipController::class, 'index'])->name('billing.wip');
    Route::get('billing/wip/{entity}', [WipController::class, 'show'])->name('billing.wip.show');
    Route::get('billing/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('matters/{matter}/invoices', [InvoiceController::class, 'store'])->name('matters.invoices.store');
    Route::post('entities/{entity}/invoices', [InvoiceController::class, 'storeForEntity'])->name('entities.invoices.store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');

    // Billing: quotes
    Route::resource('quotes', QuoteController::class)->except(['show']);
    Route::patch('quotes/{quote}/status', [QuoteController::class, 'transition'])->name('quotes.status');

    // Billing: settings (exchange rates, tax rates, activity codes, rate cards)
    Route::get('billing/settings', [BillingSettingsController::class, 'edit'])->name('billing.settings');
    Route::post('billing/sync-rates', [BillingSettingsController::class, 'syncRates'])->name('billing.sync-rates')->middleware('can:manage-billing-settings');
    Route::post('billing/exchange-rates', [BillingSettingsController::class, 'saveExchangeRate'])->name('billing.exchange-rates.save')->middleware('can:manage-billing-settings');
    Route::post('billing/tax-rates', [BillingSettingsController::class, 'saveTaxRate'])->name('billing.tax-rates.store')->middleware('can:manage-billing-settings');
    Route::patch('billing/tax-rates/{taxRate}', [BillingSettingsController::class, 'saveTaxRate'])->name('billing.tax-rates.update')->middleware('can:manage-billing-settings');
    Route::delete('billing/tax-rates/{taxRate}', [BillingSettingsController::class, 'deleteTaxRate'])->name('billing.tax-rates.destroy')->middleware('can:manage-billing-settings');
    Route::post('billing/activity-codes', [BillingSettingsController::class, 'saveActivityCode'])->name('billing.activity-codes.store')->middleware('can:manage-billing-settings');
    Route::patch('billing/activity-codes/{activityCode}', [BillingSettingsController::class, 'saveActivityCode'])->name('billing.activity-codes.update')->middleware('can:manage-billing-settings');
    Route::delete('billing/activity-codes/{activityCode}', [BillingSettingsController::class, 'deleteActivityCode'])->name('billing.activity-codes.destroy')->middleware('can:manage-billing-settings');
    Route::patch('billing/timekeepers/{user}/role', [BillingSettingsController::class, 'updateUserRole'])->name('billing.timekeepers.role')->middleware('can:manage-billing-settings');
    Route::post('billing/rate-cards', [BillingSettingsController::class, 'saveRateCard'])->name('billing.rate-cards.store')->middleware('can:manage-billing-settings');
    Route::patch('billing/rate-cards/{rateCard}', [BillingSettingsController::class, 'saveRateCard'])->name('billing.rate-cards.update')->middleware('can:manage-billing-settings');
    Route::delete('billing/rate-cards/{rateCard}', [BillingSettingsController::class, 'deleteRateCard'])->name('billing.rate-cards.destroy')->middleware('can:manage-billing-settings');

    // Communication templates & communications
    Route::resource('templates', CommTemplateController::class, ['parameters' => ['templates' => 'template']])->only(['index']);
    Route::resource('templates', CommTemplateController::class, ['parameters' => ['templates' => 'template']])
        ->except(['index', 'show'])
        ->middleware('can:manage-settings');
    Route::post('templates/preview', [CommTemplateController::class, 'preview'])->name('templates.preview');
    Route::post('matters/{matter}/communications', [CommunicationController::class, 'store'])->name('matters.communications.store');
    Route::post('communications/{communication}/send', [CommunicationController::class, 'markSent'])->name('communications.send');
    Route::delete('communications/{communication}', [CommunicationController::class, 'destroy'])->name('communications.destroy');
});

Route::middleware('auth:web')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
