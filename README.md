# IPMS — Intellectual Property Management System

A full-stack IP practice management system inspired by market-leading IPMS
platforms (Clarivate Inprotech, Patricia, and similar): matter/docket
management, deadline-driven workflows, templated client communications, and
renewal/annuity management.

**Stack:** Laravel · Vue 3 · Inertia.js · Tailwind CSS · MySQL

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

### Clients & Contacts
- Client records with codes, billing details, and multiple named contacts
- Per-client matter portfolio view

### Dates, Actions & Workflow
- Tasks with official due dates, soft internal deadlines, priorities,
  critical (statutory) flags, and assignees
- Workflow templates: reusable deadline chains triggered by an event
  (filing, publication, grant, registration, office action, or a manual date)
- Applying a workflow to a matter fans out its steps into tasks with due
  dates offset from the trigger date
- Global task list with my-tasks / overdue / status filters

### Templated Communications
- Email and letter templates with `{{merge.fields}}` (matter, client,
  contact, attorney, dates — see the in-app merge field reference)
- Compose from a matter: template rendered live against the matter's data,
  editable, saved as draft, then marked sent (a permanent record)

### Renewals
- One-click schedule generation per matter type:
  patents (annuities years 2–20 from filing), trade marks (10-year terms),
  designs (5-year terms), domains (annual)
- Status pipeline: upcoming → reminder sent → instructed → paid,
  plus lapsed/waived, with instructed/paid timestamps and fee tracking
- Renewals control centre with due-within windows (30/90/180/365 days)

### Dashboard
- Active portfolio stats, overdue tasks, renewals due in 90 days,
  upcoming actions and renewals, recent matters

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

**Backend feature tests** (PHPUnit, in-memory SQLite — 76 tests covering
clients, matters, parties, classes, tasks, renewals scheduling rules,
workflow application, template rendering, and the dashboard):

```bash
php artisan test
```

**End-to-end UI tests** (Playwright, 23 tests driving the real app —
login, navigation, matter/client creation, filtering, task completion,
renewal generation + instruction, the workflow builder and applying
workflows, and template-driven communication composition):

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
Client ─┬─ Contact
        └─ Matter ─┬─ Family (patent families)
                   ├─ Matter (parent/child, e.g. priority → national phase)
                   ├─ Party (pivot: role = applicant|inventor|agent|…)
                   ├─ MatterClass (Nice classes)
                   ├─ Renewal (annuity/renewal cycles)
                   ├─ MatterTask ── WorkflowStep ── Workflow
                   └─ Communication ── CommTemplate
```

Key services (`app/Services`):

- `RenewalScheduler` — generates type-specific renewal schedules (idempotent)
- `WorkflowRunner` — expands a workflow template into matter tasks
- `TemplateRenderer` — resolves merge fields for communications
