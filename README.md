# CodeQuest

A LAN-deployable coding debugging assessment system (PHP 7.4+/8.x + MySQL/MariaDB).

Mirrors the Blitzkrieg three-tier model: **Aptitude → Program Writing → Debugging**, with case-study driven questions, timed assessments, automated scoring, anti-cheat controls, and CSV/JSON/PDF data portability.

## Requirements

- PHP 7.4+ (8.x recommended) with PDO_MySQL
- MySQL 5.7+ / MariaDB 10.3+
- Apache 2.4 (or Nginx + PHP-FPM)
- No Composer, no npm, no internet required at runtime

## Install

1. Copy the `CoderQuest` folder into your web root (e.g. `htdocs/` in XAMPP).
2. Start Apache and MySQL.
3. Edit `config.php` with your DB credentials.
4. Open `http://<LAN-IP>/CoderQuest/install.php` in a browser and click **Install Now**.
5. Default admin: `admin` / `admin123` — **change this password immediately** (via a fresh `install` not needed; edit is done in DB or re-run seed after editing config defaults).
6. Optional sample data: open `seed.php` once to load case studies, questions, and a demo exam.

For PDF exports, place FPDF at `vendor/fpdf/fpdf.php`. If absent, PDF export falls back to a printable HTML page that opens the browser print dialog.

## Layout

```
CoderQuest/
├── config.php            DB credentials + app constants
├── install.php           One-click database installer
├── schema.sql            Full database schema
├── seed.php              Sample data seeder
├── index.php             Candidate login
├── register.php          Candidate self-registration
├── quiz.php              Exam runner (one question per page)
├── result.php            Score summary with level breakdown
├── auto_submit.php       Scoring engine
├── beacon.php            Anti-cheat violation logger
├── logout.php
├── admin/                All admin pages (require admin session)
│   ├── login.php  index.php  questions.php  question_edit.php
│   ├── case_studies.php case_study_edit.php
│   ├── exams.php  exam_edit.php  candidates.php
│   ├── reports.php  settings.php  export.php  import.php  logout.php
├── includes/
│   ├── helpers.php       CSRF, escaping, settings, sessions
│   └── layout.php        Admin/candidate page shells
├── assets/
│   ├── css/style.css     Main stylesheet
│   ├── css/quiz.css      Exam runner styles
│   └── js/               timer.js, anti_cheat.js, single_tab.js
└── schema.sql
```

## Screens

| Screen | Route | Purpose |
|---|---|---|
| Candidate Login | `/index.php` | Authenticate candidate |
| Register | `/register.php` | Create candidate account |
| Exam Runner | `/quiz.php` | Deliver questions, one per page |
| Result | `/result.php` | Show score summary |
| Admin Login | `/admin/login.php` | Authenticate admin |
| Dashboard | `/admin/index.php` | Overview counts |
| Questions | `/admin/questions.php` | List/filter questions |
| Question Editor | `/admin/question_edit.php` | Create/edit question |
| Case Studies | `/admin/case_studies.php` | List case studies |
| Case Study Editor | `/admin/case_study_edit.php` | Create/edit case study |
| Exams | `/admin/exams.php` | Configure exams |
| Candidates | `/admin/candidates.php` | Manage candidates |
| Reports | `/admin/reports.php` | Results and analytics |
| Settings | `/admin/settings.php` | Anti-cheat toggles |
| Export | `/admin/export.php` | Download datasets |
| Import | `/admin/import.php` | Upload datasets |

## Features

- **Questions**: MCQ, fill-in-the-blank, code-choice across aptitude/programming/debugging levels, attachable case studies, draft/published workflow.
- **Exams**: select published questions, timed duration, passing percent, single active exam.
- **Attempts**: resume in-progress, auto-submit on timeout, per-question persistence.
- **Scoring**: level-wise breakdown stored as JSON, pass/fail on passing percent, deterrent anti-cheat controls (shortcut blocking, blur overlay, PrintScreen detection, DevTools heuristic, single-tab lock, fullscreen option).
- **Import/Export**: CSV (UTF-8 BOM), JSON, PDF (or print-to-PDF fallback). Questions/candidates import with preview, validation, transactional commit, and import logging.
- **Security**: bcrypt passwords, PDO prepared statements, CSRF tokens, HTML escaping, session-based auth.

## Quick Start with PHP Built-in Server

For local smoke tests without Apache:

```bash
cd CoderQuest
php -S localhost:8080
# open http://localhost:8080/install.php
```

## Known Limitations

- Free-form code execution is not graded (deferred to a future Docker validator).
- Anti-cheat is a deterrent, not a guarantee; physical invigilation is assumed.
- Single-tab lock is client-side best-effort.
- PDF import is not supported (export only).

## License

This work is licensed under the [Creative Commons Attribution 4.0 International License](https://creativecommons.org/licenses/by/4.0/).

Copyright (c) 2026 Sai Karun Nandipati · https://karun99.github.io