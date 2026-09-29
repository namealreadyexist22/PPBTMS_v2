# BASE-SYSTEM

A reusable Laravel RBAC + dynamic menu base template. Everything reusable lives under `App\Core\*`, so this can be dropped into a new project and only `App\Models\User` and your own feature pages stay outside it.

## Stack

- PHP 8.3, Laravel ^13.8
- spatie/laravel-permission ^8.3 — underlying roles/permissions
- spatie/laravel-activitylog — audit trail
- yajra/laravel-datatables 13.0 — all admin list screens
- laravel/socialite ^5.28
- laravel/reverb — optional real-time notifications
- Bootstrap 5 + AdminLTE 4 (npm package, `admin-lte`) — UI

Local dev: XAMPP (Apache/mod_php), not `php artisan serve`. DB: `base_sys`.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Fill in your database credentials in `.env`, then:

```bash
php artisan migrate
php artisan db:seed
npm run build
```

Default login: `admin@example.com` / `password` — **change before any real deployment.**

If you want real-time notifications (optional — see [Notifications](#notifications) below), also run `php artisan reverb:install` and fill in the `REVERB_*` values in `.env` **before** `npm run build` (Vite bakes them in at build time).

## Architecture

Everything reusable lives under `App\Core\*` (mirrors `app/Core/*` on disk):

| What | Namespace |
|---|---|
| Menu, MenuUserOverride, PermissionUserOverride, Setting | `App\Core\Models` |
| MenuService | `App\Core\Services` |
| Menus/Roles/Permissions/Logs DataTables | `App\Core\DataTables` |
| HasCoreDataTable trait | `App\Core\DataTables\Traits` |
| Menu/Role/UserAccess/Log/User/Notification/Settings Controllers | `App\Core\Http\Controllers` |
| CheckPermissionOverride (the `perm:` middleware) | `App\Core\Http\Middleware` |
| SystemNotification | `App\Core\Notifications` |
| `setting()` / `notify_user()` global helpers | `App\Core\Helpers` |

`App\Models\User` stays at its original path — moving it broke Laravel's auth/factory conventions. `LoginController`/`MainController` stay in `App\Http\Controllers\BackEnd`.

### Route structure (`routes/web.php`)

- `auth.*` — login/logout (`portal.guest`/`portal.auth` middleware)
- `app.*` (prefix `app/`) — dashboard, profile
- `core.*` (prefix `core/`) — all RBAC admin screens

## The menu/submenu system

This is the heart of the template. A `menus` table row is the single building block for both navigation *and* permissions — there's no separate "permission screen" to manage most of the time.

### Every menu row

| Column | Purpose |
|---|---|
| `name` | Internal/admin-facing name |
| `nav_name` | Optional friendlier label shown in the sidebar (falls back to `name`) |
| `icon`, `route`, `url` | Sidebar icon and link target |
| `permission_name` | Auto-generated (`menu.<slug>`) on creation — every menu gets its own Spatie permission, always |
| `order` | Sort order among siblings |
| `is_active` | Soft on/off switch |
| `is_nav` | Whether this row appears in the rendered sidebar tree |
| `parent_id` | Self-referencing; **cascade deletes** — deleting a parent deletes its submenus |

**`is_nav` is the key idea.** Every menu — top-level or child — always gets an auto-generated permission the moment it's created, via the `Menu` model's `booted()` hook. `is_nav` only controls whether `MenuService` includes that row when building the sidebar tree. Set it to `false` for something that should exist purely as a permission gate with no clickable nav link — a "Destroy" action, for example.

### Structure convention

Keep it to two real levels below any grouping wrapper: **root → menu → submenu**. A submenu is meant to be a leaf — it never gets its own "Submenus" button. `Menu::canHaveSubmenus()` enforces this: a menu can manage submenus if it's top-level, or if its own parent is top-level; anything one level deeper can't.

A wrapping group like "Settings" doesn't count against this — it's just a visual dropdown container. The real functional units (Manage Users, Menu Management, etc.) are its children, and *they're* the ones that get submenus.

### Base Permission Name

A menu can optionally link a standalone "base gate" permission — something like `manage users` — via a plain-text **Base Permission Name** field on its own Edit form. This finds-or-creates a `Permission` by that exact name and points its `menu_id` at this menu.

This field **never renames** an existing permission — your route middleware hardcodes these exact strings, so renaming one in place would silently break every route protected by it. Changing the field just relinks which permission is "current" for this menu; the old one, if any, simply becomes unlinked.

This replaced an earlier standalone Permissions CRUD screen entirely — that screen no longer exists. The Menu form is now the one place to manage a menu, its base gate, and its submenus together.

### Quick-add common submenus

On the Menu form (when creating) or on a menu's own "Submenus" page (to add more later), a checklist — Create / Store / Edit / Update / Show / Destroy — with a per-item "Show in sidebar" toggle. Every checked item becomes a real child `Menu` row (not a linked permission) — it always gets its own auto-generated permission, and `is_nav` just decides whether it also shows as a sidebar link. Nav-visible ones get a friendly auto `nav_name` (Add / Save / Edit / Update / View / Delete).

### Menu Management screen

The main list shows only top-level menus. Each row has an inline **Submenus** column previewing the *entire* subtree (recursively flattened, not just direct children) as a bullet list, plus a **Permission** column showing the exact `permission_name` string to copy into your route middleware.

Clicking **Submenus** on a row opens a dedicated page for that menu: its full subtree (indented by depth), an **Add Submenu** button, and a **Route** + **Permission** column per row so you can see exactly what to wire up.

### Legacy linked permissions

A few permissions (`user.destroy`, `menus.destroy`, etc.) were originally seeded as standalone linked `Permission` records — from before this unification existed — rather than proper submenu `Menu` rows. They still work correctly for route protection; they just show in a separate **"Other Linked Permissions"** section on a menu's Submenus page instead of the main table, since they're a different underlying record type. `ActionPermissionSeeder` has since been rewritten to create genuine submenu rows for any *new* project cloned from this template — this legacy category should stay empty going forward.

### Connecting a submenu to a real page

1. Build the actual route/controller/view.
2. Edit the submenu (or menu) and fill in its **Route** field with that route's name.
3. Protect the route with `->middleware('perm:<permission_name>')` — copy the exact name from the Permission column.
4. It's now automatically assignable on both Roles & Permissions and User Access — no extra registration step. (Both those pages only show menus with a route set — a quick-add item with a blank route won't appear there until you connect it.)

## Permission model

- **Per-item, override-aware.** ALL protected routes use a custom `perm:` middleware (`CheckPermissionOverride`), never Spatie's built-in `permission:` — it checks a per-user override first, falling back to the user's role.
- **Two layers per resource:** a base gate (linked via Base Permission Name) covers the whole viewable block; destroy gets its own separate permission.
- **Per-user overrides** on top of role defaults, for both menus (`menu_user_overrides`) and action permissions (`permission_user_overrides`).
- **Super Admin** bypasses everything via `Gate::before` in `AuthServiceProvider`.
- Core system permissions (`manage menus`, `manage roles`, `manage users`, `manage access`, `manage settings`) are protected from deletion/renaming in code, since routes hardcode these strings.

### Roles & Permissions / User Access — card-grid UI

Both pages use a card grid: one card per menu, grouped under its parent's name as a section heading. Any linked permission whose name starts with `"manage "` folds automatically into the menu's own "View / Access" toggle (checking one always grants both) — this is a convention (`str_starts_with`), not a hardcoded list.

- **Roles page:** simple switches, submits to `Role::syncPermissions()`.
- **User Access page:** each item shows the role's own default (a plain "Allowed"/"Denied" badge) plus an **Override** switch. Flip Override on to reveal an Allow/Deny switch for items the role already grants; for items the role doesn't grant, Override alone is enough (the only meaningful action there is "allow anyway"). Flip Override back off and the override row is deleted — clean inheritance restored.

## Notifications

Laravel's built-in database notifications (not a third-party package): a `SystemNotification` class, a `notify_user($user, $title, $message, $url, $icon)` global helper, a bell badge + offcanvas panel in the navbar, and a full history page.

This is infrastructure only — nothing fires automatically. Call `notify_user(...)` from wherever a future feature's business logic warrants one:

```php
notify_user($invoice->assignedUser, 'Invoice Approved', "Invoice #{$invoice->id} has been approved.", route('core.invoices.show', $invoice));
```

### Real-time (optional)

Add `'broadcast'` to `SystemNotification::via()` and Laravel Reverb delivers updates live instead of waiting for the next page load. Two build-order gotchas to know about:

1. **Vite bakes `VITE_REVERB_*` values into the JS bundle at build time.** Set real values in `.env` *before* running `npm run build` — a later `.env` edit doesn't retroactively fix an already-built bundle.
2. **A missing Reverb key throws on page load and can silently halt every script after it** (this took down an unrelated sidebar dropdown once). `resources/js/echo.js` guards against this — it only initializes `window.Echo` if `VITE_REVERB_APP_KEY` is actually set, so a fresh clone with no Reverb configured still works fine; notifications just won't be live.

`php artisan reverb:start` needs to keep running in its own terminal for live delivery to work — it's a separate persistent process, same idea as `npm run dev`.

## App Settings

A generic key-value `settings` table (`Setting` model, cached `get()`/`set()`) and a global `setting()` helper usable anywhere, including Blade. Currently configurable: `app_name`, `app_version`, `app_logo` — gated by `manage settings`.

```blade
{{ setting('app_name', 'PORTAL') }}
```

## HasCoreDataTable trait

`App\Core\DataTables\Traits\HasCoreDataTable` — shared `html()`/`filename()` implementation for admin DataTable classes, so Menus/Roles/Logs don't each duplicate the same boilerplate. Uses abstract **methods** for `tableId()`/`getColumns()`/`builder()`, not typed properties (a trait's typed property and a class's typed-property-with-default aren't composable in PHP — this hit as a real fatal error before settling on methods).

