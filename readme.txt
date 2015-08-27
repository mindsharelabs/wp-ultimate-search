=== WP Ultimate Search ===
Contributors: mindshare
Donate link: http://mind.sh/are/donate/
Tags: search, ajax, metadata, meta, post meta, autocomplete, jquery, facet, faceted search, faceting, advanced custom fields, acf, taxonomy, taxonomies, term, terms, facets, geo, wp-geo, radius, latitude, longitude, location
Requires at least: 4.0
Tested up to: 4.3
Stable tag: 2.0.3
 
Powerful AJAX-based search alternative which supports faceting queries by taxonomies, terms, location, and post metadata.

== Description ==

A highly customizable AJAX-based WordPress search bar alternative with the ability to autocomplete [faceted search queries](http://en.wikipedia.org/wiki/Faceted_search). Users can quickly and dynamically browse through your site's taxonomies and post metadata to find exactly what they're looking for, and results can be loaded beneath the search bar instantly.

<h4>Features</h4>

* Searches post title and body content
* Can search by multiple keywords, and by full phrases
* Highlights search terms in results
* Option to send search queries as events to your Google Analytics account
* Facets by post category
* Can search in multiple categories (OR or AND search)
* Category options are dynamically generated and autocompleted as you type
* Attractive and lightweight interface based on jQuery, Backbone.js, and the VisualSearch.js library
* Customizable results template using standard WordPress functions
* Search through an unlimited number of user-specified taxonomies and meta fields (including data contained in Advanced Custom Fields).
* Conduct radius searches against data stored in the ACF Map field (i.e. search for posts within x km of a user-specified location).

== Installation ==

1. Upload the `wp-ultimate-search` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add a shortcode to a post, use the template tag in your theme, or use the sidebar widget.

To use the shortcode:
Place `[wp-ultimate-search-bar]` where you'd like the search bar, and `[wp-ultimate-search-results]` where you'd like the results.

To use the template tag:
Put `wp_ultimate_search_bar()` where you'd like the search bar, and `wp_ultimate_search_results()` where you'd like the results.

== Frequently Asked Questions ==

= How do I customize the search results template? =

When a search is executed, the plugin first looks in your current theme directory for the file wpus-results-template.php. If no file is found, it falls back to the default results template, located in /wp-ultimate-search/views/wpus-results-template.php.

To customize the template, first copy the wpus-results-template.php file into your theme directory. The code within this file is a standard WordPress loop, which you can modify in any way you choose. To learn more about WordPress loops, see the codex.

= How do I stop my the viewport from zooming in when a user touches the search box on a mobile device? =

If you have a mobile website and you want to disable autozooming on input fields (like the search bar), add the following code to your header.php file:

`<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" />`

= I've added a post with some meta fields I'd like to make searchable, but they're not showing up under Post Meta Settings =

Since Wordpress uses post meta fields to track a lot of things you probably don't care to know about, we've added a filter to WPUS to only show a meta field as an option if it occurs more than three times. If you've recently added a meta field to a post, try adding that meta field to two more posts and you should see it appear as an option under Post Meta Settings.

= How do I show search results on a map? =

See the tutorial [here](http://mindsharelabs.com/kb/using-wp-ultimate-search-for-geo-search/).

= More Info =

Help documents and support forums are available at [Mindshare Labs](http://mindsharelabs.com/).

== Screenshots ==

1. Search bar with results.

`/assets/screenshot-1.jpg`

2. Settings screen showing taxonomy options.

`/assets/screenshot-2.jpg`

3. Also compatible with touch devices.

`/assets/screenshot-3.jpg`

4. WP Ultimate Search being used on a music archive

`/assets/screenshot-4.jpg`

5. Radius search. [See a demo](http://ultimatesearch.mindsharelabs.com/radius-search-demo/#search/radius=916+Baca+Street%2C+Santa+Fe%2C+NM%2C+United+States&distance+(km)=6)

`/assets/screenshot-5.jpg`

== Changelog ==

= 2.0.3 =
* Bugfix for Tags

= 2.0.1 =
* Added all premium "Pro" features into free version, removed license activation
* Added Spanish translation thanks to Andrew Kurtis <andrewk@webhostinghub.com>
* Added Russian translation thanks to Andrijana Nikolic <andrijanan@webhostinggeeks.com>

= 1.6.1 =
* Fixed missing argument bug in widget

= 1.6 =
* Added built in custom results templates (post with thumbnail, title only, thumbnail only)
* Added ability to override default settings via shortcode / template tag
* Added support for ACF date field
* Added support for ACF true/false field
* Fixed bug with AND logic and hierarchical taxonomies
* Fixed cursor not appearing on initial search bar focus in Square style
* Fixed bug where clicking on placeholder text would prevent search
* Changed "include" and "exclude" fields to require term IDs instead of names
* Continuing style refinements

= 1.5.2 =
* Added cancel button next to facets in single facet mode
* Dropdown menu no longer appears in wrong location when facets are deleted
* "AND" logic now works correctly again
* Fixed bug where meta field options wouldn't display properly
* Fixed bug caused by using Single Facet Mode with a metadata facet

= 1.5.1 =
* Values will no longer appear in dropdown if they're already in use in the search bar
* Fixed shortcode outputting contents at top of page
* Value dropdown will no longer appear when navigating to results page via permalink

= 1.5 =
* Added ability to search for posts by user
* Added ability to confine all searches to a single facet
* Added ability to only allow facets to be used once
* Added option to disable permalink generation
* Refactored database query for faster response times
* Fixed bug where multiple parameters wouldn't be received from permalinks
* Fixed bug where URL wouldn't reset when 'clear search' was clicked
* Fixed broken "results page" dropdown
* Misc. style fixes and normalizing

= 1.4.5 =
* Fixed bug with text searches

= 1.4.4 =
* Updated visualsearch.js to latest version
* Can now specify remainder preface for text queries
* Settings will now be set to defaults on first install
* Fixed extra history state being added when navigating to results page from widget
* Moved screenshots to /assets/ folder

= 1.4.3 =
* Fixed bug that prevented radius searches from working properly
* Fixed bug that broke in-page anchors on some sites
* Misc. bugfixes

= 1.4.2 =
* Updated options framework to work with new admin styles
* Simplified pro upgrade process
* Fixed typo in installation instructions
* Fixed bug caused by ampersands in permalinks
* Fixed PHP notices on multisite installations

= 1.4.1 =
* Fixed PHP warnings

= 1.4 =
* Added radius search capability based on ACF Map field
* Added ability to confine taxonomy searches to given terms
* Added ability to exclude specific post types from results
* Added ability to search for addresses stored with an ACF Map field
* Added ability to disable autocomplete per facet
* Fixed bug where spaces in facet names would break permalinks
* Fixed bug where permalinks weren't updated when last facet was removed
* Fixed bug where lowercase terms would appear after capitalized ones
* Fixed bug where pressing backspace would sometimes cause the browser to navigate back
* Fixed bugs that sometimes prevented premium upgrade

= 1.3 =
* Added ability to choose either AND or OR logic for query components within the same taxonomy
* Added the ability to search for posts based on their ACF checkboxes
* Added support for ACF comboxboxes
* Upgraded to EDD for licensing and upgrade
* Added settings to plugin action links
* Fixed bug where taxonomies created by plugins like Taxonomy Manager would generate notices

= 1.2.1 =
* Misc. bugfixes to 1.2

= 1.2 =
* Added an alternative square search bar style
* Added option to disable built-in taxonomies and revert to a plain text search
* Added option to restrict script loading to only pages with search bar
* Improved iOS support
* Fixed bug where tag search wouldn't work for some users
* Fixed "clear search" icon not clearing results
* Fixed wpdb->prepare() notice appearing in WP 3.6
* Fixed dropdown items appearing outside of search bar when a term was deleted
* Added browser navigation history when moving from widget to results page
* Minified visualsearch.js script

= 1.1.1 =
* Misc. bugfixes to 1.1

= 1.1 =
* Added support for special characters in facet values
* Fixed permalinked searches rendering spaces as underscores
* Fixed bug that would cause the "no results" message to not show
* Removed iOS/Safari warning message. Update to Safari has fixed the bug.
* Added Clear Search Results button option
* Added option to disable the facet options popping up on first focus
* Added option for placeholder text in the search bar
* Fixed bug where search results page wouldn't load on sites without pretty permalinks
* Added option to disable search results highlighting
* DB queries updated to support Wordpress 3.6
* Misc. style refinements and bugfixes

= 1.0.3 =
* Updates to premium upgrade process
* Removed premium 'teasers' from options page to comply with repository guidelines

= 1.0.2 =

WARNING: If you encounter any problems with this update, check the "Reset options" box and hit Save Changes to restore initial settings.

* Sped up load times
* Silenced PHP notices when wp_debug was turned on
* Fixed bug that prevented option saving with some database configurations

= 1.0.1 =

* Bugfix release:
* Minified spin.js
* Moved result highlighting out of php (buggy) and into JS
* Fixed bug where warnings would be issued on sites with no eligible meta fields
* Removed debugging function in visualsearch.js that occasionally caused conflicts with other scripts
* Updated visualsearch.js to v0.4.0
* Added default styling to search bar so bar will be displayed before scripts have loaded 

= 1.0 =
* Option to replace WordPress default search
* Ability to search in custom taxonomies (with upgrade)
* Ability to search in post meta fields (with upgrade)
* Searches now generate permalinks
* Supports user-created search results templates
* Many more tweaks and optimizations

= 0.4 =
* Can search post tags
* Optimized database interaction

= 0.3 =
* Added options page
* Ability to search within shortcodes
* Google Analytics integration
* Can search by multiple categories (OR)
* Option to put scripts in header or footer
* Will throw an error if search results shortcode isn't present on page
* Loading animations
* Fixed bug where widget wouldn't display on home page
* Misc. performance tweaks

= 0.2 =
* First public release

== Upgrade Notice ==

= 1.2 =
Notice: this upgrade adds a lot of new options to the options page. Please review them and update your settings before using the search bar.
