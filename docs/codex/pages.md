# Page Tree

All pages are server-rendered PHP (no client-side routing, no SPA framework). "Client" below means JS that runs after load to add interactivity to the server-rendered HTML — there is no client-side data fetching except the two fetch() call sites noted. Every authenticated page is reachable from `includes/page_header.php` (mobile top nav), `includes/page_footer.php` (mobile bottom nav), and `includes/sidebar.php` (desktop sidebar, parent-only, hidden for `role === 'child'`).

## Route tree
```
/index.php              public landing page
/login.php               auth
/register.php             auth (parent signup only — children are created by a parent, no self-registration)
/logout.php               auth
/dashboard_parent.php     parent home            (role: main_parent, secondary_parent, family_member, caregiver)
/dashboard_child.php      child home              (role: child)
/children.php             parent: per-child detail/management
/task.php                 both roles, role-filtered view
/routine.php              both roles, role-filtered view
/goal.php                 both roles, role-filtered view
/rewards.php              both roles, role-filtered view (two structurally distinct branches)
/profile.php              both roles, self or (parent editing family member)
/preset_tasks_api.php     JSON-only, not a page (used by JS fetch, no HTML)
```
There is no `/children` vs `/routines` etc. nesting — every page is flat at repo root, PHP-extension URLs, no pretty-routing/.htaccess rewriting observed.

## Per-page detail
| Page | Server renders | Head include pattern | CSS | Client JS | Notes |
|---|---|---|---|---|---|
| `index.php` | Marketing/landing sections (hero, features, pricing, FAQ) | inline `<head>` (no shared partial) | inline-only, no `main.css` link found | none observed | Redirects to `dashboard_{role}.php` if already logged in |
| `login.php` | Login form | inline `<head>` | `css/main.css?v=3.28.0` | none | version-string drift vs `APP_VERSION` (3.27.0) — see architecture.md conventions |
| `register.php` | Parent signup form | inline `<head>` | `css/main.css?v=3.28.0` | `js/number-stepper.js` | |
| `logout.php` | JS alert + redirect only, minimal HTML | n/a | n/a | inline JS `alert()` | Not a real page, just a transitional script |
| `dashboard_parent.php` | Children overview, tasks/goals/rewards summaries, notifications, family management modals | inline `<head>` (does **not** use `includes/html_head.php`) | `css/main.css`, `css/parent.css` | `js/time-of-day.js`, `js/number-stepper.js` | Uses `includes/page_header.php`/`page_footer.php`/`sidebar.php` for nav but builds its own `<head>` |
| `dashboard_child.php` | Hero stats, week strip, goals, quick-nav cards, notifications | inline `<head>` | `css/main.css`, `css/child.css` | `js/time-of-day.js`, `js/number-stepper.js` | |
| `children.php` | Per-child cards: level/streak/points/stars, history, week schedule modal | `includes/page_setup.php` + `includes/html_head.php` (the one page that follows the "shared partial" convention along with rewards.php) | `css/main.css`, `css/shared.css`, `css/children-detail.css` (via `$extraHeadCss`) | `js/child-detail.js` | Week-schedule fetch (`?week_schedule=1`) is currently **broken** — calls undefined `serveWeekScheduleJson()`/`buildChildWeekSchedule()`, see routes.md |
| `task.php` | Task dashboard: pending/completed/approved/expired lists, week calendar, create/edit forms | inline `<head>` | `css/main.css`, `css/child.css`, `css/parent.css` | `js/time-of-day.js`, `js/number-stepper.js`, `js/preset-picker.js` | Largest controller by POST-action count |
| `routine.php` | Routine builder/list, preset-task library management, overtime insights, routine runner UI | inline `<head>` | `css/main.css`, `css/child.css`, `css/parent.css` | `js/time-of-day.js`, `js/number-stepper.js`, `js/preset-picker.js`, `SortableJS` (CDN, drag-reorder routine steps) | Largest file in repo (6014 lines); only page using a third-party JS lib |
| `goal.php` | Goal list/creation (manual, task-quota, routine-streak, routine-count types) | inline `<head>` | `css/main.css`, `css/child.css`, `css/parent.css` | `js/number-stepper.js` | |
| `rewards.php` | Two branches: child reward shop, or parent reward-template/management dashboard | `includes/page_setup.php` + `includes/html_head.php` | `css/parent.css` (via `$extraHeadCss`) | `js/number-stepper.js` | Child-role branch fully renders + `exit`s before the parent branch's code runs |
| `profile.php` | Self or family-member profile edit form (password, avatar, name, role badge) | inline `<head>` | `css/main.css`, `css/child.css`, `css/parent.css` | `js/number-stepper.js` | Avatar upload writes to `uploads/avatars/`, GD-resized 100×100 |
| `preset_tasks_api.php` | N/A — JSON body only, `Content-Type: application/json` | n/a | n/a | fetched by `js/preset-picker.js` | Not part of the page tree; the only true API endpoint |

## Layout partials (not standalone pages)
| File | Used by | Renders |
|---|---|---|
| `includes/page_setup.php` | `children.php`, `rewards.php` | Session guard, `$currentPage`, `$family_root_id`, `$welcome_role_label`, notification bootstrap |
| `includes/html_head.php` | `children.php`, `rewards.php` | Shared `<head>` (meta, title, main.css/shared.css, Font Awesome CDN, favicon) |
| `includes/page_header.php` | most authenticated pages | Top header: greeting, notification bell, family-settings gear, logout, primary nav links |
| `includes/page_footer.php` | most authenticated pages | Mobile bottom nav + version footer |
| `includes/sidebar.php` | most authenticated pages | Desktop sidebar nav (parent roles only; no-ops for `role === 'child'`) |
| `includes/notifications_bootstrap.php` | via `page_setup.php`, and inlined manually by pages that don't use `page_setup.php` | Computes `$isParentNotificationUser`/`$isChildNotificationUser` and loads notice lists |
| `includes/notifications_child.php` | child-facing pages | Notification bell dropdown/modal markup (child) |
| `includes/notifications_parent.php` | parent-facing pages | Notification bell dropdown/modal markup (parent) |

## Convention gap worth knowing
Only 2 of 11 authenticated pages (`children.php`, `rewards.php`) use the shared `page_setup.php`/`html_head.php` partials; the other 8 (`dashboard_parent.php`, `dashboard_child.php`, `task.php`, `routine.php`, `goal.php`, `profile.php`, plus the unauthenticated `login.php`/`register.php`) inline their own session guard and `<head>` block instead, with independently-drifted CSS `?v=` query strings (`3.27.0` vs `3.28.0` vs `APP_VERSION` constant `3.27.0`). Treat `page_setup.php`/`html_head.php` as the intended pattern for new pages, not the actual state of all existing ones.

---
Last generated: 2026-07-23. Triggered by: initial /docs/codex reference generation request (grep of every root .php file's `<head>`, CSS/JS includes, and layout-partial usage). Regenerate when a page's include pattern, CSS/JS dependencies, or the page tree itself changes.
