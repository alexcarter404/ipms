# IPMS — Intellectual Property Management System

A full-stack IP practice management system inspired by market-leading IPMS
platforms (Clarivate Inprotech, Patricia, and similar): matter/docket
management, deadline-driven workflows, templated client communications, and
renewal/annuity management.

**Stack:** Laravel · Vue 3 · Inertia.js · PrimeVue · Tailwind CSS · MySQL

## Features

### Matters
- All IP right types: patents, trade marks, designs, copyright, domain names
- Full lifecycle details: application / publication / registration numbers and
  dates, priority claims, expiry, filing routes (national, PCT, EP, Madrid, Hague)
- Patent families and parent/child (priority → national phase) relationships
- Parties with roles: applicants, inventors, owners, agents, associates,
  licensees, opponents
- Nice classes with goods/services specifications for trade marks
- Search + filter register across reference, title, numbers, client, type,
  status, and jurisdiction

### Clients, Entities & Contacts
- Clients are groups that can contain multiple **legal entities**, each
  with its own registered details (company number, VAT, registered
  address) and billing particulars (billing contact, billing email,
  separate billing address, PO/reference to quote on invoices)
- Exactly one **default entity** per client; matters are billed to an
  explicitly chosen entity or fall back to the default
- Entity billing details are available as `{{entity.*}}` merge fields
  in communication templates
- Typed contacts: **people**, **shared mailboxes** (docketing inboxes,
  generic addresses — email required), and **organisations**
- Matters link to any number of contacts in roles — main correspondence,
  docketing, billing, reporting — with the comm composer prefilled from
  the main contact and a picker for the rest; `{{contact.*}}` and
  `{{docketing.*}}` merge fields resolve per matter
- Per-client matter portfolio view

### Dates, Actions & Workflow
- Tasks with official due dates, soft internal deadlines, priorities,
  critical (statutory) flags, and assignees
- Workflow templates: reusable deadline chains triggered by an event
  (filing, publication, grant, registration, office action, or a manual date)
- Applying a workflow to a matter fans out its steps into tasks with due
  dates offset from the trigger date
- **Stage data contracts**: each workflow step can declare the matter
  fields it requires (application number, priority date, responsible
  attorney, …) in the builder
- **Matter take-on**: open a matter part-way through a workflow — pick the
  entry stage, and the cumulative contract of that stage and all earlier
  ones becomes required (shown as a live checklist); tasks are created
  from the entry stage onward, anchored on the trigger date (or an
  explicit base date for manually-triggered workflows)
- Global task list with my-tasks / overdue / status filters

### Templated Communications
- Email and letter templates with `{{merge.fields}}` (matter, client,
  contact, attorney, dates — see the in-app merge field reference)
- Compose from a matter: template rendered live against the matter's data,
  editable, saved as draft, then marked sent (a permanent record)

### Renewals
- Data-driven **schedule rules** (templates) keyed by matter type and
  jurisdiction — a country-specific rule overrides the type-wide default.
  Seeded with common conventions plus the well-known exceptions:
  patents (annuities years 2–20 from filing), US patents (maintenance
  fees at 3.5/7.5/11.5 years from grant), EP (annuities from year 3),
  trade marks (10-year terms), US trade marks (§8 + §9 cadence from
  registration), designs (5-year terms), US designs (no maintenance)
- Rules define the anchor date (filing vs grant/registration), regular
  cycles or fixed offsets, grace period, and default fees — manageable
  in-app under Renewals → Schedule Rules
- One-click schedule generation per matter from its governing rule
  (idempotent — safe to re-run as rules improve)
- Status pipeline: upcoming → reminder sent → instructed → paid,
  plus lapsed/waived, with instructed/paid timestamps and fee tracking
- Renewals control centre with due-within windows (30/90/180/365 days)

### Billing
- **Fee agreements at client-entity level with case overrides**: set a
  default arrangement on the billing entity and every matter billed to
  it inherits it (shown as "Inherited from entity"); any matter can
  override with its own agreement and later drop the override to fall
  back to the entity default