Configurable per class via override hooks: `pageLength()`, `lengthMenu()`, `responsive()`, `selectStyle()`, `withButtons()`/`buttons()`, `dom()`, `ajaxUrl()`/`ajaxData()`, `language()`, `extraParameters()`.

## AdminLTE integration notes

The sidebar uses AdminLTE 4's real markup (`<p>` tags for labels, `.brand-link`/`.brand-text` for the logo area) and its own stock CSS/JS — installed as the `admin-lte` npm package, imported in `app.css` and `app.js` (`import 'admin-lte';`). Don't write custom CSS that fights its built-in collapsed/hover/mini-sidebar behavior; it already handles centering, label reveal-on-hover, and active-state styling correctly given standard markup.

Body classes for the standard "Sidebar Mini + Collapsed" layout:

```blade
<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-light">
```

`sidebar-mini` must be permanent — AdminLTE's `PushMenu` JS (wired to `data-lte-toggle="sidebar"`) toggles `sidebar-collapse` on and off when the hamburger is clicked.

Two small custom overrides currently sit in `app.css` (after the `@import` lines) to force label/brand-text visibility on hover, working around an unresolved conflict with AdminLTE's own equivalent rules:

```css
.sidebar-mini.sidebar-collapse .app-sidebar:hover .sidebar-menu .nav-link p {
    width: auto !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.sidebar-mini.sidebar-collapse .app-sidebar:hover .sidebar-brand .brand-text {
    max-width: none !important;
    width: auto !important;
    visibility: visible !important;
    opacity: 1 !important;
    margin-left: 0.5rem !important;
}
```

