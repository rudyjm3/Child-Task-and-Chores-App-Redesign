# FamilyQuest — UX/UI Design System & Screen Specifications

> **Figma source:** https://www.figma.com/design/taPoxMa7BoFwt8BD3DIsX0  
> **Stack:** PHP · MySQL · HTML · CSS · Vanilla JS  
> **Last updated:** 2026-06-15

This document is the single source of truth for visual design. Every color, spacing value, component pattern, and screen layout is defined here. When building or modifying any UI, refer to this file first.

---

## 1. Design Principles

| Principle | Detail |
|-----------|--------|
| **Dual-audience** | Parent UI = clean, information-dense, professional. Child UI = warm, playful, large touch targets. |
| **Role-aware** | The same URL switches its entire visual personality based on whether the logged-in user is a parent or child. |
| **Gamification-first** | Points, levels, progress bars, and reward states are visible on every child-facing screen. |
| **Accessibility** | Minimum 44px touch targets on mobile. Color is never the only status indicator (always paired with a label). High-contrast badges for overdue/danger states. |
| **Autism-friendly** | Toggleable sounds and animations. No auto-playing media. Predictable layouts — the same card structure is reused across all task and goal screens. |

---

## 2. Color System

All colors are defined as CSS custom properties on `:root`. Use these variables everywhere — never hardcode hex values in component CSS.

```css
:root {
  /* Brand */
  --color-primary:       #6D28D9;   /* Main purple — buttons, active nav, hero gradients */
  --color-primary-mid:   #A78BFA;   /* Gradient endpoint, level chips */
  --color-primary-light: #EDE9FE;   /* Chip backgrounds, inactive highlights */

  /* Accent */
  --color-accent:        #0D9488;   /* Teal — routines, "done" states, secondary CTAs */
  --color-accent-light:  #CCFBF1;   /* Teal chip backgrounds */

  /* Semantic */
  --color-success:       #10B981;
  --color-success-light: #D1FAE5;
  --color-warning:       #D97706;
  --color-warning-light: #FEF3C7;
  --color-danger:        #DC2626;
  --color-danger-light:  #FEE2E2;

  /* Gold / Points */
  --color-gold:          #F59E0B;
  --color-gold-light:    #FEF3CD;

  /* Category accents (task/goal card left borders) */
  --color-cat-routine:   #0D9488;   /* teal */
  --color-cat-chore:     #F97316;   /* orange */
  --color-cat-learning:  #6D28D9;   /* primary purple */
  --color-cat-pet:       #D97706;   /* warning amber */
  --color-cat-custom:    #A78BFA;   /* primary-mid */

  /* Neutrals */
  --color-bg:            #F8F7FF;   /* Page background — near-white with a purple tint */
  --color-slate:         #F0EFFC;   /* Input backgrounds, inactive chips */
  --color-text-dark:     #1E1B4B;   /* Primary text */
  --color-text-sec:      #6B7280;   /* Secondary / placeholder text */
  --color-white:         #FFFFFF;

  /* Gradients (use as background shorthand) */
  --gradient-primary:    linear-gradient(135deg, #6D28D9, #A78BFA);
  --gradient-gold:       linear-gradient(135deg, #F59E0B, #F97316);
  --gradient-teal:       linear-gradient(135deg, #0D9488, #14B8A6);
}
```

---

## 3. Typography