- **Fee agreements** covering the full arrangement spectrum:
  hourly (configurable 6/15-minute increments), blended hourly (one
  rate for every timekeeper), capped fee (hourly with a ceiling —
  invoices get an automatic cap-adjustment line), fixed/flat fee, and
  stage payments (milestones raised as charges when reached)
- **Task-based billing**: an agreement can require a task (activity)
  code on every time entry; UTBMS-style codes are seeded and manageable
- **Time recording** with automatic rounding to the agreement increment
  and rate resolution from **rule-based rate cards** scoped by any
  combination of timekeeper, grade (partner / attorney / case manager /
  paralegal), client, matter type and task code — null dimensions match
  anything, the most specific matching rule wins (personal beats grade
  beats client/matter/task scoping), ties go to the latest effective
  date, and future-dated uplifts activate automatically. Timekeeper
  grades are managed in Billing Settings, so new joiners inherit their
  grade's rate without a personal rule. Rates convert into the matter's
  billing currency. Rules are managed in a server-side searchable,
  filterable, sortable, paginated table built to stay fast at scale
- **Disbursements** captured at cost in any currency, marked up
  (per-item or agreement default) and converted to the billing currency
- **Exact money storage**: every monetary value is stored as integer
  minor units (fixed-point hundredths) behind a model-level Money cast
  — services and the UI work in ordinary 12.34 values, the database
  holds 1234, and SUM() over integers can never accumulate rounding
  drift. Percentages, FX rates and quantities are ratios, not money,
  and keep decimal types
- **Multi-currency**: billing currency set per client entity (or
  overridden per agreement); daily exchange rates against the firm's
  base currency, synced from an ECB-backed provider
  (`billing:sync-rates`, scheduled weekdays) or maintained by hand.
  Every WIP item stores its value in **both** the billing currency and
  the base currency, converted at capture time — so entity-currency and
  firm-wide base totals are always available and never drift with
  later FX moves (dashboard tile, WIP screens, per-entity ≈ base
  equivalents)
- **Tax rates** (e.g. UK VAT, zero-rated export) assigned per entity
  and snapshotted onto each invoice
- **WIP dashboard** (Billing → WIP): a compact row per billing entity —
  unbilled total, matter count, and an aged "oldest WIP" indicator
  (green/amber/red) — filterable by client and responsible attorney.
  Drill into an entity to review every unbilled item, **amend the
  wording that will appear on the invoice** (time narratives,
  disbursement and charge descriptions — locked once billed), and bill
  a single matter, a ticked selection, or the whole balance
- **Consolidated invoices**: one bill per entity covering multiple
  matters, lines grouped by matter, each matter's WIP converted into
  the entity's currency (per-matter fee caps still respected)
- **Matter budgets**: budgets accumulate per matter — each entry
  records who added it, when, and in what currency (with a frozen base
  value), and stays amendable with the audit trail intact. Consumption
  counts every cost, billed and WIP, with utilisation shown on the
  matter's Billing tab. A **Budgets dashboard** (Billing → Budgets)
  gives client/case managers budget-vs-cost across their portfolio —
  defaulting to the logged-in user's matters — with RAG utilisation
  bars, over-budget highlighting, and add-budget straight from the row
- **Quoting**: numbered quotes with lines, live totals and tax, and a
  draft → sent → accepted/declined pipeline
- **Invoicing**: one click gathers a matter's unbilled WIP onto a draft
  invoice for its billing entity; issue assigns a sequential number and
  payment terms; record part/full payments; void or delete releases the
  WIP for rebilling. Invoicing sits behind an `InvoicingProvider`
  interface, so an external driver (Xero, QuickBooks, Stripe) can take
  over the last mile later without touching the WIP layer

### Reports & E-billing
- **Report builder** (Reports): five practice-shaped datasets —
  matters register, tasks & deadlines, renewals, unbilled WIP items,
  invoices — with shared filters (client, attorney/user, status, date
  window), on-screen results and **CSV export**
- **Saved reports with schedules**: save any definition and have it
  emailed as CSV daily or weekly (`reports:send`, scheduled weekday
  mornings)
- **Client-ready PDF invoices** rendered on the firm letterhead: lines
  grouped by matter, tax breakdown, payments and balance due, the
  entity's PO reference
