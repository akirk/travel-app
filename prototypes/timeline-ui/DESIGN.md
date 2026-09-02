---
name: Traveler Timeline UI Concepts
description: Prototype-only comparison record for four alternative itinerary timeline interfaces.
---

# Design Comparison: Traveler Timeline UI Concepts

> **Prototype-only proposal.** This file documents four alternatives in `prototypes/timeline-ui/`. Command Ledger is the proposed direction for design review; it is not yet an approved production design, and the values below are not a global Traveler design system.

## Overview

**Creative North Star: "Four Operational Lenses"**

The gallery holds itinerary data constant while changing the hierarchy used to operate it. Command Ledger—the proposed direction—prioritizes dense planning, Route Atlas prioritizes place and movement, Day Canvas prioritizes time and duration, and Pocket Briefing prioritizes immediate one-handed decisions. The comparison is about topology, scanning behavior, and control language—not decoration alone.

The persistent gallery frame introduces the exercise, states that edits reset on reload, and exposes all four concepts as keyboard-operable tabs. Each selected concept fills the stage and restyles the shared editing flow in its own visual language. This is an evaluation harness: preserve the differences, compare them on equal terms, and do not merge their tokens into a synthetic fifth system.

**Key Characteristics:**

- Identical trip content and core actions across four structurally distinct views.
- A compact, sticky concept switcher keeps the comparison available while scrolling.
- Every concept exposes current context, upcoming context, itinerary editing, adding, and day-level disclosure in a form appropriate to its thesis.
- Prototype edits live only in the browser session and reset on reload.
- Keyboard operation, visible focus, readable contrast, narrow-screen use, and reduced-motion behavior are shared requirements.

**The Proposal Boundary Rule.** Treat Command Ledger as the direction being proposed for review while retaining the other concepts as comparison evidence. Do not imply production approval or promote any prototype tokens into production guidance.

## Colors

Each concept owns a complete palette. Token names below mirror its local CSS scope and apply only inside that concept.

### Comparison Frame

| Token | Value | Role |
| --- | --- | --- |
| Gallery background | `#f4f3ee` | Warm neutral canvas around the selected concept. |
| Gallery ink | `#1e2421` | Primary frame text and active tab indicator. |
| Gallery muted | `#626962` | Introductory copy, tab subtitles, and prototype note. |
| Gallery line | `#d8d9d2` | Header, tab, and switcher separators. |
| Gallery surface | `#fffef9` | Selected tab surface and default editor surface. |
| Gallery focus | `#0a66d4` | Shared three-pixel keyboard focus outline. |

### Command Ledger

| Token | Value | Role |
| --- | --- | --- |
| Ink | `#101820` | Headings, rules, primary action, and high-priority data. |
| Muted | `#52606b` | Metadata and secondary status. |
| Line | `#ccd2d5` | Table rows, rail boundary, and status borders. |
| Paper | `#f7f8f5` | Flat planning surface and themed editor. |
| Signal | `#e4532f` | Now/Next labels and current-state emphasis. |
| Today | `#eaf0eb` | Quiet current-row highlight. |

### Route Atlas

| Token | Value | Role |
| --- | --- | --- |
| Ink | `#173f39` | Deep green route structure, headlines, and compact mobile briefing. |
| Paper | `#f2eadc` | Map-like warm ground and editor surface. |
| Line | `#b6b6a0` | Route spines and event separators. |
| Orange | `#c8512c` | Primary action, route spines, ribbons, and current waypoint. |
| Blue | `#176887` | Transport wash and movement distinction. |
| Soft | `#e4dbc9` | Reserved warm tonal layer within the concept palette. |

### Day Canvas