Font family: **Inter** (Google Fonts). Load weights 400, 500, 600, 700.

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
```

```css
:root {
  --font-base: 'Inter', sans-serif;

  /* Scale */
  --text-xs:   9px;
  --text-sm:   11px;
  --text-base: 13px;
  --text-md:   14px;
  --text-lg:   15px;
  --text-xl:   16px;
  --text-2xl:  18px;
  --text-3xl:  20px;
  --text-4xl:  22px;
  --text-5xl:  24px;
  --text-hero: 32px;
}
```

| Use case | Size | Weight |
|----------|------|--------|
| Page title / hero name | 24px | 700 |
| Section headers | 16–18px | 700 |
| Card titles | 14–15px | 600 |
| Body / secondary text | 11–13px | 400 |
| Badge / chip labels | 10–12px | 600 |
| Bottom nav labels | 10px | 400 (inactive) / 600 (active) |
| Desktop stat numbers | 32px | 700 |
| Desktop section headers | 20px | 700 |

---

## 4. Spacing & Sizing

```css
:root {
  /* Layout */
  --sidebar-width:   240px;   /* Desktop sidebar */
  --content-pad:     32px;    /* Desktop main content horizontal padding */
  --mobile-pad:      20px;    /* Mobile horizontal padding */
  --card-gap:        12px;    /* Gap between cards in a list */
  --section-gap:     24px;    /* Gap between sections */

  /* Radii */
  --radius-sm:   8px;
  --radius-md:   12px;
  --radius-lg:   16px;
  --radius-xl:   20px;
  --radius-2xl:  24px;
  --radius-full: 9999px;

  /* Touch targets */
  --touch-min:   44px;

  /* Bottom nav */
  --nav-height:  80px;

  /* Status bar (mobile) */
  --status-bar-height: 44px;

  /* Header */
  --header-height: 64px;
}
```

---

## 5. Shadows

```css
:root {
  --shadow-card:   0 4px 12px rgba(30, 27, 74, 0.07);
  --shadow-hero:   0 10px 28px rgba(30, 27, 74, 0.20);
  --shadow-fab:    0 8px 20px rgba(30, 27, 74, 0.25);
  --shadow-nav:    0 -4px 16px rgba(30, 27, 74, 0.08);
  --shadow-header: 0 2px 10px rgba(30, 27, 74, 0.05);
  --shadow-chip:   0 2px  6px rgba(30, 27, 74, 0.05);
  --shadow-modal:  0 20px 60px rgba(30, 27, 74, 0.18);
}
```

---

## 6. Reusable Components

### 6.1 Card — Task / Goal (Mobile)

Every task and goal card shares the same shell. Only the left accent strip color and right-side badge change.

```
┌─────────────────────────────────────────────────────┐
│█ [icon 48×48]  Card Title              [pts badge]  │
│█               Subtitle · Category     [status]     │
└─────────────────────────────────────────────────────┘
```

```css
.card {
  background: var(--color-white);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card);
  position: relative;
  overflow: hidden;
}

.card__strip {
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 4px;                        /* 5px on child-view cards */
  border-radius: var(--radius-lg) 0 0 var(--radius-lg);
  background: var(--strip-color);    /* set inline or via class */
}

.card__icon {
  width: 44px; height: 44px;
  border-radius: 22px;
  background: var(--strip-color);
  opacity: 0.12;                     /* 0.30 when task is complete */
}

.card__pts-badge {
  background: var(--color-gold-light);
  color: var(--color-gold);
  font-size: var(--text-sm);
  font-weight: 600;
  padding: 4px 8px;
  border-radius: var(--radius-full);
}
```

**Status badge colours:**

| Status | Background | Text color |
|--------|-----------|------------|
| Done | `--color-success-light` | `--color-success` |
| To Do | `--color-primary-light` | `--color-primary` |
| Pending | `--color-warning-light` | `--color-warning` |
| Overdue | `--color-danger-light` | `--color-danger` |
| Approve? | `--color-warning-light` | `--color-warning` |

### 6.2 Hero Card (Child Dashboard)

Full-width gradient card, 335px wide × 168px tall on mobile.

```css
.hero-card {
  background: var(--gradient-primary);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-hero);
  padding: 16px;
  position: relative;
  overflow: hidden;
}