- **LEDES 1998B export** per invoice: the e-billing format corporate
  legal departments ingest — pipe-delimited, fees (F) and expenses (E)
  typed from the billable behind each line, timekeeper ids/names/
  classifications and UTBMS task codes included

### IPO Integrations — Office Exchange
- **Connector seam per office** (EPO, UK IPO, USPTO, WIPO, EUIPO):
  a file-drop driver reads JSON exchange batches from
  `storage/app/ipo-inbox` (the classic SFTP pattern), and an **API
  driver built on Saloon** (`OfficeExchangeConnector` + request
  classes) covers REST exchanges — set `driver: api` with a base URL
  and token per office; the ingestion pipeline is identical either way
- **Idempotent ingestion** (deduped on the office's message id) with
  **matter matching** by normalised application/registration numbers —
  only an unambiguous match is automated; anything else waits in the
  inbox for review
- **Automation pipeline** per event (publication, grant, registration,
  office action, renewal reminder), fully audit-logged per message:
  copies official numbers/dates onto the matter, moves its status,
  **auto-completes tasks** whose workflow step is marked "completed by
  this office event", **auto-applies event-triggered workflows** (an
  office action fans out its response deadline chain from the event
  date), **records official fees as disbursements** at cost (marked up
  and currency-converted), and **auto-drafts communication templates**
  flagged for the event — drafts only, never sent
- **Integrations inbox**: review queue with office/status filters,
  payload + automation audit per message, assign-matter for unmatched
  messages, process/dismiss, and Poll Now (also scheduled hourly via
  `ipo:poll`)
- **Import from the register**: create a matter straight from an
  office by application number — the register record fills the docket
  (title, type, official numbers and dates, status) and the reference
  is generated from the type's sequence
- **Register reconciliation** (`ipo:reconcile`, scheduled weekly, or
  on demand): every matter is compared field-by-field against the
  office record; drift lands in a review queue on the Integrations
  page with ours-vs-register diffs and one-click "accept office
  values". Absent-from-register results are recorded as informational
- **Outbound submissions**: filings, office action responses, renewal
  payments and documents are drafted as canonical packages built from
  matter data, pushed through the same per-office connector (file-drop
  outbox or Saloon API), and tracked draft → submitted → acknowledged.
  Inbound **receipts** auto-acknowledge the submission, store the
  office reference, and complete the linked docket task; failures keep
  the error and can be retried
- **Per-office payload transformers**: each office's filing dialect is
  an adapter (`OfficePayloadTransformer`) that validates
  office-specific prerequisites before anything is sent and converts
  the canonical package into the office's wire format — the EPO Online
  Filing transformer assembles a Form 1001-style request (applicant
  with full address, representative, title, priority claims) plus a
  computed fee sheet; offices without a dialect send the canonical
  package as-is

### Email — Mailroom & Delivery
- **Real email delivery**: marking an email communication sent
  dispatches it through Laravel Mail on the firm letterhead (letters
  are recorded as sent without delivery)
- **Inbound mailbox capture**: the docketing mailbox lands in the
  Mailroom (`mail:ingest`, scheduled) — emails are matched to matters
  by reference in the subject or any normalised official number in the
  text; matched mail files itself as an inbound communication with its
  **attachments filed as documents**, unmatched mail waits in a review
  queue for one-click filing
- **Daily reminder digests** (`reminders:digest`, weekday mornings):
  each user gets their due/overdue tasks and upcoming renewals by email

### Documents
- **Files on the docket** per matter: user uploads, documents
  **auto-filed from office exchange messages** (an inbound office
  action carries its PDF and lands on the matter with the message
  linked), and **PDFs generated from communication templates** (merge
  fields resolved against the matter, rendered on the firm letterhead
  via dompdf)
- Categories (office action, filed document, receipt, correspondence,
  evidence, generated), rename/recategorise, and download with the
  original filename
- **Versioning**: replacing a document files a new version and keeps
  the superseded one on record; the panel shows current versions and
  every change is in the audit trail