| Token | Value | Role |
| --- | --- | --- |
| Ink | `#e8eee7` | Primary content on the dark schedule ground. |
| Muted | `#a8b1aa` | Supporting copy, hour labels, and field labels. |
| Ground | `#151a18` | Full concept and editor background. |
| Surface | `#202622` | Briefing cards, controls, fields, and grid corner. |
| Line | `#414a44` | Grid construction, panel separation, and field borders. |
| Lime | `#d6ef63` | Current day, current time, Now/Next emphasis, and primary action. |
| Cyan | `#5cd1c7` | Travel and multi-day stay blocks. |
| Coral | `#f17b63` | Activity blocks. |

### Pocket Briefing

| Token | Value | Role |
| --- | --- | --- |
| Ink | `#25231f` | Primary text, desktop add actions, and decisive controls. |
| Muted | `#6f6a60` | Secondary trip detail. |
| Paper | `#fbfaf5` | Day cards and editor sheet. |
| Warm | `#f0c85a` | Happening-now panel and current date tile. |
| Blue | `#3a6573` | Briefing card, time accents, and editor primary action. |
| Line | `#ded9cc` | Event divisions and empty-state border. |

**The Palette-Isolation Rule.** Never average, normalize, or interchange these colors across concepts; palette is part of each alternative's operational character.

## Typography

The gallery frame uses the native UI sans stack for neutral comparison chrome. Each concept loads its own bundled font family with regular and bold files, then uses familiar fallbacks if those assets fail.

### Command Ledger

**Primary family:** Traveler Ledger, with Arial Narrow and sans-serif fallbacks. Compact proportions, tight tracking, and tabular numerals make schedules feel like an operational record. The dark rail carries a forceful `2.15rem` trip title, oversized `2.65rem` day numbers divide the record, and most row metadata remains between `.72rem` and `.82rem`.

### Route Atlas

**Display family:** Traveler Atlas, with Georgia and serif fallbacks. The route headline is the most expressive type in the gallery (`clamp(2.35rem, 5.4vw, 5.8rem)`, `.86` line-height). **Utility family:** Traveler Pocket or the native sans stack for times, labels, metadata, and controls, keeping the editorial display voice from reducing operational legibility.

### Day Canvas

**Primary family:** Traveler Canvas with sans-serif fallback. The hierarchy pairs a strong `2.7rem` trip heading with dense `.72rem`–`.9rem` schedule labels. Tabular numerals and clipped single-line event titles support direct time-grid comparison.

### Pocket Briefing

**Primary family:** Traveler Pocket with sans-serif fallback. The voice is friendly but decisive: the personal briefing headline reaches `3.55rem` at desktop and `2.15rem` on narrow screens, while day controls and event details use compact `.72rem`–`.9rem` text for thumb-distance scanning.

**The Display-and-Utility Rule.** Expressive type may set context, but times, labels, statuses, and controls remain compact and immediately readable.

## Layout

### Comparison Frame

The frame centers content up to `1500px`. Its header pairs Traveler identity and comparison premise with a prototype-status note. Directly below, a sticky four-column tablist keeps concept switching visible. Below `900px`, the tabs become a horizontally scrollable strip; below `620px`, subtitles disappear and the tab height contracts. The selected panel occupies at least the remaining viewport height.

### Command Ledger — Rail and Record

Desktop uses a `230px` sticky dark day rail beside a fluid ledger. A split, full-color Now/Next command band leads into day sections rendered as strongly ruled, grid-based rows: time, icon, event, place, and state. The current date uses Signal in the rail and square dark pictograms punctuate the record. At `900px`, the rail becomes a horizontal day strip above the record and event rows drop to four columns. At `620px`, Now and Next stack, state pills disappear, and rows reduce to time, icon, and content while place metadata moves beneath the event.

### Route Atlas — Chapters Along a Route

Desktop opens with an editorial-scale route headline and actions, followed by a five-stop route summary anchored to a thick Orange route line. Day chapters arrange oversized sticky dates beside softly washed event streams; a continuous Orange spine, waypoint dots, and broad directional ribbons connect geography and chronology. The current event reverses to Ink and Paper. At `900px`, the date column narrows and the route summary can scroll horizontally. At `620px`, chapters become single-column, dates stop sticking, event tags move under their content, and a compact Now panel appears before the route.