/* Decorative circles (pseudo-elements) */
.hero-card::before {
  content: '';
  position: absolute;
  width: 150px; height: 150px;
  border-radius: 50%;
  background: rgba(255,255,255,0.06);
  top: -40px; right: -20px;
}
```

Interior layout:
- Left: Avatar circle (60×60, white 22% opacity background)
- Right of avatar: Child name (24px Bold, white), Level chip (white 25% bg), Points row (gold dot + "2,450 pts" Bold 18px white), XP progress bar (white 28% track, white fill), Stats strip (white 18% bg, two stat labels separated by a 1px divider)

### 6.3 Points / XP Badge

```css
.pts-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: var(--color-gold-light);
  color: var(--color-gold);
  font-size: var(--text-sm);
  font-weight: 600;
  padding: 4px 10px;
  border-radius: var(--radius-full);
}
```

### 6.4 Filter Chip Row

Used on Tasks and Goals pages to filter by status.

```css
.filter-chips {
  display: flex;
  gap: 8px;
  padding: 8px var(--mobile-pad);
  overflow-x: auto;
  scrollbar-width: none;
}

.filter-chip {
  white-space: nowrap;
  padding: 8px 16px;
  border-radius: var(--radius-full);
  font-size: var(--text-base);
  font-weight: 400;
  background: var(--color-white);
  color: var(--color-text-sec);
  box-shadow: var(--shadow-chip);
  cursor: pointer;
  border: none;
}

.filter-chip--active {
  background: var(--color-primary);
  color: var(--color-white);
  font-weight: 600;
}
```

### 6.5 Bottom Navigation (Mobile)

Fixed at the bottom of all mobile screens. 375×80px. White background with top shadow.

```css
.bottom-nav {
  position: fixed;
  bottom: 0; left: 0; right: 0;
  height: var(--nav-height);
  background: var(--color-white);
  box-shadow: var(--shadow-nav);
  display: flex;
}

.bottom-nav__item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  position: relative;
  padding-top: 10px;
}

/* Active pill behind icon */
.bottom-nav__item--active::before {
  content: '';
  position: absolute;
  top: 0;
  width: 44px; height: 26px;
  background: var(--color-primary-light);
  border-radius: var(--radius-full);
}

.bottom-nav__icon {
  width: 20px; height: 20px;
  border-radius: 6px;
}

.bottom-nav__label {
  font-size: var(--text-xs);    /* 10px */
  font-weight: 400;
  color: var(--color-text-sec);
}

.bottom-nav__item--active .bottom-nav__label {
  font-weight: 600;
  color: var(--color-primary);
}
```

**Nav order (both parent and child):** Home · Routines · Tasks · Goals · Rewards

### 6.6 Week Strip (Child Dashboard)

7-day horizontal strip showing Mon–Sun with a dot indicator for tasks completed.

```css
.week-strip {
  display: flex;
  background: var(--color-white);
  box-shadow: var(--shadow-header);
  padding: 8px 10px;
  gap: 7px;
}

.week-day {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 9px 0 0;
  border-radius: 14px;
  height: 64px;
}

.week-day--today {
  background: var(--color-primary);
}

.week-day__letter {
  font-size: 11px;
  font-weight: 400;
  color: var(--color-text-sec);
}
.week-day--today .week-day__letter { color: white; font-weight: 600; }

.week-day__number {
  font-size: 17px;
  font-weight: 700;
  color: var(--color-text-dark);
}
.week-day--today .week-day__number { color: white; }

.week-day__dot {
  width: 6px; height: 6px;
  border-radius: 3px;
  background: var(--color-accent);
  margin-top: auto;
  margin-bottom: 8px;
}
.week-day--today .week-day__dot { background: rgba(255,255,255,0.7); }
```

### 6.7 FAB — Floating Action Button

Used on parent Task Manager and Goal Manager to add new items.

```css
.fab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0 20px;
  height: 48px;
  border-radius: var(--radius-2xl);
  background: var(--gradient-primary);
  color: white;
  font-size: var(--text-md);
  font-weight: 600;
  border: none;
  cursor: pointer;
  box-shadow: var(--shadow-fab);
}
```

Fixed position on mobile: `bottom: calc(var(--nav-height) + 16px); left: 50%; transform: translateX(-50%);`

### 6.8 Gradient Stats Strip

Used at the bottom of the Parent Dashboard hero area and inside the Goals Parent View.

```css
.stats-strip {
  background: var(--gradient-primary);
  border-radius: var(--radius-lg);
  padding: 0 16px;
  height: 56px;
  display: flex;
  align-items: center;
  gap: 0;
  box-shadow: var(--shadow-hero);
}

