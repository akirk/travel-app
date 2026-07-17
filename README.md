# Travel App

A private travel organizer for WordPress.

[Try Travel App in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/travel-app/main/blueprint.json)

Paste or upload booking confirmations, TripIt calendar exports, and itinerary notes to turn them into structured trip timelines. Each trip has editable itinerary items for flights, lodging, trains, rental cars, activities, and other travel details.

ICS calendar files use a dedicated calendar parser. Other text uses the WordPress AI Client (`wp_ai_client_prompt()`) when an AI connector is configured, with a basic local parser as fallback. Saved travel plans have detail pages and can be deleted from the app. When AI Assistant is active, the app also exposes WordPress Abilities for listing, importing, and deleting travel plans.

Trips are stored as `travel_app_trip` taxonomy terms. Itinerary entries are first-class `travel_app_item` posts assigned to the trip term, so each entry has a stable ID and dedicated edit page.

## Development

Run the parser tests with:

```sh
composer test
```
