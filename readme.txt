=== WP Ultimate Search ===
Contributors: sekatsim, mindshare
Donate link: http://mind.sh/are/donate/
Tags: search, ajax, metadata, autocomplete, jquery
Requires at least: 3.4.1
Tested up to: 3.6
Stable tag: 1.2
 
Advanced faceted auto completing AJAX search and filter utility.

== Description ==

WP Ultimate Search: a highly customizable WordPress search alternative with the ability to autocomplete [faceted search queries](http://en.wikipedia.org/wiki/Faceted_search).

Try a [demo](http://ultimatesearch.mindsharelabs.com/).

<h4>Features</h4>

* Searches post title and body content
* Can search by multiple keywords, and by full phrases
* Highlights search terms in results
* Option to send search queries as events to your Google Analytics account
* Facets by post category
* Can search in multiple categories (OR search)
* Category options are dynamically generated and autocompleted as you type
* Attractive and lightweight interface based on jQuery, Backbone.js, and the VisualSearch.js library
* Customizable results template using standard WordPress functions

Premium version now supports the ability to search through an unlimited number of user-specified taxonomies and meta fields (including data contained in Advanced Custom Fields)

== Installation ==

1. Upload the `wp-ultimate-search` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add a shortcode to a post, use the template tag in your theme, or use the sidebar widget.

For additional information, [visit our website](http://mindsharelabs.com/)

== Frequently Asked Questions ==

= How do I customize the search results template? =

When a search is executed, the plugin first looks in your current theme directory for the file wpus-results-template.php. If no file is found, it falls back to the default results template, located in /wp-ultimate-search/views/wpus-results-template.php.

To customize the template, first copy the wpus-results-template.php file into your theme directory. The code within this file is a standard WordPress loop, which you can modify in any way you choose. To learn more about WordPress loops, see the codex.

= How do I stop my the viewport from zooming in when a user touches the search box on a mobile device? =

If you have a mobile website and you want to disable autozooming on input fields (like the search bar), add the following code to your header.php file:

`<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" />`

= More Info =

Help documents and support forums are available at [Mindshare Labs](http://mindsharelabs.com/).

== Screenshots ==

1. Search bar with results.

`/tags/1.0/screenshot-1.jpg`

2. Settings screen showing taxonomy options.

`/tags/1.0/screenshot-2.jpg`

3. Also compatible with touch devices.

`/tags/1.0/screenshot-3.jpg`

== Changelog ==

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

== To Do ==

* Caching of meta data
* Sortable results