.stats-strip__item {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.stats-strip__value {
  font-size: 20px;
  font-weight: 700;
  color: white;
}

.stats-strip__label {
  font-size: var(--text-sm);
  font-weight: 400;
  color: rgba(255,255,255,0.75);
}

.stats-strip__divider {
  width: 1px;
  height: 30px;
  background: rgba(255,255,255,0.3);
  margin: 0 8px;
}
```

### 6.9 Progress Bar

```css
.progress-bar {
  height: 5px;                 /* 7px on goal cards */
  border-radius: 3px;          /* 4px on goal cards */
  background: var(--color-bg); /* track */
  position: relative;
  overflow: hidden;
}

.progress-bar__fill {
  position: absolute;
  left: 0; top: 0; bottom: 0;
  border-radius: inherit;
  background: var(--fill-color); /* matches card accent */
  min-width: 8px;
}
```

### 6.10 Goal Card — Child View

Taller than task cards (136px). Has a circular progress indicator on the right, a colored left strip, a reward chip, and either a "Claim!" button (when 100% complete) or a "Xd left" label.

```css
.goal-card {
  background: var(--color-white);
  border-radius: var(--radius-xl);    /* 20px */
  box-shadow: var(--shadow-card);
  padding: 16px 16px 16px 24px;      /* extra left for strip */
  position: relative;
  overflow: hidden;
}

.goal-card--nearly-done {
  border: 2px solid var(--color-gold);
  box-shadow: 0 8px 22px rgba(30, 27, 74, 0.18);
}

.goal-card__ring {
  width: 70px; height: 70px;
  border-radius: 35px;
  /* Implement as SVG circle or conic-gradient */
  position: absolute;
  right: 16px; top: 28px;
}

.goal-card__claim-btn {
  background: var(--gradient-gold);
  color: white;
  font-weight: 700;
  font-size: var(--text-base);
  padding: 0 16px;
  height: 30px;
  border-radius: 15px;
  border: none;
  cursor: pointer;
}
```

### 6.11 Approval Card (Parent)

Used in Parent Dashboard and Goals Parent View for pending approvals.

```css
.approval-card {
  background: var(--color-white);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card);
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
  overflow: hidden;
}

.approval-card__strip {
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 4px;
  background: var(--color-warning);
  border-radius: var(--radius-lg) 0 0 var(--radius-lg);
}

.approval-card__actions {
  display: flex;
  gap: 8px;
  margin-left: auto;
}

.btn-approve {
  background: var(--color-success-light);
  color: var(--color-success);
  font-size: var(--text-sm); font-weight: 600;
  padding: 6px 10px;
  border-radius: 14px; border: none; cursor: pointer;
}

.btn-reject {
  background: var(--color-danger-light);
  color: var(--color-danger);
  font-size: var(--text-sm); font-weight: 600;
  padding: 6px 10px;
  border-radius: 14px; border: none; cursor: pointer;
}
```

### 6.12 Stat Chip (Quick Stats Row)

Used on both Parent Dashboard (quick glance row) and Goals Child View (Active / Almost! / Done row).

```css
.stat-chip {
  background: var(--chip-bg);     /* passed per instance */
  border-radius: var(--radius-lg);
  padding: 10px 16px;
  min-width: 100px;
}

.stat-chip__value {
  font-size: 22px;
  font-weight: 700;
  color: var(--chip-color);
}

