=== Certant Knowledgebase ===
Tags: knowledge base, ai, rest api, taxonomy, documentation
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Choose in WordPress which posts, pages and files Certant imports into your knowledgebase.

== Description ==

Certant Knowledgebase adds one label, **Knowledgebase**, to posts, pages and media. Tick a collection in the editor and that content is imported into your Certant knowledgebase on the next sync. Untick it and it is removed.

* Works like categories: collections with sub-collections, ticked in the editor.
* On posts, pages and media library files (PDF, Word, Excel, PowerPoint, text).
* Nothing on the public site: no archive pages, URLs or menus.
* No settings, no outbound requests, no tracking. One PHP file.

Certant reads the labels through the WordPress REST API. In Certant, connect the site, pick the collections and choose how often to sync.

== Installation ==

1. Download `certant-knowledgebase.zip` from https://github.com/certant-ai/certant-knowledgebase-plugin/releases/latest
2. In WordPress, go to Plugins > Add New Plugin > Upload Plugin, choose the zip and click Install Now.
3. Click Activate.

With WP-CLI: `wp plugin install https://github.com/certant-ai/certant-knowledgebase-plugin/releases/latest/download/certant-knowledgebase.zip --activate`

== Frequently Asked Questions ==

= Can visitors see the labels? =

Not on the site. Like categories and tags, collection names and which public content is in them can be read through the REST API, which is how Certant reads them. Private and draft content stays private.

= Can I use it with a custom post type? =

Yes, with the `certant_kb_post_types` filter. See the README on GitHub.

= What happens if I delete the plugin? =

Your collections stay in the database, and reinstalling brings them back.

== Changelog ==

= 1.0.0 =
* First release: the Knowledgebase taxonomy on posts, pages and media, shared through the REST API.
