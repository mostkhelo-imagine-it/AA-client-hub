# AA New Reality - Client DB

Internal tool for AA and the team: client contacts, course history, and
1-on-1 progress for the Reality Creator clients. Follows the design spec
(roles, tiers, data model) worked out separately — this is the Phase 1–4
implementation of that spec, plain PHP + MySQL, no framework, no build step.

## Roles

Two: **AA** (owner) and **Staff** (everyone else). Staff have the same
access to client data as AA — add, edit, delete clients; log courses,
sessions, and contracts; import; view the activity log. The only thing
reserved for AA is managing staff accounts themselves — creating,
disabling, or removing one. The exact rule for every action lives in
`src/Access.php`, in one place — that file is the source of truth if
this summary and the code ever disagree.

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
  Csv.php          ← generic CSV reader (BOM/delimiter handling) used by the importer
  Xlsx.php         ← dependency-free .xlsx reader (ZipArchive + SimpleXML)
  Tabular.php      ← picks Csv vs Xlsx by file extension
  ImportMapper.php ← guesses CSV/Excel column → client field from header text
  controllers/
  views/
scripts/
  import_fluentcrm_contacts.php  ← one-time migration from a FluentCRM contacts CSV
storage/
  imports/         ← uploaded CSVs land here briefly, deleted right after import (git-ignored)
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
   use **Staff → Add staff account** to create real staff logins.

If any of this needs SSH, a cron job, or anything File Manager can't do,
that's exactly what AA's dev team is there for — nothing here requires it,
but nothing here avoids it either.

## Schema changes after your first import

`schema.sql` now has a UNIQUE index on `clients.email` (added after
duplicate contacts turned out to be possible). `CREATE TABLE IF NOT
EXISTS` won't retroactively add that to a database you already created —
if `php -S` ever errors with `Duplicate entry ... for key
'uq_clients_email'` when adding the constraint below, find and resolve
the duplicate first:

```sql
SELECT id, full_name, email FROM clients WHERE email = 'the@duplicate.email' ORDER BY id;
-- decide which row to keep, then:
DELETE FROM clients WHERE id = <the one to remove>;
-- note: this cascades to that client's course records, contracts, and
-- session logs too (see schema.sql's foreign keys) — make sure you're
-- deleting the right one.
```

Then apply the constraint:

```sql
ALTER TABLE clients ADD UNIQUE KEY uq_clients_email (email);
```

A brand-new database created from the current `schema.sql` already has
this — this section only matters for a database set up before this
change.

`users.role` is just `aa` or `staff` now (see **Roles** above) — a
short-lived `super_admin`/`admin`/`assistant` split existed for a bit
and is gone. If your database ever had those values, collapse them to
`staff` before narrowing the enum:

```sql
UPDATE users SET role = 'staff' WHERE role <> 'aa';
ALTER TABLE users MODIFY role ENUM('aa','staff') NOT NULL;
```

The `client_assignments` table (used to scope Assistants to specific
clients) is gone too — every Staff account now sees every client, so
there's nothing left to assign. Drop it if it exists:

```sql
DROP TABLE IF EXISTS client_assignments;
```

A brand-new database from the current `schema.sql` already has all of this.

## Importing clients from any CSV or Excel file

**Clients → Import** handles this in the browser now —
upload any CSV or .xlsx file, and it guesses which column feeds which
field from the header text (Full Name, E-mail, Cell #, whatever the
export calls them), shows you the guess next to a preview of the actual
data, and lets you override any of it before anything is saved.

Excel support (`src/Xlsx.php`) is a small dependency-free reader — no
PhpSpreadsheet, no Composer — built on PHP's bundled `ZipArchive` and
`SimpleXML` (an .xlsx is just a zip of XML files under the hood). Both
ship with PHP by default and Homebrew's `php` formula includes them, so
this should just work, but if a server ever has them compiled out, the
import screen shows a clear error rather than a blank crash. Only the
first sheet is read, only modern .xlsx (not the legacy binary .xls), and
formulas come through as their last-saved value rather than being
recalculated — none of which matters for the columns this importer
actually maps.

It also supports an optional
tags/labels column with keyword matching for tier (e.g. a tag containing
"premium" sets Premium), skips duplicate emails by default, and — like
the CLI script below — never auto-promotes a keyword match to Reality
Creator; it flags those rows on the results screen for a manual check
instead, since a tag isn't a signed contract. This is the right tool for
a one-off spreadsheet or an export from something other than FluentCRM.

The uploaded file is deleted from the server the moment the import
finishes (success or failure) — it's never kept around or committed
anywhere (`storage/imports/` is git-ignored for this reason too).

## Migrating existing contacts (FluentCRM/FluentCommunity specifically)

For the FluentCRM export format specifically, `scripts/import_fluentcrm_contacts.php`
is a more opinionated alternative to the in-app importer above — it
already knows that format's columns, filters out obvious test rows, and
catches likely duplicate people under different emails. Run it as a **dry
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

Built (Phases 1–4 from the spec): login + forced password reset, two
roles (AA and Staff, both with full access to client data — AA alone
manages staff accounts), client directory + profile with edit/delete,
course catalog, manual course-record logging, session logs, staff
accounts, activity log, and a general CSV/Excel client importer.

Disabled for now, to rebuild later: the Reality Creator contract
screens (open/renew a contract, the expiry review queue). The code is
still in `src/controllers/ContractController.php` and
`src/views/contracts/`, and the `contracts` table is still in
`schema.sql` — just unhooked from routes and nav
(`public/index.php`, `src/views/layout/app.php`) and from the client
profile page. Re-wire those to bring it back.

Not built yet, on purpose (Phase 5 per the spec): the FluentCommunity/
FluentCart webhook that automates course-purchase sync. That's the last
phase — added once the manual flow above has been running in daily use
and is stable, not before.