## Activity logging

`spatie/laravel-activitylog`, explicit `activity()->causedBy()->performedOn()->log(...)` calls at each mutating action (not the automatic per-model trait). Activity Logs screen is read-only; `logs.clear` (a non-nav submenu of Activity Logs) gates a destructive clear-all action.

## Seeder chain

`DatabaseSeeder` runs, in order:

1. `RolePermissionSeeder` — roles + base permissions (no menu links yet, menus don't exist)
2. `AdminUserSeeder` — default Super Admin login
3. `MenuSeeder` — the menu tree
4. `ActionPermissionSeeder` — links base permissions to their now-existing menus, and creates the destroy/clear submenus

Single `php artisan db:seed` sets up everything, and `ActionPermissionSeeder` is safe to rerun (relinks by name, `firstOrCreate`s submenus).

## Workflow: adding a new page

1. Build the route/controller/view; protect it with `->middleware('perm:<permission-name>')`.
2. Menu Management → Add — name it, fill in its route, optionally use Quick-add for its common actions (Create/Destroy/etc.), optionally set a Base Permission Name.
3. Roles & Permissions → grant it to the relevant roles.
4. (Optional) User Access → allow/deny for specific individuals.

## Troubleshooting

- **Stale cached config causing env-derived errors to persist after `.env` is fixed:** `php artisan config:clear` (not `config:cache` during dev).
- **After moving/renaming classes:** `composer dump-autoload`.
- **A new controller/class "doesn't exist" even though the file looks right:** double-check the exact folder path matches the namespace — a misplaced file produces the same `BindingResolutionException` as a missing autoload entry.
- **A new global helper function is "undefined":** confirm it's listed in `composer.json`'s `"files"` autoload array, then `composer dump-autoload` — adding the file alone isn't enough.
- **New npm packages missing after cloning/pulling on a different machine:** `node_modules/` isn't committed; run `npm install`.