.stat-chip__label {
  font-size: var(--text-sm);
  font-weight: 400;
  color: var(--chip-color);
  opacity: 0.75;
}
```

---

## 7. Screen Specifications

### 7.1 Child Dashboard (Mobile — `dashboard_child.php`)

**Viewport:** 375px. Scroll enabled. Bottom nav fixed.

**Section order (top → bottom):**

1. **Status Bar** — 44px white, time left + battery right.
2. **Header** — 64px white card + shadow. "Good Morning!" (13px secondary), child's name + "Dashboard" (18px bold). Avatar circle + notification dot top-right.
3. **Hero Card** — 335px × 168px, primary→primaryMid gradient, 20px radius, 20px margin each side. Contains avatar, name, level chip, points, XP bar, stats strip.
4. **Week Strip** — Full-width white card + shadow. 7 day chips. Today chip = primary fill, white text.
5. **Schedule Header Row** — "Today's Schedule" bold 17px + "See All" teal link right-aligned.
6. **Task Groups** — Sections labelled "Morning", "Afternoon", "Evening" (13px semi-bold secondary). Each section contains task cards (335px wide, 76px tall). See §6.1.
7. **Bottom Nav** — Fixed 80px, Home active.

**State rules:**
- Completed tasks: icon background opacity 0.30 instead of 0.12, title text opacity 0.6, status badge = "Done" (success green).
- "Complete!" button (84×28, primary bg) on incomplete tasks. Tapping triggers points animation and status change.

---

### 7.2 Parent Dashboard (Mobile — `dashboard_parent.php`)

**Viewport:** 375px scroll. Bottom nav fixed.

**Section order:**

1. Status Bar (44px)
2. Header — "Good Morning!" + "Family Dashboard", avatar + notif dot + bell icon
3. **Children Cards Row** — Horizontal scroll row. Each child card: 140×120px white, 20px radius. Avatar circle (52×52), name bold 14px, Level chip. Red badge (top-right) shows count of pending items. Tapping navigates to child's detail view.
4. **Quick Glance Row** — 3 stat chips side by side. "Tasks Due" (primaryLight/primary), "Pending Approval" (warningLight/warning), "Rewards Pending" (accentLight/accent).
5. **"Pending Approvals"** section — approval cards (§6.11) with inline Approve/Reject buttons.
6. **"Recent Completions"** section — compact 64px completion rows. Green check circle, task name, "ChildName · time", gold "+X pts" right-aligned.
7. **Family Strip** — full-width gradient banner: "Family earned X pts today!" + small "Add Task" white pill button.
8. Bottom Nav — Home active.

---

### 7.3 Tasks — Parent View (`task.php`, parent role)

**Viewport:** 375px scroll.

1. Status Bar + Header ("Task Manager" / date)
2. Filter chips: All · Pending · Done · Overdue
3. **"Pending Approval"** section with count badge. Approval cards.
4. **"Active Tasks"** section. Task cards (§6.1) with child name chip above title, status badge. Options: three-dot menu (edit/delete/reassign).
5. FAB: "+ Add New Task"
6. Bottom Nav — Tasks active.

**Parent task card extra elements vs child:**
- Child name chip (58×20, primaryLight bg) above title.
- No "Complete!" button — read-only for parent. Instead shows status badge.
- Three-dot vertical menu (3×4px dots) at far right.

---

### 7.4 Tasks — Child View (`task.php`, child role)

**Viewport:** 375px scroll.

1. Status Bar
2. **Gradient Header** (100px tall, primary→primaryMid). "My Tasks" bold 22px white. Sub "X tasks today — you got this!" 13px white 80%. Progress bar: "1 of 4 done".
3. Time-of-day section labels: Morning / Afternoon / Evening
4. Task cards (§6.1) — child variant: left strip 5px, "Complete!" button (84×28 primary) instead of status badge on incomplete items. Completed items show green check circle (40×40 successLight bg).
5. **Bonus Card** — full-width teal→accent gradient card at bottom: "Complete all tasks to earn a bonus!" + subtitle "Bonus: +15 pts if done by 8pm".
6. Bottom Nav — Tasks active.

---

### 7.5 Goals — Parent View (`goal.php`, parent role)

**Viewport:** 375px scroll.

1. Status Bar + Header ("Goal Manager")
2. Filter tabs: Active · Pending · Completed
3. **Stats strip** (gradient, §6.8): Active count · Pending count · Completed count (gold for Pending value)
4. **"Active Goals"** section. Goal cards: 335×110px. Child name chip, title, progress bar with "X / Y tasks (Z%)" label, reward chip ("Reward: Movie Night"), status badge ("On Track" = success), % large text right side.
5. **"Pending Approval"** section with count badge. Same goal card structure but status badge = "Approve?" (warning). Below card: inline Approve + Reject buttons (separate from the card).
6. FAB: "+ Create Goal"
7. Bottom Nav — Goals active.

---

### 7.6 Goals — Child View (`goal.php`, child role)

**Viewport:** 375px scroll.

1. Status Bar
2. **Gradient Hero Header** (130px, diagonal purple gradient). "My Goals" 24px Bold white. Motivational subtitle. Three gold trophy icon squares row.
3. Three stat chips: Active (primaryLight), Almost! (goldLight), Done (successLight)
4. **"Active Goals"** section. Goal cards (§6.10) — 335×136px. Left strip, right circle ring with %, progress bar, reward chip, "Xd left" label. Nearly-done cards: gold border + deeper shadow + "Claim!" button.
5. **"Completed (5)"** section header. Single wide card (trophy shelf): 5 mini trophy chips in a horizontal row (55×48 each, goldLight bg). Each has gold circle + label below.
6. Bottom Nav — Goals active.

---

### 7.7 Parent Dashboard — Desktop (`dashboard_parent.php`, wide viewport)

**Viewport:** 1440px (min-width: 1024px triggers desktop layout). No bottom nav.

**Layout: Sidebar + Main (CSS Grid or Flexbox)**

```
┌──────────────────────────────────────────────────────────────┐
│  Sidebar 240px  │  Main content 1200px                       │
└──────────────────────────────────────────────────────────────┘
```

**Sidebar:**
- Top 80px: gradient logo bar (primary→primaryMid). App name "FamilyQuest" 18px Bold white.
- Nav items (7): each 208×44px, 12px radius. Active: primaryLight bg + 4px primary left indicator + semi-bold primary text. Inactive: transparent bg + 30% opacity icon + secondary text.
- Nav order: Dashboard · Children · Tasks · Goals · Rewards · Routines · Settings
- Bottom: user card (208×64, slate bg, 16px radius). Avatar circle + "Parent Name" semi-bold + "Parent Account" secondary.

**Main area top bar (64px):**
- Left: "Good Morning, Name!" secondary 14px + "Family Dashboard" bold 20px
- Center: Search box (280×36, slate bg, 18px radius, placeholder text)
- Right: notification bell with danger dot, settings icon, date label

**Content sections:**

1. **Stat cards row** (4 cards, 272×88px each, 16px radius): Tasks Due / Pending Approvals / Rewards to Claim / Family Pts Today. Each: large bold number (32px) + label (13px secondary). Color-coded backgrounds (primaryLight / warningLight / successLight / goldLight).

2. **Children cards row** (3 cards, 368×120px each): Gradient banner top (40px, child's accent color, rounded top corners). Avatar + name bold 16px + level. Points. Mini progress bar "X/Y tasks". Red pending badge top-right corner.

3. **Two-column lower area:**
   - Left (556px): "Pending Approvals" — same approval cards as mobile but wider. Child name chip, task name, task type chip, points, Approve/Reject buttons.
   - Right (540px): "Today's Schedule" — compact task rows, 72px tall. Left strip, icon, task + child chip + category, pts badge + status badge.

4. **Weekly bar chart strip** (full width, 1136×100px, white, 20px radius): "Family Weekly Progress" title + "Week total: X · Best day: Y". 7 bars (Mon–Sun). Active day bar = gold, others = primary. Track = primaryLight.

---

## 8. Breakpoints & Responsive Behaviour

```css
/* Mobile first */
/* xs: < 480px  — single column, bottom nav */
/* sm: 480–767px — slightly wider cards */
/* md: 768–1023px — tablet: 2-column grid, no sidebar, top nav */
/* lg: ≥ 1024px — desktop: sidebar + main layout */

