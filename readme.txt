=== WXP Events Feed ===
Contributors: wilko
Tags: events, feed, agenda, list, local events
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show the coming events of another website that runs WXP Local Events: as a list, with a block, a shortcode or an Elementor widget.

== Description ==

You run your events on one website with WXP Local Events, and you want to show the coming events on another website too, for example the site of a partner, a club or a neighbourhood. WXP Events Feed is installed on that other website. It reads the events from the events website and shows a simple list: date, title, short description and, if you like, a picture. The title and the picture link to the event on the events website, in a new tab.

* Works with a block ("Events feed", in the category WXP Plugins), a shortcode and an Elementor widget.
* The other website does not need WXP Local Events. Only the website with the events does.
* Visitors never wait for the events website: the list is kept on your site (default one hour) and refreshed in the background. If the events website is down, the last list stays visible.
* Only public data is shared by the events website: title, date and time, short description, image and link. Never reservations or personal data.
* Colours and fonts come from your theme. Change the look with the classes `.wxp-ef__*` or, in Elementor, with the style controls.
* Dates follow the date and time format of the website that shows them (or the text of the events website, your choice).
* Ready for translation. Dutch (informal and formal), German and French are included.

This plugin is free. It is not in the WordPress plugin directory, so it looks for new versions on GitHub (you can switch that off in the settings).

= Set up =

1. On the events website: update WXP Local Events to version 0.14 or newer, open Events → Settings → Share events and switch it on.
2. On this website: go to Settings → Events Feed and fill in the address of the events website. Use "Test the connection".
3. Add the block "Events feed", the Elementor widget, or the shortcode where you want the list.

= Shortcode =

`[wxp_events_feed limit="5" category="music" image="yes" description="yes" time="yes" date="site" heading="h3"]`

* `limit` - number of events, 1 to 20 (default 5).
* `category` - category slugs of the events website, comma separated.
* `image`, `description`, `time` - `yes` or `no` (default `yes`).
* `image_size` - `thumbnail` (default) or `medium`.
* `date` - `site` formats dates like this website does (default), `source` uses the text of the events website.
* `heading` - `h2` to `h6`, or `p`, for the titles (default `h3`).
* `class` - an extra CSS class.

= For developers =

* The look is made with CSS custom properties on `.wxp-ef`: `--wxp-ef-gap`, `--wxp-ef-image-size`, `--wxp-ef-date-color`, `--wxp-ef-date-size`, `--wxp-ef-title-size` and `--wxp-ef-line`.
* `wxp_events_feed_update_repo` - filter the GitHub repository ("owner/name") that holds the releases.
* The events website must allow requests from this server. WordPress refuses requests to addresses on a private network; on an intranet allow them with the core filter `http_request_host_is_external`.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the zip under Plugins → Add New → Upload.
2. Activate the plugin.
3. Go to Settings → Events Feed and fill in the address of the events website.

== Frequently Asked Questions ==

= Do I need WXP Local Events on this website? =

No. Only on the website that holds the events, version 0.14 or newer, with Share events switched on.

= What does the plugin send to the events website? =

An ordinary request for the list, with the address of this website in the user agent. No visitor ever contacts the events website because of this plugin: your server fetches the list, not the visitor's browser.

= What does it send to GitHub? =

Twice a day WordPress asks GitHub if there is a newer release. Nothing but the request itself is sent. Switch it off under Settings → Events Feed → Updates.

= The list does not show. Why? =

Visitors see nothing when the list cannot be loaded, to keep your pages clean. Administrators see a short message with the reason. Use "Test the connection" in the settings to find out what is wrong.

= I changed an event, but the other website still shows the old one. =

The list is kept for the time set in the settings (default 60 minutes). Use "Empty the cache" to see the change at once.

== Changelog ==

= 0.1.1 =
* Fix: "Test the connection" now tests the address as it is in the field, also when it is not saved yet. The address stays in the field afterwards, with a reminder to save it.

= 0.1.0 =
* First version: block, shortcode and Elementor widget, with a cache that never makes visitors wait.
