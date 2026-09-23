# Travel App

- Contributors: akirk
- Tags: travel, itinerary, trip-planner, travel-journal, wp-app
- Requires at least: 6.0
- Requires PHP: 7.4
- Tested up to: 7.1
- Stable tag: 1.0.0
- License: GPL-2.0-or-later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn scattered booking confirmations into a trip you can actually follow, privately on your own WordPress.

## Description

[Try Travel App with demo data](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/travel-app/main/demo.json)
· [Start with an empty app](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/travel-app/main/blueprint.json)

Travel planning has a way of spreading itself out: a booking confirmation in
your e-mail, train times in a calendar, an address in a message and a list of
things you want to do somewhere else. Then, while you are already on your way,
you have to remember where all of it went.

I built Travel App to bring those pieces together on a WordPress that is yours.
Paste a confirmation, upload a calendar export or type a quick note such as
"Train to the coast, Friday 9:40." The app turns it into a day-by-day trip with
flights, lodging, trains, rental cars and whatever else belongs in your plan.

This is a WordPress plugin, but it does not feel like another settings screen.
It runs as its own app at `/travel-app/`, works well on a phone and can be
installed as a Progressive Web App. If you already have a WordPress, you already
have a place where your travel plans can live.

### Start with what you already have

Retyping a booking confirmation into another service is not a great start to a
holiday. Travel App can read ICS calendar files locally, turn pasted itinerary
text into entries and understand short notes you type yourself. On a phone, you
can share a booking page or confirmation e-mail straight into the app.

Every import stops at a review screen first. You can see what was understood,
correct what was not, and only then add it to the trip. If a WordPress AI Client
connector is available, it can help with messier confirmations. If there is no
AI configured, the local parsers still work: AI makes some imports better, but
it is not the price of admission.

### Use it while you are traveling

The result is a timeline you can actually follow. It is grouped by day, shows
what is happening now and what comes next, and keeps the useful details close:
times, locations, booking references, notes, links and attachments.

There are a few details I especially wanted when using an itinerary on the go:

- A route map connects the places in your trip and can play the journey one
  step at a time.
- A lodging check points out nights for which you have not booked somewhere to
  sleep yet.
- Booking links get a recognisable preview instead of remaining a wall of URLs.
- The timeline and its attachments remain available offline. Changes made
  without a connection wait and sync when you are back online.
- A trip can appear in your usual calendar through an ICS subscription.

### Your trip stays yours

Trips are stored in your own WordPress database. There is no Travel App account
to create, no subscription, and no service that needs to keep a copy of your
itinerary.

This does not mean that a trip has to remain trapped on one screen. You can make
a read-only link for a fellow traveller, publish a public link, download the
whole trip as one self-contained HTML file, or let another user on your site
help edit it. Those are choices you make for an individual trip, and a share
link can be revoked again.

### Bring the memories home

A plan is useful before and during a trip, but afterwards it can become the
outline of what happened. Travel App can create a journal entry for each day,
starting with the places and activities already on the itinerary. When you want
to tell the longer story, prepare those entries as WordPress post drafts with
your own category and tags.

### It can work with your other WordPress tools

When the [AI Assistant](https://github.com/akirk/ai-assistant) plugin is active,
Travel App exposes WordPress Abilities for creating, importing, inspecting,
renaming, sharing and editing travel plans. This lets the assistant work with
your trips without giving it a separate store of travel data.

Travel App is built on [WpApp](https://github.com/akirk/wp-app). You can also
[try it in OpenStation](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/travel-app/main/blueprint-openstation.json),
where it opens in a desktop-style window.

For the technically inclined: trips are `travel_app_trip` taxonomy terms,
itinerary entries are `travel_app_item` posts and journal entries are
`travel_app_journal` posts. There are no custom database tables. It is ordinary
WordPress data, in the place you chose for it.

### External services

Travel App works without any third-party service, but two optional features talk
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

1. Upload the `travel-app` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Open `/travel-app/` on your site

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

Yes. Travel App is a Progressive Web App: the timeline is cached for offline use
and changes you make offline are queued and synced when you are back online.

### Where is the map data from?

The map uses OpenStreetMap tiles and OpenStreetMap's Nominatim geocoder, called
from your browser. Looked-up coordinates are cached on your own site so repeat
visits do not query it again.

## Screenshots

1. A trip's day-by-day timeline, with the current and upcoming itinerary items highlighted.
2. The trip list on a phone: the trips coming up with their dates and lengths, and the finished ones by year below.

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
