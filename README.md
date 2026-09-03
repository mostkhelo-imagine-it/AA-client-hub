# Client Hub

Internal tool for AA and the team: client contacts, course history, and
1-on-1 progress for the Reality Creator clients. Follows the design spec
(roles, tiers, data model) worked out separately — this is the Phase 1–4
implementation of that spec, plain PHP + MySQL, no framework, no build step.

## Stack

- PHP 8.1+ (uses `never` return types and constructor property promotion
  patterns compatible with 8.0+, but written against 8.1)
- MySQL 5.7+ / MariaDB 10.3+
- No Composer dependencies — `.env` is parsed by hand in `src/config.php`
  specifically so this deploys from a plain zip with nothing to `composer
  install`, and works identically with cPanel's Git Version Control pull.

## Project layout

```
public/            ← document root — point the subdomain here
  index.php        ← front controller / router entry point
  .htaccess        ← rewrites everything through index.php (Apache/cPanel)
  router.php       ← same job, for `php -S` local testing only
  assets/style.css
src/
  config.php       ← loads .env
  Db.php           ← PDO singleton
  Auth.php         ← login/session/role checks
  Access.php       ← every "who can see/do what" rule from the spec, in one place
  Activity.php     ← activity_log writes
  Router.php       ← ~50-line router, no dependency
  controllers/
  views/
scripts/
  import_fluentcrm_contacts.php  ← one-time migration from a FluentCRM contacts CSV
schema.sql         ← full database schema + a seeded AA account
.env.example       ← copy to .env and fill in
```

## Local setup

1. Install MySQL locally (or use a free tier like a local MariaDB container) and PHP 8.1+.
2. `cp .env.example .env` and fill in `DB_*` for a local database. Set `APP_ENV=local` for readable error output.
3. Create the database and import the schema:
   ```
   mysql -u root -p -e "CREATE DATABASE aa_clienthub CHARACTER SET utf8mb4"
   mysql -u root -p aa_clienthub < schema.sql
   ```
4. Serve it with PHP's built-in server (no Apache needed locally). It needs
   the router script below because, unlike Apache/cPanel, the built-in
   server doesn't read `.htaccess` — without it, everything except `/`
   404s instead of reaching `index.php`:
   ```
   php -S localhost:8000 -t public public/router.php
   ```
5. Visit `http://localhost:8000`. Log in with `aa@example.com` / `changeme123`
   — **change that email in `schema.sql` before this ever touches a real
   deployment**, and the app forces a password reset on that first login.

## Deploying to cPanel

The app expects the subdomain's **document root to point at `/public`**
— when you create the subdomain in cPanel, there's a document root field;
set it to `client-hub/public`, not `client-hub`. That's what keeps
`schema.sql`, `.env`, and everything in `src/` unreachable from a browser.
(A root `.htaccess` is included as a fallback that redirects into
`/public` if the document root ever ends up pointed at the repo root
instead — belt and suspenders, not a substitute for setting it correctly.)

Two ways to get the code onto the server, both fine:

**Git Version Control** (cPanel feature, if enabled on the account) — point
it at this repo, pull, and every future deploy is another pull. Ask AA's
dev team to confirm this is enabled; if it's not, the fallback is:

**File Manager zip upload** — zip the repo, upload, extract. Works on any
cPanel account with zero extra setup, just more manual on every update.

Either way, after the code is on the server:

1. Create a MySQL database and user in cPanel's **MySQL Databases** tool.
2. Import `schema.sql` via **phpMyAdmin** (or `mysql` over SSH if available).
3. Create `.env` on the server (cPanel File Manager can create/edit it
   directly — it's git-ignored so it never comes from the repo) with the
   real `DB_*` values and `APP_URL`.
4. Turn on **AutoSSL** for the subdomain (free, one click in cPanel) so the
   whole app runs over HTTPS — sessions and passwords depend on this.
5. Log in with the seeded AA account, reset the password immediately, then
   use **Staff → Add staff account** to create real admin/assistant logins.

If any of this needs SSH, a cron job, or anything File Manager can't do,
that's exactly what AA's dev team is there for — nothing here requires it,
but nothing here avoids it either.

## Migrating existing contacts

`scripts/import_fluentcrm_contacts.php` reads a FluentCRM/FluentCommunity
"contacts export" CSV and imports it into `clients`. Run it as a **dry
run first** — it changes nothing in the database until you pass `--commit`:

```
php scripts/import_fluentcrm_contacts.php /path/to/contacts_export.csv
```

It prints counts and writes a handful of `review-*.csv` files next to the
source file — rows with no email, rows that look like test data, possible
duplicate people under different emails, and contacts tagged with a
program/course (candidates worth checking for Reality Creator status,
since a program tag isn't the same claim as "has a signed 1-on-1
contract" — see the comment at the top of the script for why that's a
manual call rather than automatic).

Once the dry-run output looks right:

```
php scripts/import_fluentcrm_contacts.php /path/to/contacts_export.csv --commit
```

This only imports contacts as Basic or Premium (based on the tag
configured at the top of the script). **No client is auto-promoted to
Reality Creator** — set that tier and add their contract by hand, from
each of the real 10 clients' profile pages, after the import.

Course attendance/purchase history isn't in this export format, so that
part of the migration is still a manual pass per client — or a second
script once we know what format that data is actually in (a course
platform export, a spreadsheet, FluentCommunity's own course data, etc.).

## What's built vs. what's next

Built (Phases 1–4 from the spec): login + forced password reset, role
scoping (AA/Admin see everyone, Assistants see only assigned clients),
client directory + profile, course catalog, manual course-record logging,
Reality Creator contracts with the renew-or-drop-to-Basic review queue,
session logs with AA-only delete, staff accounts + assignments, activity
log.

Not built yet, on purpose (Phase 5 per the spec): the FluentCommunity/
FluentCart webhook that automates course-purchase sync. That's the last
phase — added once the manual flow above has been running in daily use
and is stable, not before.