@media (min-width: 768px) {
  .bottom-nav { display: none; }
  /* Switch to top nav */
}

@media (min-width: 1024px) {
  body { display: flex; }
  .sidebar { width: var(--sidebar-width); }
  .main-content { flex: 1; }
}
```

On tablet (768–1023px): show a horizontal top nav bar (same items as bottom nav but horizontal). Cards shift to 2-column grid. Week strip becomes a full-month calendar view.

---

## 9. Interaction Patterns

| Interaction | Behaviour |
|-------------|-----------|
| Complete task (child) | Button flashes success green → icon fades → status badge changes to "Done" → points counter increments with animation |
| Approve task (parent) | Card fades out with success flash → pending count decrements |
| Reject task (parent) | Card fades out with danger flash |
| Claim reward (child, "Claim!" button) | Full-screen confetti animation → card moves to "Completed" section |
| Level up | Full-screen overlay: gold star burst + "Level X!" → auto-dismisses after 3s |
| Filter chip | Active chip snaps to primaryLight pill → list filters instantly (no page reload) |
| Child card tap (parent) | Navigates to that child's task list filtered to that child |

---

## 10. Role-Based UI Switching

The PHP session determines which UI variant to render. Use a CSS body class as the hook:

```php
// In header.php
$role = $_SESSION['role']; // 'parent' or 'child'
echo '<body class="role-' . $role . '">';
```

```css
/* Child-specific overrides */
.role-child .page-header {
  background: var(--gradient-primary);
  color: white;
  min-height: 100px;
}

