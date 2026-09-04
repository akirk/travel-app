# Traveler

- Contributors: akirk
- Tags: travel, itinerary, trip-planner, travel-journal, maps
- Requires at least: 6.0
- Requires PHP: 7.4
- Tested up to: 7.1
- Stable tag: 1.0.0
- License: GPL-2.0-or-later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn booking confirmations into day-by-day travel itineraries you can follow, map, share and journal, all kept privately on your own site.

[Try Traveler in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/traveler/main/blueprint.json)
· [Try it with demo data](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/traveler/main/demo.json)

[Try it in OpenStation](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/traveler/main/blueprint-openstation.json) — the same app opened in desktop mode with the [OpenStation](https://github.com/WordPress/openstation) plugin.

## Description

Traveler is a private travel organizer that lives on your own WordPress. Paste or
upload booking confirmations, calendar exports and itinerary notes, and Traveler
turns them into a structured trip: flights, lodging, trains, rental cars,
activities and anything else you want to keep track of. Built on
[WpApp](https://github.com/akirk/wp-app), so it runs as its own app at
`/traveler/` instead of inside wp-admin.

Nothing leaves your site unless you decide it should. Trips are stored in your
own database, and every sharing feature is opt-in and revocable.

### Import instead of retype

Paste or upload booking confirmations, TripIt-style calendar exports, and plain
itinerary notes to turn them into structured trip timelines.

- ICS calendar files are read by a dedicated calendar parser, so recurring
  formats come in reliably without any external service.
- Other text is handed to the WordPress AI Client (`wp_ai_client_prompt()`) when
  an AI connector is configured, and falls back to a built-in local parser when
  it is not — so the plugin still works with no AI at all.
- A quick-plan parser understands short one-line notes ("Train to the coast,
  Friday 9:40") for entries you type by hand.
- On a phone the app registers as a Web Share Target, so a confirmation e-mail
  or a booking page can be shared straight into Traveler from another app.
- Imports land on a review screen first, so you can correct what the parser got
  wrong before anything is saved.

### A timeline you can actually follow

Each trip has a day-by-day timeline of itinerary items, each with a stable ID
and its own edit page. While a trip is running, the timeline highlights what is
happening now and what comes next, and the WordPress toolbar shows the trip you
are currently on.

- Items carry times, locations, end locations, booking references, notes, links
  and file attachments.
- Links get a preview (title, description, image) fetched from the linked page,
  so a booking URL turns into something recognisable.
- A lodging check compares the nights your trip spans against the lodging you
  have booked and points out the nights that are not covered yet.

### Route map

Every trip has a route map built from the locations in the itinerary, drawn with
a bundled copy of Leaflet on OpenStreetMap tiles. Places are geocoded in the
browser through OpenStreetMap's Nominatim service, and the coordinates are
cached on your site so the same itinerary does not have to be looked up again on
the next visit or on another device. When a place name is ambiguous you can pick
the right match from a list. A playback mode walks the route step by step.

### Travel journal

Turn a trip into a diary: each day gets a journal entry that starts from what
was on the itinerary that day, and entries can be prepared as blog post drafts
with a category and tags of your choosing, ready for you to publish.

### Sharing, on your terms

- Generate a share link for a trip, in a read-only "fellow traveller" view or a
  public one, and revoke it whenever you want.
- Subscribe to a trip as an ICS calendar feed, or to all of your trips at once,
  so the itinerary shows up in your usual calendar app.
- Download a trip as a single self-contained HTML file to keep or hand on.
- Delegate: let another user on the site create or edit trips on your behalf,
  with the capability required for that under your control.

### Works offline

Traveler installs as a Progressive Web App. The timeline, its assets and its
attachments are cached for offline use, and edits made while offline are queued
and synced when the connection comes back.

### AI Assistant

When the AI Assistant plugin is active, Traveler registers WordPress Abilities
for listing, creating, importing, inspecting, renaming, sharing and editing
travel plans and their itinerary items, so an assistant can work with your trips
without a separate integration.

### How it is stored

Trips are `traveler_trip` taxonomy terms. Itinerary entries are first-class
`traveler_item` posts assigned to the trip term, so each entry has a stable ID
and a dedicated edit page. Journal entries are `traveler_journal` posts. No
custom database tables are created; remove the plugin and your WordPress is as
slim as it was before.

### External services

Traveler works without any third-party service, but two optional features talk
to the outside world:

- The route map loads map tiles from [OpenStreetMap](https://www.openstreetmap.org/)
  and geocodes place names through its [Nominatim](https://nominatim.org/)
  service, from your browser. See the
  [OSMF privacy policy](https://wiki.osmfoundation.org/wiki/Privacy_Policy).
- Link previews fetch the page behind a URL you entered on an itinerary item, to
  read its title, description and preview image.

If an AI connector is configured for the WordPress AI Client, imported text is
sent to whichever provider you configured there. Without a connector, importing
uses the built-in local parsers and nothing is sent anywhere.

## Installation

1. Upload the `traveler` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Open `/traveler/` on your site

## Frequently Asked Questions

### Do I need an AI service to use this?

No. ICS calendar files are parsed locally, and free-form text falls back to a
built-in parser when no AI connector is configured. An AI connector makes
importing messy confirmation e-mails better, it is not required.

### Does the plugin create custom database tables?

No. Trips are taxonomy terms, itinerary items and journal entries are custom
post types, and the rest is post meta and options.

### Who can see my trips?

Only you, unless you say otherwise. Trips are private to their owner; the app
requires a logged-in user. Share links are created explicitly per trip and can
be removed again, and users you delegate to have to be granted the capability
you pick.

### Can I see my itinerary in my calendar app?

Yes. Each trip can be subscribed to as an ICS feed, and there is a feed covering
all of your trips at once.

### Does it work without a connection?

Yes. Traveler is a Progressive Web App: the timeline is cached for offline use
and changes you make offline are queued and synced when you are back online.

### Where is the map data from?

The map uses OpenStreetMap tiles and OpenStreetMap's Nominatim geocoder, called
from your browser. Looked-up coordinates are cached on your own site so repeat
visits do not query it again.

## Screenshots

1. A trip's day-by-day timeline, with the current and upcoming itinerary items highlighted.

## Changelog

### 1.0.0

- Import itineraries from ICS calendar files, pasted booking confirmations and
  short one-line notes, with an AI-assisted parser and a local fallback.
- Day-by-day trip timeline with now/next highlighting and per-item edit pages.
- Route map with cached geocoding, ambiguous-place picking and route playback.
- Travel journal entries per day, publishable as blog post drafts.
- Share links, per-trip and all-trips ICS feeds, and self-contained HTML export.
- Progressive Web App with offline caching, background sync and Web Share Target.
- WordPress Abilities for the AI Assistant plugin.
- Delegated trip editing for other users on the site.

## Development

Run the parser tests with:

```sh
composer test
```
