# Plan: STL Sharer

A website to upload, browse, and view STL files, with accounts, favorites, and
collections — built to run on Mijndomein.nl shared webhosting (Plesk, PHP +
MySQL). See [CLAUDE.md](CLAUDE.md) for the hosting constraints and decisions
behind this plan.

## Goals, in order

1. **Phase 1 — Core sharing site.** Upload STL files, browse/search a library,
   view models in-browser without downloading them.
2. **Phase 2 — Accounts & organization.** Register/login (closed by default —
   an admin must approve each signup), favorite models, build personal
   collections, with a per-user upload quota and baseline GDPR protections.
3. **Phase 3 — Simple edits (future, not built now).** Let a user enlarge a
   hole in an uploaded model and export the result. Architecture in phases 1–2
   is chosen so this slots in without a rewrite.

---

## Architecture overview

```
Browser (three.js STL viewer, upload UI, account UI)
        |
        v
PHP application (Plesk-hosted, MySQL backend)
   - Auth (sessions/cookies)
   - Upload handling (validate, store, extract metadata)
   - REST-ish endpoints (JSON) for models/favorites/collections
        |
        v
MySQL (users, models, favorites, collections)
Filesystem (uploaded .stl files, generated thumbnails), inside webroot's
private storage dir, served through a PHP download/stream route (not direct
static links) so we control access and can add private models later.
```

Everything CPU-heavy (parsing geometry, rendering previews, and later, mesh
editing) happens **client-side in the browser** via three.js/WASM. The PHP
backend stays simple: auth, CRUD, and file storage/streaming. This matches
what shared hosting can actually run (no background workers, no guaranteed
Node.js) and keeps the door open for the phase 3 editor, which will also run
client-side.

### Stack

