# Travel App

A private itinerary app powered by [WpApp](https://github.com/akirk/wp-app).

Paste or upload flight, hotel, train, rental car, calendar, or activity confirmations to save them as structured travel plans.

ICS calendar files use a dedicated calendar parser. Other text uses the WordPress AI Client (`wp_ai_client_prompt()`) when an AI connector is configured, with a basic local parser as fallback. Saved travel plans have detail pages and can be deleted from the app. When AI Assistant is active, the app also exposes WordPress Abilities for listing, importing, and deleting travel plans.

Trips are stored as `travel_app_trip` taxonomy terms. Itinerary entries are first-class `travel_app_item` posts assigned to the trip term, so each entry has a stable ID and dedicated edit page.
