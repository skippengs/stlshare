# CLAUDE.md — STL Sharer

This file records project facts, decisions, and answers gathered during planning
(2026-09-21) so future work doesn't need to re-derive or re-ask them. See
[plan.md](plan.md) for the actual build plan.

## What this project is

A website for uploading, browsing, and viewing STL (3D printable model) files,
with user accounts, favoriting, and collections. A later phase adds simple
in-browser editing of uploaded models — starting with "enlarge this hole" as
the first concrete edit operation.

## Hosting environment — Mijndomein.nl

- **Plan:** Webhosting Plus (confirmed by user). Roughly €7.50–8.50/mo tier.
  - ~20 GB SSD storage
  - Up to 10 MySQL databases
  - Unlimited data transfer, free SSL, unlimited email addresses
  - Managed via **Plesk** control panel
- **No dedicated VPS / root access.** Mijndomein has been phasing out its
  Managed VPS product (declining demand) — do not design around VPS-only
  features. Plesk on shared hosting gives a web-based SSH shell as the Plesk
  admin user, not true root.
- **PHP + MySQL are guaranteed** on every Mijndomein webhosting tier — this is
  the safe, zero-friction baseline.
- **Node.js is *not* guaranteed.** Plesk has a Node.js Toolkit extension that
  *can* be enabled, but availability depends on the specific shared-hosting
  node and isn't something to rely on without confirming with Mijndomein
  support first.
- No background workers / long-running processes / job queues in the
  traditional sense — shared PHP hosting runs PHP per-request (or via cron for
  scheduled jobs). Anything CPU-heavy (mesh processing, thumbnail rendering)
  should happen **client-side in the browser**, not on the server.

## Key decisions from planning Q&A

1. **Backend stack: PHP + MySQL, no framework (plain PHP).** PHP+MySQL chosen
   because it's the only stack Mijndomein guarantees across all webhosting
   tiers. Framework choice went through two rounds: first Laravel (user
   decision, 2026-09-21), then reconsidered after weighing alternatives
   (CodeIgniter 4, Symfony, Slim, WordPress+plugin) — **final decision:
   plain PHP, no framework** (user decision, 2026-09-21). This sidesteps
   Laravel's biggest hosting risks entirely: no Composer dependency, no
   custom document-root requirement, no SSH/`artisan` dependency for
   migrations. Trade-off, accepted deliberately: routing, sessions, CSRF,
   password hashing, and the DB access layer are all hand-written rather
   than provided by a framework — see "Plain PHP on Mijndomein" below for
   what that means in practice.
