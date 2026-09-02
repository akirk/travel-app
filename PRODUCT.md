# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Travelers organizing their own trips in WordPress. They need to turn booking confirmations and notes into a reliable itinerary, scan what is happening now and next, and make quick corrections before or during travel.

## Product Purpose

Traveler is a private travel organizer for WordPress. It converts booking confirmations, calendar exports, and itinerary notes into editable day-by-day trip timelines. Success means a traveler can understand the sequence of a trip at a glance and confidently find or change the detail they need.

## Positioning

Traveler keeps itinerary data inside the traveler's WordPress installation while supporting assisted imports, first-class itinerary items, private sharing, offline access, and travel journaling.

## Operating Context

The primary surface is a responsive web timeline used on desktop while planning and on a phone while traveling. Entries include flights, trains, rental cars, lodging, activities, locations, times, notes, links, and attachments. Timelines can be editable, privately shared, publicly shared, or downloaded for offline use.

## Capabilities and Constraints

- The current application is server-rendered PHP with inline CSS and dependency-free JavaScript.
- The timeline groups items by day, synthesizes checkout and rental-return events, highlights current time, and supports a Now/Next summary.
- Accessibility, responsive behavior, private-data masking, and read-only sharing modes must remain intact in any production redesign.
- The four timeline concepts in `prototypes/timeline-ui/` are isolated evaluation artifacts and do not persist edits. Command Ledger is the proposed direction for design review, but it does not become the production design until a separate integration change is approved.

## Brand Commitments

The product name is Traveler. Its voice is calm, direct, practical, and focused on the traveler's plans rather than implementation details.

## Evidence on Hand

- `demo.json` contains representative multi-country, road-trip, and business-trip itineraries.
- `templates/trip.php` contains the current production timeline, editing, sharing, lodging, and journal behaviors.
- No approved external brand system, imagery library, testimonials, or commercial claims are available and none should be invented.

## Product Principles

- Make the next relevant travel detail immediately findable.
- Preserve chronological and geographic context while reducing editing friction.
- Keep private travel data under the user's control.
- Work clearly across planning, active-trip, shared, and offline contexts.
- Use familiar controls and explicit status feedback under travel-time pressure.

## Accessibility & Inclusion

Interfaces must be keyboard operable, preserve visible focus, use semantic landmarks and controls, maintain readable contrast, respect reduced motion, and remain usable at narrow mobile widths.