### Day Canvas — Schedule Geometry

The concept is a horizontal six-day calendar with a `58px` time gutter, fixed all-day stay lane, and a `780px` high time grid spanning 07:00–22:00. Event position and height encode start time and duration; Today receives the widest lane plus a Lime wash, the entire Now card turns Lime, and a three-pixel Lime rule marks current time across all lanes. The grid keeps a `1110px` minimum width at every viewport and becomes the intentionally scrollable object on narrower screens. Below `620px`, outer padding contracts, header actions wrap into the flow, and Now/Next cards stack without collapsing the schedule geometry.

### Pocket Briefing — Immediate Context and Disclosure

Desktop presents a sticky briefing card up to `480px` wide beside a day accordion up to `680px`, against a deliberate Warm-to-neutral split ground. The briefing elevates happening now and next; the current day card pulls toward it with stronger depth while one later day remains expanded initially. At `900px`, the columns collapse to one and the briefing loses sticky positioning. At `620px`, page padding and radii tighten, the greeting scales down, expanded day events use the full card width, and a single compact circular quick-add action remains fixed near the lower-right edge.

**The Topology-Is-the-Concept Rule.** Preserve the rail, route chapters, time grid, and briefing-plus-accordion as distinct comparison structures; replacing them with a common card list invalidates the exercise.

## Elevation & Depth

Command Ledger is almost entirely flat: borders, row rules, and a quiet tonal current-state fill provide hierarchy. Route Atlas also relies on lines and colored washes; the current waypoint gains only a paper-colored halo so it stays legible on the spine. Day Canvas uses structural grid lines plus a moderate event-block shadow (`0 5px 18px rgb(0 0 0 / .22)`) to separate overlapping schedule layers. Pocket Briefing is the most physically layered, lifting its briefing card (`0 22px 48px rgb(37 46 45 / .2)`), day cards (`0 5px 18px rgb(52 48 40 / .06)`), and floating action (`0 10px 28px rgb(0 0 0 / .22)`).

The shared editor casts a leftward desktop drawer shadow (`-18px 0 50px rgb(0 0 0 / .16)`) over a dimmed backdrop. The toast uses motion and contrast rather than additional surface layering.

**The Structural-Depth Rule.** Depth must clarify containment, overlap, or reachability; it is not a shared decorative effect to apply uniformly across concepts.

## Shapes

Command Ledger is rectilinear and data-dense: square editor surfaces, thin rules, compact `4px` status corners, `6px` action corners, and restrained `7px` day controls. Route Atlas combines cartographic lines and dots with fully pill-shaped actions and tags; its ribbon ends in an arrow point. Day Canvas is modular and technical, using mostly square grid construction with compact `5px`–`7px` schedule blocks and `9px` shared controls. Pocket Briefing is soft and hand-scaled: a `30px` briefing container, `16px` Now panel, `14px` day cards, `10px` date tiles, and pill actions.

On screens below `620px`, the shared editor becomes a bottom sheet with rounded top corners (`18px`). Pocket Briefing retains the strongest sheet silhouette, using `26px` top corners and a desktop-height cap of `min(92vh, 780px)`.

**The Silhouette Rule.** Corners reinforce the operating model: strict for records, cartographic for routes, modular for schedules, and tactile for one-handed briefing.

## Components

### Comparison Tabs

Four equal columns show a concept name and short planning instinct. The selected tab uses the warm surface and a three-pixel ink underline. Arrow keys move left and right, Home and End jump to extremes, and tab focus follows selection. At compact widths the strip scrolls horizontally rather than squeezing labels below legibility.

### Shared Actions

Every concept provides **Jump to now** and **Add item**, though placement and styling vary. Jumping scrolls the current event into view, transfers focus where possible, and announces the result in the toast. Add and editable event titles open the shared editor. Buttons retain a minimum `42px` height; interactive event rows retain a minimum `44px` target.