- **Backend:** PHP (any recent 8.x) + MySQL, **no framework — plain PHP**
  (confirmed choice, after weighing Laravel/CodeIgniter 4/Symfony/Slim/
  WordPress — see CLAUDE.md decision #1). This avoids Laravel's hosting risks
  entirely (no Composer requirement, no custom document root, no SSH/
  `artisan` dependency). In exchange, routing, sessions, CSRF, password
  hashing, and the DB access layer are hand-written — see "Plain PHP on
  Mijndomein" in CLAUDE.md for what that means in practice and the specific
  discipline it requires (prepared statements only, manual output escaping).
- **Frontend:** Plain PHP templates/partials (no templating engine), with a
  small `e()`-style helper wrapping `htmlspecialchars()` used on every
  user-supplied value we output. No build-heavy JS framework required for
  phase 1–2; a full SPA framework (React/Vue) is optional polish, not a
  requirement.
- **3D viewing:** [three.js](https://threejs.org/) + `STLLoader`, one
  `<canvas>` per viewer instance, lazy-loaded so a library page with many
  models doesn't try to render them all at once.
- **File storage:** local disk under the hosting account, outside the public
  webroot where possible (or protected via PHP-gated download route).
  Revisit only if usage outgrows ~15 GB (see CLAUDE.md).

---

## Data model (phase 1–2)

- **users**: id, email, password_hash, display_name, created_at,
  **role** (`admin` | `member`), **status** (`pending` | `approved` |
  `rejected` | `suspended`), **approved_by** (nullable, user_id),
  **approved_at** (nullable), **storage_quota_bytes** (nullable override —
  null means "use the global default"), **consented_at** (GDPR: timestamp of
  accepting terms/privacy policy at signup)
- **models**: id, owner_user_id, title, description, original_filename,
  stored_path, file_size_bytes, thumbnail_path (nullable), created_at
- **favorites**: user_id, model_id, created_at (composite unique key)
- **collections**: id, owner_user_id, name, description, created_at
- **collection_models**: collection_id, model_id, sort_order

Forward-looking, not built yet but shapes the schema: a future
**model_versions** table (model_id, parent_version_id, stored_path,
created_by_user_id, edit_description) so phase 3 edits produce new versions
rather than mutating the original file. Phase 1–2 can ignore this as long as
we don't design uploads as "one file per model, editable in place."

---

## Phase 1 — Core sharing site

1. **Project setup**
   - Provision Mijndomein Webhosting Plus, confirm PDO/`pdo_mysql`,
     `mbstring`, and `fileinfo` extensions are enabled (see CLAUDE.md's "Plain
     PHP on Mijndomein" section — low risk, but not yet checked).
   - Set up MySQL database via Plesk; write the initial schema as a plain
     `.sql` file, applied via phpMyAdmin/Plesk's DB tool.
   - Small project skeleton: a single front-controller (`index.php`) with a
     minimal route table, a `lib/` (or `app/`) folder for a PDO wrapper,
     session/auth helpers, and the `e()` escaping helper — no Composer
     dependency required to run on the server.
2. **Upload & storage**
   - Upload form with client-side validation (`.stl` extension + magic-byte
     sniff, max file size sane for the 20 GB quota — e.g. cap at 50–100 MB
     per file initially).
   - Store file with a generated unique name; keep original filename as
     metadata only (never trust it for paths).
   - Basic STL sanity check server-side (valid ASCII/binary STL header) to
     reject garbage uploads early.
3. **Library / browse**
   - Paginated model listing, basic search by title.
   - Model detail page.
4. **Viewer**
   - three.js STL viewer component: orbit controls, reset view, wireframe
     toggle, background color toggle (light/dark model contrast).
   - Generate a static thumbnail (client-side render → canvas → upload as
     PNG) at upload time, so the library grid doesn't need to boot a WebGL
     viewer per thumbnail.
5. **Ops basics**
   - HTTPS (free SSL via Plesk), basic error pages, upload size limits
     enforced both client- and server-side (and in PHP's own
     `upload_max_filesize`/`post_max_size`, adjustable via Plesk).

## Phase 2 — Accounts & organization

1. **Auth & admin approval gate**
   - Registration + login (email/password), password hashing via PHP's
     built-in `password_hash()`/`password_verify()` (no framework needed for
     this — it's already the right primitive).
   - **The first user ever created is auto-assigned `role = admin` and
     `status = approved`** (seed/bootstrap logic — e.g. `if (User::count() ===
     0) { role = admin; status = approved; }` at registration time).
   - **Every subsequent signup is created with `status = pending`** and
     cannot log in / access the site beyond a "your account is awaiting
     approval" holding page until an admin approves it.
   - **Admin dashboard:** list pending users, approve/reject each one; also
     able to suspend or re-approve an already-approved user at any time
     (revoking access without deleting their account/data).
   - This makes the site closed-by-default rather than open registration —
     matches hobby/small-group scale and cuts abuse risk substantially
     without needing invite-code infrastructure.
   - Basic email verification if Mijndomein's included mailboxes/SMTP can be
     used for sending mail (Plesk plans include mail — should work for
     transactional mail via PHP's `mail()` or SMTP). Approval notification
     email to the user once an admin approves them.
   - Session-based auth (cookies), CSRF protection on state-changing forms.
   - Middleware/guard on every route (except login/register/pending-notice)
     that checks `status === approved`, so a pending or suspended user is
     blocked everywhere, not just at login.
2. **Favorites**
   - Favorite/unfavorite toggle on model detail + library grid.
   - "My favorites" page.
3. **Collections**
   - Create/rename/delete a collection, add/remove models, reorder.
   - Public vs. private collections (simple boolean flag) — decide default
     visibility with the user before building.
4. **Ownership & permissions**
   - Only the uploader can edit/delete their model; admin can moderate/delete
     any model or account (needed for the copyright/abuse handling noted
     below). No need for a full RBAC system beyond `admin`/`member` at this
     scale.
5. **Per-user upload quota**
   - Global default `storage_quota_bytes` (e.g. 1–2 GB per user to start,
     easily fits several users in the 20 GB Plus plan with headroom) stored
     as an app setting; admin can override per-user via `users
     .storage_quota_bytes`.
   - Enforce at upload time: sum the user's existing `models.file_size_bytes`
     and reject the upload (with a clear error) if it would exceed their
     quota — checked server-side, not just via a UI indicator.
   - Show each user their used/total quota on their profile/upload page.
   - Admin dashboard shows total disk usage across all users, so the admin
     can see how close the account is to the 20 GB plan limit overall.
6. **GDPR / privacy basics**
   - Minimal data collection: email, password hash, display name — nothing
     else required for phase 1–2 (no tracking cookies, no third-party
     analytics by default).
   - A real privacy policy page and a terms/upload-rules page linked from the
     registration form, with `consented_at` recorded at signup.
   - **Right to erasure:** an account deletion action (self-service, and
     admin-triggered) that removes the user's row (or anonymizes it if their
     models must be retained for others' collections — decide per model:
     likely simplest is deleting the user's own models too, since this is a
     personal/small-group site, not a public archive) and their uploaded
     files from disk.
   - **Right to access:** not building a full data-export tool now, but since
     the data model is small (user + their models list), a simple admin- or
     self-service "here's what we have on you" page is cheap to add later —
     noted as a phase 2 nice-to-have, not a blocker.
   - Session cookies only (no non-essential cookies) so a cookie-consent
     banner isn't legally required — keep it that way; revisit if analytics
     are ever added.

## Phase 3 — Simple edits (future; design-only for now)

Not built in this pass. Noted here so phase 1–2 decisions don't block it:

- **Feature:** detect a circular/cylindrical hole in an uploaded STL and let
  the user enlarge its diameter, producing a new derived STL.
- **Where it runs:** entirely client-side (WASM geometry library — candidates
  to evaluate later: `manifold-3d` for robust boolean ops, or `three-bvh-csg`
  for a lighter-weight approach) — shared hosting cannot run this server-side.
- **Hardest sub-problem:** hole *detection* (finding cylindrical bores in an
  arbitrary mesh) — needs its own research spike when we start this phase.
- **Data model seam:** store edits as new rows in a `model_versions` table
  (see Data Model above) rather than overwriting the original file.
- **Not deciding now:** exact library choice, UI for "pick a hole and a new
  diameter," and how/whether edited files get re-validated as printable
  (manifold, watertight) before being shared.

---

## Implementation status (2026-09-21)

Phases 1 and 2 are built and locally tested end-to-end against XAMPP (see
CLAUDE.md's "Local dev environment" section for the setup and what was
verified). Not yet done: actual Mijndomein Plesk provisioning/deployment,
transactional email (approval notifications), and phase 3 (not in scope
yet). Source layout: `public/` (webroot/front controller + assets),
`src/lib` (DB/auth/helpers), `src/controllers`, `src/views`, `db/schema.sql`.

## Open questions to confirm before/at Phase 1 kickoff

(Also tracked in CLAUDE.md's "Plain PHP on Mijndomein" and "Open items"
sections.)

- Confirm PDO `pdo_mysql`/`mbstring`/`fileinfo` extensions are enabled on the
  **actual Mijndomein** Webhosting Plus Plesk instance — confirmed present
  locally on XAMPP's PHP 8.2.12 (see CLAUDE.md), but that's a stand-in, not
  the real hosting environment.
- Confirm max practical upload size Mijndomein's Plesk config allows by
  default (may need to raise `upload_max_filesize`/`post_max_size` via Plesk
  UI).
- ~~Decide default visibility for collections/models~~ — resolved, see
  CLAUDE.md decision #8: private by default, with a per-collection
  `is_public` toggle visible only to other approved members (never public).
- ~~Pick the actual default per-user storage quota number~~ — resolved:
  2 GiB, see CLAUDE.md decision #7 and `settings.default_quota_bytes`.
- ~~Decide what happens to a deleted user's models in others'
  favorites/collections~~ — resolved as cascade-delete, and now actually
  implemented via `ON DELETE CASCADE` throughout `db/schema.sql`.
