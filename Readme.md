<p align="center">
  <img src="https://img.shields.io/badge/ElimuHub-CBE%20School%20Platform-166534?style=for-the-badge&logoColor=white" alt="ElimuHub" />
</p>

<h1 align="center">ElimuHub</h1>
<p align="center">Multi-school CBE management platform by STETECH LIMITED</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=flat&logo=laravel" alt="Laravel 12" />
  <img src="https://img.shields.io/badge/Livewire-3-fb70a9?style=flat" alt="Livewire 3" />
  <img src="https://img.shields.io/badge/CBE-Kenya-166534?style=flat" alt="CBE Kenya" />
  <img src="https://img.shields.io/badge/Multi--school-enabled-1d4ed8?style=flat" alt="Multi-school" />
</p>

## What this repository contains

ElimuHub is a Laravel application for managing Kenyan CBE school operations. It supports a platform super-admin who manages schools and plans, and school users who work only within their own school.

This document deliberately lists only functionality that exists in this repository. A configured provider account, a deployed queue worker, and real test data are still required before a provider integration is live.

## Implemented modules

| Module | Current capability |
|---|---|
| Multi-school platform | Super-admin dashboard, school creation, school locking, subscriptions, plan feature gates, platform settings, audits, broadcasts, support tickets, and safe school-admin impersonation. |
| School setup | Per-school classes, subjects, grading scales, academic years/terms, teacher-subject allocations, learner admission prefixes, and school settings. New schools receive Grade 1–9 CBE setup. |
| People | Learner, staff, guardian and user-account management; CSV imports; learner portal accounts; roles and permissions. |
| Assessment and exams | CBC assessment entry, grouped multi-subject exams, marks entry/import, review/finalize/publish workflow, grading scales, report cards, merit lists, and exam timetables. |
| Timetables | Conflict-aware class timetable generation from teacher allocations and class subjects; draft/publish flow; teacher timetable; printable class weekly grid; exam timetable generation. |
| Fees | Fee structures, invoices, payment recording, receipts, arrears views, and Daraja integration endpoints. |
| Communications | In-app notifications, platform broadcasts, SMS sending/logging and test SMS screen. SMS delivery requires a real configured SMS provider and paid school credits. |
| Documents | Per-school logo, signature and stamp settings; branded report cards, merit lists, receipts, ID cards/certificates, Drive document storage, and printable exports. |
| Notes and portals | Learning notes, teacher/parent/learner portals, published learner results, notes, notifications, and support tickets. |
| Inventory | Categories, items and stock transactions. |
| KEMIS | KEMIS configuration screen, API endpoints, sync logs, and data validation paths. A real KEMIS service/API is required for live synchronisation. |
| Operations | Google Drive connector, database backups, maintenance mode, legal/cookie settings, activity/system logs, and diagnostic tools. |

## Provider-dependent features

These code paths are present but cannot be called live until each school or platform administrator supplies valid credentials and tests the connection:

- Safaricom Daraja M-Pesa payments and subscription STK Push
- Olympus SMS sending and delivery status
- Google Drive OAuth storage and record sync
- KEMIS API synchronisation
- Firebase notifications

## Not implemented yet

The following are planned and must not be marketed as available until their database models, user interface, permissions, and end-to-end flows are built:

- AI/Claude lesson, marking, question, insight, and report-comment tools
- Homework assignment and learner submission workflow
- Transport, vehicles, routes, GPS tracking, and driver portal
- HR leave, payroll, TPAD, professional-development workflow
- Question bank, past-paper archive, and exam authoring
- Online classes and video-provider integration
- PTA/BOG governance, pathway selection, bursary applications, feeding programme, talent pipeline, USSD, and offline-first conflict sync
- Advanced analytics such as strand heatmaps, at-risk detection, and national benchmarking

## Roles

Access is controlled through Spatie permissions and route middleware. The actual visible modules depend on the role permissions configured by the platform or school administrator. The primary active portals are:

- Super admin — platform-wide school, plan, billing, support, audit and settings management
- School administration — school setup, staff, learners, finance, academics and reports as permitted
- Teachers — allocated learners/classes, assessment, exams, notes and timetable as permitted
- Finance users — fees, payments, receipts and finance reports as permitted
- Parents and learners — scoped portals for their linked records, notes, results, notifications and support

## Branding and school isolation

Each school has independent settings and branding. Its uploaded logo is used on its reports, merit lists, official forms, receipts, and printable documents. Queue jobs reload tenant settings so one school's branding and configuration cannot appear in another school's output.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

For queues and scheduled work in development, run:

```bash
php artisan queue:work
php artisan schedule:work
```

Production requires a configured database, `APP_KEY`, queue worker, scheduler, storage configuration, and the provider credentials needed for any enabled integration.

## Security notes

- Never commit `.env`, API tokens, OAuth refresh tokens, or real passwords.
- Change all seeded/default credentials before production use.
- School-admin and super-admin login defaults are generated from initials plus a serial value, shown once, and sent by SMS when a phone number is available.
- School admins can reset user passwords from User Accounts and impersonate same-school users through the audited Impersonate Users screen.
- Configure M-Pesa, SMS, Drive, KEMIS, and Firebase in the appropriate platform or school settings, then use the application's test actions before enabling live workflows.

## License

MIT

<p align="center"><strong>ElimuHub · @STETECH LIMITED</strong></p>