### Application Auditing
- **Every model is audited** via `owen-it/laravel-auditing`: creates,
  updates, deletes and restores are recorded with the acting user, the
  before/after values, IP and URL. Credentials and 2FA material are
  excluded from the log at the model level
- **History timelines** where the work happens: a History tab on the
  matter (covering the matter plus its tasks, renewals, communications,
  billing items, budgets and office submissions) and an audit panel on
  the client screen (client, entities, contacts) — who, when, and each
  field's old → new value
- **Restore any captured state**: every created/updated entry in the
  timeline captures the state it left the record in, and a single
  Restore action applies those values via the package's `transitionTo`
  — pick the point in history you want, no back/forward bookkeeping.
  Restores are saved through the model, so the time-travel itself
  lands in the audit trail

### Client Portal
- **Separate portal guard** at `/portal`: client-side logins (granted
  and revoked by admins from the client's page) see their own
  portfolio and nothing else — matters, upcoming renewals, documents
  (with downloads) and issued invoices with balances, all hard-scoped
  to the portal user's client
- **Renewal instructions**: the killer portal feature — clients answer
  "pay or let lapse?" themselves; instructions land straight in the
  renewals pipeline (instructed with timestamp, or waived)
- The portal session cannot reach the firm's system and vice versa
  (distinct auth guards, firm routes pinned to the web guard)

### Roles, Permissions & Conflicts
- **Access roles** orthogonal to timekeeper grades: administrator
  (everything, including configuration and users), professional
  (day-to-day matter/docket/billing work), finance (billing operations
  plus billing settings) and **read-only** (browses everything they may
  see, changes nothing — enforced by middleware on every mutating
  request, not per-controller)
- Configuration is admin-only (workflows, templates, renewal rules,
  users); billing settings are shared with finance; the last
  administrator can never be demoted
- **Ethical walls** per client: behind a wall, the client and all its
  matters are visible only to the listed users (admins always see
  everything) — enforced on index lists, detail pages, and global
  search alike; managed from the client page
- **Users & Access screen** (admin): grades, access roles and 2FA
  status at a glance
- **Conflict check at intake**: search the whole practice for a name —
  clients, entities, contacts and opposing parties — before taking on
  a new client

### Authentication & Security
- Session auth powered end-to-end by **Laravel Fortify** (headless),
  rendered through the app's Inertia pages: login, registration,
  password reset, email verification, password confirmation
- **Two-factor authentication** (TOTP): QR-code enrolment with setup
  key, code confirmation, single-use recovery codes with regeneration,
  and a login challenge step — all guarded by fresh password
  confirmation and rate-limited, with TOTP replay protection
- 2FA secrets and recovery codes are encrypted at rest and never
  exposed through Inertia page props

### Global Search
- Typeahead search box in the nav (Ctrl/Cmd+K) that filters as you type
- Searches matters (reference, title, official numbers), clients,
  contacts (name/email), client entities, parties, tasks, workflows,
  and communication templates — grouped results with match
  highlighting, keyboard navigation, and direct links

### Dashboard
- Active portfolio stats, overdue tasks, renewals due in 90 days,
  upcoming actions and renewals, recent matters

## UI components

The design system is PrimeVue 4 (Aura preset, indigo primary to match the
Tailwind palette), used both directly and behind thin app-level wrappers
in `resources/js/Components`:

- Structures: **DataTable** (all register pages, with lazy server-side
  pagination driven by Laravel's paginator, and client-side sorting on
  the settings lists), **Tabs** (matter page), **Toast** (flash
  messages), **ConfirmDialog** (destructive actions via the
  `useDeleteConfirm` composable), **Dialog** (modals)
- Form primitives via wrappers with a stable local API: `TextInput`
  (InputText), `TextareaInput`, `SelectInput` (Select), `DateInput`
  (DatePicker with `YYYY-MM-DD` string model), `Checkbox`, the button
  trio (Button severities), `StatusBadge` (Tag)
- Still custom by design: `DueDate` (domain-specific due-date
  colouring) and `GlobalSearch` (grouped multi-entity typeahead)

## Getting started

```bash
composer install
npm install

cp .env.example .env          # configured for MySQL (database: ipms)
php artisan key:generate

# create the MySQL database, then:
php artisan migrate --seed    # seeds demo data
npm run build                 # or: npm run dev

php artisan serve
```

Log in with the seeded demo user: **admin@example.com / password**.

> SQLite also works for local development: set `DB_CONNECTION=sqlite` and
> `touch database/database.sqlite`.

## Testing

**Backend feature tests** (PHPUnit, in-memory SQLite — 276 tests covering
clients, matters, parties, classes, tasks, renewals scheduling rules,
workflow application, stage contracts + matter take-on, billing (time
rounding, rate cards, FX, markup, caps, invoicing, quotes, settings),
office integrations (ingestion, matching, automation, outbound
submissions + EPO payload transformation, receipts), template
rendering, application auditing with audit-trail state restore, and the dashboard):

```bash
php artisan test
```

**End-to-end UI tests** (Playwright, 73 tests driving the real app —
login, navigation, matter/client creation, filtering, task completion,
renewal generation + instruction, the workflow builder and applying
workflows, matter take-on with stage contracts, the billing journey
(log time → invoice → payment), quotes, billing settings, the office
exchange (inbox review, processing, outbound submissions), documents
(upload, template-generated PDFs, office auto-filing), audit history
timelines with state restore, and
template-driven communication composition):

```bash
npm run test:e2e
```

The E2E suite boots `php artisan serve` on port 8123 against a dedicated
seeded SQLite database (`database/e2e.sqlite`) automatically. If your
environment provides its own Chromium build, point the suite at it:

```bash
PLAYWRIGHT_CHROMIUM_PATH=/path/to/chrome npm run test:e2e
```

## Domain model

```
Client ─┬─ Contact (person | mailbox | organisation)
        ├─ ClientEntity (legal entities; one default per client)
        └─ Matter ─┬─ ClientEntity (billing entity, falls back to default)
                   ├─ Contact (pivot: role = main|docketing|billing|reporting|other)
                   ├─ Family (patent families)
                   ├─ Matter (parent/child, e.g. priority → national phase)
                   ├─ Party (pivot: role = applicant|inventor|agent|…)
                   ├─ MatterClass (Nice classes)
                   ├─ Renewal (annuity/renewal cycles) ── RenewalRule (schedule templates)
                   ├─ MatterTask ── WorkflowStep ── Workflow
                   ├─ Communication ── CommTemplate
                   └─ BillingAgreement (matter override OR entity default)
                                        ─┬─ BillingAgreementStage
                                        ├─ TimeEntry (rate via RateCard)
                                        ├─ Disbursement (markup + FX)
                                        ├─ Charge (fixed fee | stage payment)
                                        └─ Invoice ─┬─ InvoiceLine (bills WIP items)
                                                    └─ Payment
Quote ── QuoteLine (client / entity / matter, tax snapshot)
TaxRate · ExchangeRate · ActivityCode · RateCard   (billing reference data)
```

## Backend architecture

Controllers are thin HTTP adapters; the domain lives below them, so a
JSON API can reuse the same building blocks without touching web code:

```
Http/Controllers   validate (FormRequest) -> call action/repository -> respond
Http/Requests      all validation rules (shared by web now, API later)
Actions/           one class per state change (CreateClient, LinkContact,
                   ApplyWorkflowToMatter, UpdateRenewal, SaveWorkflow, ...);
                   business-rule violations raise DomainActionException
Repositories/      all Eloquent queries: filtered pagination, option lists,
                   typeahead search, dashboard counts
Services/          domain services composing repositories:
                   RenewalScheduler, WorkflowRunner, TemplateRenderer,
                   SearchService, DashboardService, ExchangeRateService,
                   RateResolver, InvoiceBuilder; Invoicing/ holds the
                   InvoicingProvider seam (internal driver today, an
                   external Xero/Stripe driver later)
Models/            relationships, casts, scopes, and per-model domain
                   helpers (schedule computation, default-entity switch)
```

Web controllers turn `DomainActionException` into flash errors; an API
controller would map the same exceptions to 422 responses and reuse the
identical FormRequests, actions, and repositories.
