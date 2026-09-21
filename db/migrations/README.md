# Migrations

This directory is empty until the first schema change after initial launch.
It's the hand-rolled equivalent of `artisan migrate` for this no-framework
plain-PHP app, run through `public/upgrade.php` (there's no SSH/CLI access
guaranteed on Mijndomein shared hosting — see CLAUDE.md).

## How it works

- `db/schema.sql` is always the **full current schema** — what a brand new
  install gets, via `public/install.php`.
- Each file in here is **one incremental change** applied to an *existing*
  install that's behind, via `public/upgrade.php`.
- `install.php` marks every migration file that exists at install time as
  already-applied (their effects are already folded into `schema.sql`), so
  a fresh install never replays history — only existing sites catch up.

## Adding a migration

1. Create `db/migrations/YYYY_MM_DD_NNN_description.sql`
   (e.g. `2026_10_03_001_add_model_print_settings.sql`), where `NNN` is a
   two-or-more-digit counter for same-day ordering. Files run in filename
   sort order.
2. Write plain SQL statements, one per line ending in `;`. Keep each
   migration additive/non-destructive where possible (`ADD COLUMN`,
   `CREATE TABLE IF NOT EXISTS`, backfill `UPDATE`s) since it will run
   against a live site's real data.
3. **Also apply the same change to `db/schema.sql`** so a fresh
   `install.php` run ends up at the same schema without replaying every
   migration ever written.
4. Commit both files together. Deploy the updated code, then visit
   `/upgrade.php` (logged in as admin) and click "Run migrations".

## Applying on Mijndomein

`upgrade.php` is a normal page at `https://yourdomain/upgrade.php`,
gated behind admin login — upload the new code (including the new
migration file) via FTP/Plesk file manager, then open that URL and confirm.
No SSH needed.