2. **Storage scale target: small/hobby.** Expected usage is the user plus a
   small group — a few hundred models, comfortably within the 20 GB Plus
   quota. We are **not** front-loading external object storage (e.g. S3 /
   Backblaze B2) — files live directly on the hosting disk under a web-
   inaccessible or access-controlled directory. If usage later grows past
   ~15 GB, revisit and add an object-storage layer (this is called out as a
   seam in plan.md so it's not a rewrite later).
3. **STL viewing: fully client-side.** Use a WebGL-based STL viewer (three.js
   + `STLLoader`) that runs entirely in the browser. The server's only job is
   to serve the raw STL file (and maybe a cached thumbnail); it does no
   rendering or geometry processing itself.
4. **Future editing feature: "easy hole enlarging."** The user's specific
   vision for phase 3 is *not* generic transforms (move/scale/rotate) but a
   targeted, real geometry edit: detect a circular/cylindrical hole in a
   model and let the user enlarge its diameter, then re-export a valid STL.
   This is genuine mesh editing (a boolean/local-geometry operation), not just
   a transform — it has real architectural implications *now*:
   - It must happen **client-side** (WASM), since shared PHP hosting cannot
     run heavy geometry kernels server-side.
   - Likely approach: a WASM CSG/geometry library (e.g. `manifold-3d`,
     or `three-bvh-csg` for simpler cases) doing a boolean subtract of a
     larger cylinder at the detected hole location, then re-triangulating
     and re-exporting as STL.
   - Hole *detection* (finding circular edge loops / cylindrical bores in an
     arbitrary mesh) is the hard part and needs its own research spike when
     we get to phase 3 — not solved here, just flagged.
   - Implication for phase 1/2: store the *original* uploaded STL immutably,
     and treat any edited output as a new derived file/version (don't edit
     in place) — makes the future edit feature additive rather than a schema
     change.
5. **Closed registration with admin approval (user decision, 2026-09-21).**
   The first account ever created becomes admin automatically; every account
   after that is created `pending` and can't access the site until the admin
   approves it. The admin can also suspend an already-approved user later.
   Reasoning: this is a small/hobby-scale, closed-group site, not a public
   platform — open registration adds abuse/moderation surface (spam
   accounts, copyright dumping, quota exhaustion) with no upside at this
   scale. This is now baked into phase 2's auth design (see plan.md), not an
   add-on — every route needs an approved-status check, not just the login
   page.
6. **GDPR protections built in from phase 2, not deferred.** Minimal data
   collection (email, password hash, display name only), a privacy policy +
   terms page with recorded consent at signup, self-service/admin account
   deletion that removes personal data and files (right to erasure), and
   deliberately no non-essential cookies (so no cookie-consent banner is
   needed — keep it that way unless analytics get added later). Reasoning:
   Mijndomein is a Dutch host and the user is EU-based, so GDPR isn't
   optional; doing it from phase 2 is far cheaper than retrofitting deletion
   logic onto an existing user base later.
7. **Per-user upload storage quota.** A global default quota (suggested
   1–2 GB per user, exact number still open — see Open Items) enforced
   server-side at upload time, with an admin-configurable per-user override
   and an admin-visible total-disk-usage view. Reasoning: the whole app
   shares one 20 GB disk (see decision #2) — without a per-user cap, one
   account (malicious or just careless) can exhaust the quota for everyone.
   This pairs naturally with the closed-registration model above, since the
   admin already reviews every new user and can set their limit at approval
   time if needed.

## Plain PHP on Mijndomein — what this simplifies, what we hand-roll

Dropping the framework removes most of the hosting risk that Laravel would
have introduced. What's still worth confirming, and what we now own
ourselves:

- **No custom document root needed.** The app's front controller can sit
  wherever Plesk's default webroot is (e.g. `httpdocs/`) — this was the
  single riskiest unknown with Laravel and it's now moot.
- **No Composer required.** We can write straight PHP files with a tiny
  manual autoloader (`spl_autoload_register`), or optionally still use
  Composer *locally only* purely for autoloading/dependency organization and
  upload the resulting `vendor/` folder via FTP — never a hard requirement
  on the server itself.
- **No SSH/CLI dependency.** Database schema changes are plain `.sql` files
  run through Plesk's phpMyAdmin/DB tool (or a tiny custom PHP migration
  runner script we write ourselves) instead of `artisan migrate`.
- **Extensions actually needed are minimal:** PDO + `pdo_mysql`, `mbstring`,
  `fileinfo` (for upload MIME/type sniffing), `session` — all standard on
  virtually any PHP install, much shorter list than Laravel's.
- **What we hand-roll (own carefully, since there's no framework guardrail):**
  - A minimal front-controller router (map request path → handler function).
  - Session-based auth using PHP's built-in `password_hash()` /
    `password_verify()` — these are already solid, no framework needed for
    correct password hashing.
  - CSRF protection: generate a per-session token, verify it on every
    state-changing POST — must not be skipped since nothing enforces it
    automatically.
  - All database access via PDO **prepared statements only** (never string-
    interpolated SQL) — this is the main security risk area without an ORM,
    so it needs discipline/a thin wrapper class from day one, not ad hoc
    queries scattered through the codebase.
  - Templating: plain PHP templates/partials, with manual `htmlspecialchars()`
    escaping on every output of user-supplied data (titles, descriptions,
    filenames) — no automatic escaping like Blade provides, so this needs to
    be a strict habit, ideally wrapped in a small `e()` helper function used
    everywhere.
  - Input validation (upload file type/size, form fields) written as small
    reusable functions rather than a validation library.
- **Mail:** PHP's `mail()` or a manual SMTP call to the domain's own
  Mijndomein mailbox for registration/approval notification emails.
- **Cron:** Plesk's cron UI still works the same for any scheduled cleanup
  script we might add later (e.g. deleting orphaned files).
- **Schema setup/changes without SSH:** built as two small web-reachable
  scripts rather than left as a someday-idea — see "install.php / upgrade.php"
  below.

## install.php / upgrade.php (built 2026-09-21)

Since there's no SSH/`artisan migrate` on Mijndomein shared hosting, schema
setup and later schema changes both happen through plain PHP scripts you hit
with a browser after uploading code via FTP/Plesk file manager.

- **`public/install.php`** — one-time setup. Checks PHP version/extensions
  and that `storage/`/`src/` are writable, then takes DB host/port/name/
  user/password in a form, tests the connection, runs `db/schema.sql`
  against it, marks any pre-existing `db/migrations/*.sql` files as already
  applied (see below), and writes `src/config.local.php` with the submitted
  credentials (via `var_export`, not string interpolation — safe against a
  malicious DB name/user containing PHP syntax).
  - **Refuses to run at all if the configured database already has a `users`
    row** — checked *before* it will even show the form, using whatever
    `config('db')` currently resolves to, not whatever gets typed into the
    form. This means it can't be pointed at a different, not-yet-installed
    database on a site that's already live; it's a "did this exact site get
    installed" guard, not a general "is some DB installed" guard.
  - The success/already-installed screens both say to **delete
    `public/install.php` from the server** — it's not worth leaving a setup
    script reachable, even a self-defusing one, on a live site.
- **`public/upgrade.php`** — migration runner, gated behind
  `require_admin()` (unlike install.php, which by definition runs before any
  admin account exists). Compares `db/migrations/*.sql` against the
  `schema_migrations` table, shows what's pending, and applies it on a
  confirmed POST. See `db/migrations/README.md` for the authoring convention
  — short version: `db/schema.sql` is always the full current schema (what
  `install.php` gives a fresh site); files in `db/migrations/` are
  incremental deltas for a site that's already live and behind, and each one
  needs the same change folded into `schema.sql` too so a fresh install
  doesn't need to replay history.
  - `db/migrations/` is empty right now — nothing has needed a schema change
    since the initial build. First real use will be whenever phase 3 (or
    anything else) needs one.
- **Shared gotcha both scripts hit and had to work around:** naively
  splitting a `.sql` file into statements on `;` breaks if a `-- comment`
  contains a semicolon as English punctuation (schema.sql's own default-quota
  comment did: "...starting default; admin can override..." — split the
  `INSERT` right in half). Fixed with `split_sql_statements()` in
  `src/lib/helpers.php`, which strips whole `-- ...` comment lines before
  splitting. Also: don't wrap `CREATE TABLE`/`ALTER TABLE` execution in an
  explicit PDO transaction — MySQL implicitly commits DDL regardless, which
  leaves PDO's transaction bookkeeping out of sync with the server and makes
  `rollBack()` throw "There is no active transaction" instead of doing
  anything useful. Both scripts just run statements directly and rely on
  `CREATE TABLE IF NOT EXISTS`/idempotent migrations to make a failed
  partial run safe to fix and re-submit.
- Tested end-to-end locally: ran `install.php` against a throwaway database
  start to finish (requirements check → form → schema created →
  `config.local.php` written), confirmed `upgrade.php` correctly 403s a
  non-admin and reports "no migrations yet" for an admin, then restored the
  real local `config.local.php`-less (default XAMPP) setup afterward.

## Open items / assumptions to revisit

- No domain name has been discussed yet — plan assumes the user will point an
  existing or new Mijndomein-registered domain at the webhosting package.
- What happens to a deleted user's models when they appear in *other* users'
  favorites/collections — current assumption is cascade-delete (small closed
  group, not a public archive); revisit if that stops being true. **Built
  this way:** `models.owner_user_id` and every join table use
  `ON DELETE CASCADE`, so deleting a user (self-service or admin) removes
  their models, favorites, and collections automatically — see
  `db/schema.sql`.
- ~~Confirm PHP version / extensions~~ — resolved, see "Local dev environment"
  below (still needs re-confirming against the actual Mijndomein Plesk PHP
  version once hosting is provisioned, since local XAMPP isn't guaranteed to
  match it exactly).
- ~~Exact default per-user quota~~ — resolved: **2 GiB**, see decision #7 and
  `settings.default_quota_bytes` in `db/schema.sql`.
- ~~Collection public/private default~~ — resolved, see decision #8 below.

## Local dev environment — XAMPP (2026-09-21)

Built and tested phase 1 + phase 2 locally against XAMPP before Mijndomein
hosting is provisioned, to validate the plain-PHP architecture end to end.
Findings, since Mijndomein's actual Plesk PHP install hasn't been checked yet
and this is the closest local stand-in:

- **PHP 8.2.12** (XAMPP's bundled version). Extensions confirmed present:
  `PDO`, `pdo_mysql`, `mbstring`, `fileinfo`, `json`, `session` — everything
  the plan called for. (`pdo_sqlite` is also present but unused.)
- **MySQL** via XAMPP's bundled MariaDB/MySQL, started with
  `mysql_start.bat`. Database `stlsharer` created directly from
  `db/schema.sql` with no changes needed — the schema is portable, plain
  ANSI-ish MySQL with `InnoDB`/`utf8mb4`, no Plesk-specific assumptions.
- **Serving setup:** rather than dropping the app inside `htdocs/` (which
  would serve it under `/stlsharer/`, a subdirectory — not how it'll run on
  Mijndomein, where `httpdocs/` *is* the domain root), added a dedicated
  Apache vhost on **port 8081** in
  `C:\xampp\apache\conf\extra\httpd-vhosts.conf` with
  `DocumentRoot` pointing straight at `public/`. This makes local testing
  match production path structure (`/login`, `/upload`, etc. all resolve
  from root) instead of needing every link rewritten through a base-path
  helper. `C:\xampp\htdocs\stlsharer` is *also* kept as a directory junction
  to `public/` for convenience, but the vhost on :8081 is the one that
  mirrors deployment.
  - The front controller (`public/index.php`) *additionally* strips
    `dirname($_SERVER['SCRIPT_NAME'])` from the request path before routing,
    so it still works correctly if someone hits it via the `/stlsharer/`
    junction/subdirectory path too — this is a no-op at a real domain root.
- **No AllowOverride/mod_rewrite changes needed** — XAMPP's default
  `httpd.conf` already has `AllowOverride All` and `mod_rewrite` loaded for
  `htdocs`; the new vhost's `<Directory>` block sets `AllowOverride All`
  explicitly for the same reason. `public/.htaccess` (front-controller
  rewrite + "serve real files directly") worked without modification.
- **Three.js loading:** pulled from jsDelivr (`three@0.160.0`) as native ES
  modules — `three.module.js` plus the `STLLoader`/`OrbitControls` addons.
  The addon files import the bare specifier `"three"` internally, which
  browsers can't resolve without help — needed an `<script type="importmap">`
  on the model-detail page mapping `three` → the CDN module URL (and
  `three/addons/` → the examples/jsm root) before the module `<script>` that
  uses them. No bundler/build step needed either way.
- **Thumbnail generation confirmed working end-to-end:** the viewer renders
  the model client-side, captures the canvas as a PNG data URL, and POSTs it
  back to `/models/{id}/thumbnail` (a raw-body endpoint, CSRF-checked via an
  `X-CSRF-Token` header rather than a form field since there's no `$_POST`).
  Verified the library grid picks up the generated thumbnail on next load.
- **Verified working via curl (session-cookie-based) + the browser:** first
  registered account auto-promoted to admin+approved; second registration
  left `pending` and blocked from login; admin approve/suspend/reinstate;
  per-user quota override rejecting an over-quota upload with the right
  message; `StlValidator`'s binary/ASCII sanity check rejecting a non-STL
  file; private-collection access returning 403 to a non-owner; favorites
  and collection add/remove round-tripping through real CSRF-protected forms
  in the browser (not just curl).
- File-input elements can't be scripted from a browser-automation tool (by
  design — browsers block programmatically setting `<input type=file>`), so
  upload-flow testing here was done by curling the same multipart POST a
  real browser form would send, using a cookie jar for the session. Worth
  remembering if a future session tries to browser-test the upload form
  directly and it silently can't fill the file field.

## Decision #8 — Collection visibility default (resolved 2026-09-21)

plan.md flagged "decide default visibility for collections" as open before
building. Resolved: **private by default**, with an `is_public` boolean the
owner can flip per collection to share it with other *approved* members
(never with the public internet — there's no anonymous/public route, every
collection view still requires login). Reasoning: matches the closed-group
model (decision #5) — nothing should be more exposed than "visible to people
the admin already approved" without an explicit opt-in per collection.