.role-child .task-card__action {
  /* Show "Complete!" button */
}

.role-parent .task-card__action {
  /* Show status badge + three-dot menu */
}
```

---

## 11. Notification Badges

Red dot / number badge pattern used consistently:

```css
.badge {
  position: absolute;
  top: 8px; right: 8px;
  min-width: 18px; height: 18px;
  background: var(--color-danger);
  color: white;
  font-size: var(--text-sm);
  font-weight: 700;
  border-radius: var(--radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
}
```

Used on: navigation items, child avatar cards (parent view), header bell icon.

---

## 12. Empty States

When a section has no content, show a centred illustration placeholder (64×64 muted icon) + short message + optional CTA button.

```css
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  gap: 12px;
  text-align: center;
}

.empty-state__icon {
  width: 64px; height: 64px;
  border-radius: 32px;
  background: var(--color-primary-light);
  opacity: 0.6;
}

.empty-state__message {
  font-size: var(--text-lg);
  color: var(--color-text-sec);
}
```

Examples:
- No tasks today → "No tasks yet! Ask a parent to add some."
- No pending approvals → "All caught up! No approvals needed."
- No goals → "No active goals. Create one to get started!"

---

## 13. File / Asset Conventions

| Asset type | Location | Naming |
|------------|----------|--------|
| Global styles | `/css/main.css` | — |
| Parent-specific styles | `/css/parent.css` | — |
| Child-specific styles | `/css/child.css` | — |
| Component CSS | `/css/components.css` | — |
| Task/goal icons | `/images/icons/` | `icon-{category}.svg` |
| Avatar placeholders | `/images/avatars/` | `avatar-{color}.svg` |
| Reward images | `/images/rewards/` | `reward-{slug}.png` |

---

## 14. Figma → Code Mapping

| Figma frame | PHP file | CSS class prefix |
|-------------|----------|------------------|
| 01 Child Dashboard | `dashboard_child.php` | `.child-dash` |
| 02 Parent Dashboard Mobile | `dashboard_parent.php` | `.parent-dash` |
| 03 Tasks Parent View | `task.php` (parent role) | `.tasks-parent` |
| 04 Tasks Child View | `task.php` (child role) | `.tasks-child` |
| 05 Goals Parent View | `goal.php` (parent role) | `.goals-parent` |
| 06 Goals Child View | `goal.php` (child role) | `.goals-child` |
| 07 Parent Dashboard Desktop | `dashboard_parent.php` | `.parent-dash` (lg breakpoint) |
