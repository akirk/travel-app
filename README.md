# Travel App

- Contributors: akirk
- Tags: itinerary, trip-planner, wpapp
- Requires at least: 6.0
- Requires PHP: 7.4
- Tested up to: 7.1
- Stable tag: 1.0.0
- License: GPL-2.0-or-later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn booking confirmations into day-by-day travel itineraries you can follow, map, share and journal, all kept privately on your own site.

## Description

Travel App is a [WP App](https://wpapps.kirk.at/), an app for WordPress with the primary focus of being
used by yourself, or your family or social group. It can only be accessed logged-in
and adds a menu entry to the Masterbar to be reached.

Travel App is a private travel organizer that allows you to organize travel itineraries
as timelines. You can paste booking confirmations, calendar exports and itinerary notes,
and Travel App turns them into a structured trip: flights, lodging, trains, rental cars,
activities and anything else you want to keep track of.

There are lots of other small features:
- map view (with Open Street Map),
- journal entries per day,
- you can share your trip with a link,
- subscribe to your trips in your Calendar,
- access your trips (including reservation files) offline, and
- delegate another user on your site to create or edit trips on your behalf.

As every WP App, Travel App comes with support for the Abilities API by providing all
the abilities necessary to use the app with an AI. 

If WordPress has AI Connectors configured, it will use them to better parse your pasted
booking confirmations.

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

You find the source at [https://github.com/akirk/travel-app](https://github.com/akirk/travel-app)