### Command Ledger Controls

- **Day navigation:** sticky desktop rail becomes a horizontal mobile strip; current day receives a quiet fill.
- **Disclosure:** an underlined Collapse/Expand control sits in each day header.
- **Status:** outlined uppercase Planned, Passed, Generated, or In progress markers; the current marker uses Signal.
- **Primary action:** compact dark rectangle consistent with the ledger's ruled discipline.

### Route Atlas Controls

- **Route navigation:** the summary is informational, while sticky date chapters anchor scrolling context.
- **Disclosure:** Fold chapter/Expand lives with each date heading.
- **Event type:** outlined pill labels distinguish transport, stay, activity, and generated checkout entries.
- **Primary action:** Orange pill; current route position uses an Orange waypoint.

### Day Canvas Controls

- **Schedule navigation:** the grid itself is a keyboard-focusable horizontal scroll region with an explicit accessible label.
- **Event blocks:** editable items are positioned buttons; generated checkout items are non-button blocks.
- **Temporal cues:** all-day stay spans, colored event categories, current-day header, and current-time line expose duration and overlap.
- **Primary action:** Lime on dark ground; secondary controls use dark Surface with Line borders.

### Pocket Briefing Controls

- **Immediate actions:** the briefing includes a compact icon action for the current item. Desktop pairs a list-header Add item control with a fixed `+ Add plan` pill; mobile removes the duplicate header action and condenses the fixed control to a circular plus while preserving its accessible name.
- **Disclosure:** each day header is a full-width button with date tile, city, item count, and rotating chevron.
- **Context cards:** Happening now, Next, and an explicit free-evening state prioritize travel-time decisions.
- **Primary action:** Ink in the list context and Blue in the editor, both fully rounded.

### Themed Editor

The same add/edit form is used for all concepts: title, type, in-trip date, start/end time, location, destination, and notes. Desktop defaults to a right-side drawer no wider than `520px`; below `620px`, it becomes a one-column bottom sheet. Closing restores focus to the invoking control, the dialog traps attention with a modal backdrop, and submitting updates all four prototype renderings for the current browser session.

- **Ledger editor:** Paper background, Traveler Ledger type, square sheet and fields, compact dark primary button.
- **Atlas editor:** Paper background, Traveler Atlas heading voice, Traveler Pocket form labels, and Orange pill actions.
- **Canvas editor:** Ground background, Ink text, Surface fields with Line borders, Lime primary action, and dark secondary action.
- **Pocket editor:** Paper bottom sheet with `26px` top corners and pill actions; primary action uses Blue.

### Motion and Feedback

Concept changes enter over `260ms` with a short opacity-and-clip reveal. Editor backdrop and sheet transitions run between `200ms` and `240ms`; disclosure chevrons and toast feedback use `180ms` transitions. When reduced motion is requested, scroll behavior becomes immediate and animation/transition duration collapses to `.01ms`.

## Do's and Don'ts

### Do:

- **Do** compare identical data and equivalent tasks across every concept.
- **Do** preserve each concept's palette, typography, topology, control language, responsive behavior, and editor treatment as a coherent set.
- **Do** keep the prototype-only message visible and state that edits reset on reload.
- **Do** retain keyboard tab navigation, visible focus, semantic controls, focus restoration, readable contrast, and reduced-motion support.
- **Do** test Command Ledger as a planning record, Route Atlas as a geographic narrative, Day Canvas as a duration map, and Pocket Briefing as a travel-time briefing.

### Don't:

- **Don't** describe the proposed Command Ledger direction as already approved or shipped in production.
- **Don't** copy these tokens into a root `DESIGN.md` or `.impeccable/design.json`; they are local evaluation data, not the Traveler production system.
- **Don't** combine favorite parts into a hybrid without defining and evaluating a new concept separately.
- **Don't** flatten the four structures into recolored versions of one card list.
- **Don't** treat session-only editing as persistence, synchronization, or production behavior.
