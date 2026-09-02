# Timeline UI concepts

This dependency-free gallery proposes **Command Ledger** as the direction for a future Traveler timeline redesign. It retains the other three explorations so reviewers can compare the same itinerary, states, and interactions on equal terms.

The proposal is intentionally isolated: it does not call WordPress, persist changes, or modify the production trip template. Integrating the selected direction into `templates/trip.php` should happen in a follow-up change after design review.

## Review locally

From the repository root, run:

```sh
python3 -m http.server 4173 --directory prototypes/timeline-ui
```

Then open `http://127.0.0.1:4173/#ledger`.

## Proposed direction

**Command Ledger** treats the itinerary as an operations board: a sticky day index, an explicit Now/Next command band, strongly ruled schedule rows, compact state labels, and inline editing. It is optimized for rapid scanning on desktop while becoming a horizontal day strip and stacked schedule on mobile.

The proposal preserves the plugin's existing itinerary vocabulary and represents flights, trains, rental cars, lodging, activities, generated checkout/return events, multi-day stays, notes, locations, and past/current/future states.

## Comparison concepts

All four concepts render the same shared itinerary data from `app.js`:

- **Command Ledger:** proposed direction for compact planning and fast scanning.
- **Route Atlas:** geographic progression and destination context.
- **Day Canvas:** duration, overlaps, and schedule shape.
- **Pocket Briefing:** immediate next actions and one-handed use.

Available prototype interactions include concept switching, keyboard-operated tabs, collapsing days, jumping to the simulated current time, selecting events, and adding or editing items through a shared in-memory editor.
