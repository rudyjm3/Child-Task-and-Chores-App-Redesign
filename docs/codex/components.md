# Component Index

No component framework (no React/Vue/Blade components) — "components" here are the two real reuse units in this codebase: **PHP layout partials** (`includes/*.php`, server-rendered, included via `require_once`/`include`) and **standalone JS modules** (`js/*.js`, vanilla, no build step, loaded via `<script src>`). CSS component *classes* (not files) are catalogued separately at the bottom since `css/components.css` defines many reusable visual patterns referenced by class name across pages.

## PHP layout partials (`includes/`)
| Name | Path | Key inputs (PHP vars expected in scope) | Renders |
|---|---|---|---|
| Page setup | `includes/page_setup.php` | none (reads `$_SESSION`) | Session guard + `$currentPage`, `$family_root_id`, `$welcome_role_label`; requires `notifications_bootstrap.php` |
| HTML head | `includes/html_head.php` | `$pageTitle`, optional `$extraHeadCss` (array), `$extraHeadHtml` (string) | `<meta>`, `<title>`, `main.css`, `shared.css`, optional extra CSS, favicon, Font Awesome CDN |
| Page header | `includes/page_header.php` | `$pageHeading`, optional `$dashboardPage`; uses `$currentPage`, `$isParentNotificationUser`, `$isChildNotificationUser`, `$parentNotificationCount`, `$notificationCount`, `$welcome_role_label` | Header: greeting, notification bell (role-aware), family-settings gear (parent only), logout, primary nav (`nav-links`) with active-state per `$currentPage` |
| Page footer | `includes/page_footer.php` | `$dashboardPage`, `$dashboardActive`, `$routinesActive`, `$tasksActive`, `$goalsActive`, `$rewardsActive` (all set by `page_header.php`) | Mobile bottom nav + version footer (`APP_VERSION`) |
| Sidebar | `includes/sidebar.php` | none (reads `$_SESSION['role']`, `$_SESSION['name']`) | Desktop-only (≥1024px) sidebar nav; renders `.parent-sidebar` (6 items incl. Children) for parent roles or `.child-sidebar` (5 items, no Children) for `role === 'child'`. Included by `dashboard_parent.php`, `dashboard_child.php`, `task.php`, `routine.php`, `goal.php`, `children.php`, `rewards.php` (parent branch only) — each page wraps its content in `.parent-page`/`.parent-main` or `.child-page`/`.child-main` around the include |
| Notifications bootstrap | `includes/notifications_bootstrap.php` | `$main_parent_id` (optional) | Sets `$isParentNotificationUser`/`$isChildNotificationUser` + loads `$parentNotices`/`$childNotices` and unread counts |
| Parent notifications UI | `includes/notifications_parent.php` | `$parentNew`, `$parentRead`, `$parentDeleted`, etc. (set by bootstrap) | Notification bell dropdown/modal markup for parent-role pages |
| Child notifications UI | `includes/notifications_child.php` | `$notificationsNew`, `$notificationsRead`, `$notificationsDeleted`, etc. | Notification bell dropdown/modal markup for child-role pages |

## JS modules (`js/`)
Vanilla JS, no bundler, no npm — each file is a self-contained IIFE or `DOMContentLoaded` handler loaded directly via `<script src>`. No shared module system (no ES imports); cross-file sharing happens through globals (`window.TimeOfDay`, `window.PresetPicker`).
| Name | Path | Exposes | Purpose | Used by |
|---|---|---|---|---|
| Number stepper | `js/number-stepper.js` | (auto-init, no export) | Auto-wraps every `<input type="number">` (unless `data-stepper="false"`) with +/- buttons on `DOMContentLoaded` | Nearly every page (task.php, routine.php, goal.php, rewards.php, profile.php, register.php, dashboards) |
| Time-of-day helpers | `js/time-of-day.js` | `window.TimeOfDay = {normalize, fromTime, label, icon, order}` | Client-side mirror of PHP `timeOfDay*()` helpers in `includes/functions.php` (morning <12:00, afternoon 12:00–16:59, evening ≥17:00, anytime = unset) | dashboard_parent.php, dashboard_child.php, task.php, routine.php |
| Preset picker | `js/preset-picker.js` | `window.PresetPicker.create({onSelect, getDisabledIds, disabledNote})` → `{open()}` | Modal for searching/filtering/picking a Preset Task; fetches `preset_tasks_api.php` | task.php (individual task form), routine.php (routine builder) |
| Child detail interactivity | `js/child-detail.js` | (auto-init, no export) | Points/stars history modals with filters, adjust-points/stars modals, week-schedule modal + `fetch()` to `?week_schedule=1` | children.php only |

### Third-party
| Library | Loaded from | Used by | Purpose |
|---|---|---|---|
| Font Awesome 6.7.2 | cdnjs CDN | every authenticated page | Icon font (`fa-solid fa-*` classes throughout) |
| SortableJS 1.15.0 | jsdelivr CDN | routine.php only | Drag-and-drop reordering of routine steps in the builder |

## CSS files
| File | Scope |
|---|---|
| `css/reset.css` | Browser default reset |
| `css/main.css` | Global base styles, loaded everywhere |
| `css/shared.css` | Cross-role shared component styles (used with the `page_setup.php` pattern) |
| `css/parent.css` | Parent-role-specific styling |
| `css/child.css` | Child-role-specific styling (bright/playful theme per design system) |
| `css/components.css` | Reusable visual component classes (cards, badges, chips, progress bars — see below) |
| `css/children-detail.css` | `children.php`-specific styles |

## Notable reusable CSS component classes (documented in `docs/ux-ui-design-system.md` §6)
Not JS components — just conventionally-reused class/markup patterns applied across pages. Referenced here so a search for "the task card" or "the progress bar" resolves without re-deriving from screenshots.
| Component | Where documented | Used on |
|---|---|---|
| Task/Goal card (mobile) | design doc §6.1 | task.php, goal.php |
| Hero card | design doc §6.2 | dashboard_child.php |
| Points/XP badge | design doc §6.3 | dashboard_child.php, children.php |
| Filter chip row | design doc §6.4 | task.php, routine.php |
| Bottom navigation | design doc §6.5 | `includes/page_footer.php` |
| Week strip | design doc §6.6 | dashboard_child.php |
| FAB (floating action button) | design doc §6.7 | task.php, routine.php, goal.php |
| Gradient stats strip | design doc §6.8 | dashboard_parent.php |
| Progress bar | design doc §6.9 | routine.php (runner), goal.php |
| Goal card (child view) | design doc §6.10 | goal.php |
| Approval card (parent) | design doc §6.11 | dashboard_parent.php |
| Stat chip | design doc §6.12 | children.php, dashboard_parent.php |

Color tokens are CSS custom properties on `:root` (`--color-primary: #6D28D9`, etc.) defined in the design doc — always use the variables, never hardcode hex in new component CSS (existing convention per `docs/ux-ui-design-system.md` §2).

---
Last generated: 2026-07-23. Triggered by: initial /docs/codex reference generation request (read of every includes/*.php partial, every js/*.js file's header comment + exports, and docs/ux-ui-design-system.md §6/§13); updated same day after wiring `includes/sidebar.php` into the pages that were missing it and adding its child-role variant. Regenerate when a partial's expected inputs change, a JS module is added/removed, or new CSS component classes are introduced.
