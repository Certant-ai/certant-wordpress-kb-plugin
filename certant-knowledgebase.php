<?php
/**
 * Plugin Name:       Certant Knowledgebase
 * Plugin URI:        https://github.com/certant-ai/certant-knowledgebase-plugin
 * Description:       Adds a Knowledgebase label to posts, pages and media, so you choose in WordPress what Certant imports into your knowledgebase.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Certant
 * Author URI:        https://certant.ai
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/certant-ai/certant-knowledgebase-plugin
 * Text Domain:       certant-knowledgebase
 *
 * @package CertantKnowledgebase
 */

/*
 * What this plugin does, all of it:
 *
 * 1. Registers one taxonomy, "Knowledgebase" (certant_kb), on posts, pages and
 *    media. It is hierarchical, so it works like categories: collections
 *    with sub-collections, ticked in the editor.
 * 2. Shares it through the WordPress REST API, which is how the Certant
 *    connector reads which content to import.
 * 3. Creates a first "Knowledgebase" collection on activation.
 *
 * It keeps the taxonomy off the public site: no archive pages, no URLs, no
 * menus. It stores nothing else, makes no outbound requests and has no
 * settings. Deactivating or deleting it leaves your collections in the
 * database, so reinstalling brings them back.
 */

defined( 'ABSPATH' ) || exit;

/** The taxonomy's name, also its REST base and its admin query variable. */
const CERTANT_KB_TAXONOMY = 'certant_kb';

/**
 * Content types that can be filed in the knowledgebase.
 *
 * Add a custom post type (for example a docs plugin's) with the
 * `certant_kb_post_types` filter.
 *
 * @return string[] Post type names.
 */
function certant_kb_post_types() {
	return apply_filters( 'certant_kb_post_types', array( 'post', 'page', 'attachment' ) );
}

/**
 * Registers the Knowledgebase taxonomy.
 */
function certant_kb_register_taxonomy() {
	register_taxonomy(
		CERTANT_KB_TAXONOMY,
		certant_kb_post_types(),
		array(
			'labels'            => array(
				'name'              => __( 'Knowledgebase', 'certant-knowledgebase' ),
				'singular_name'     => __( 'Knowledgebase collection', 'certant-knowledgebase' ),
				'menu_name'         => __( 'Knowledgebase', 'certant-knowledgebase' ),
				'all_items'         => __( 'All collections', 'certant-knowledgebase' ),
				'edit_item'         => __( 'Edit collection', 'certant-knowledgebase' ),
				'view_item'         => __( 'View collection', 'certant-knowledgebase' ),
				'update_item'       => __( 'Update collection', 'certant-knowledgebase' ),
				'add_new_item'      => __( 'Add new collection', 'certant-knowledgebase' ),
				'new_item_name'     => __( 'New collection name', 'certant-knowledgebase' ),
				'parent_item'       => __( 'Parent collection', 'certant-knowledgebase' ),
				'parent_item_colon' => __( 'Parent collection:', 'certant-knowledgebase' ),
				'search_items'      => __( 'Search collections', 'certant-knowledgebase' ),
				'not_found'         => __( 'No collections found.', 'certant-knowledgebase' ),
				'back_to_items'     => __( '&larr; Go to collections', 'certant-knowledgebase' ),
			),
			'description'       => __( 'Content filed here is imported into your Certant knowledgebase.', 'certant-knowledgebase' ),
			'hierarchical'      => true,
			// Not on the public site: no archive pages, URLs or menus.
			'public'            => false,
			'rewrite'           => false,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			// In the admin: the editor panel, list columns and Posts/Pages/Media menus.
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => CERTANT_KB_TAXONOMY,
			// In the REST API, which is how Certant reads it.
			'show_in_rest'      => true,
			'rest_base'         => CERTANT_KB_TAXONOMY,
		)
	);
}
add_action( 'init', 'certant_kb_register_taxonomy' );

/**
 * On activation, creates a first collection so there is something to tick.
 */
function certant_kb_activate() {
	certant_kb_register_taxonomy();
	if ( ! term_exists( 'knowledgebase', CERTANT_KB_TAXONOMY ) ) {
		wp_insert_term(
			__( 'Knowledgebase', 'certant-knowledgebase' ),
			CERTANT_KB_TAXONOMY,
			array( 'slug' => 'knowledgebase' )
		);
	}
}
register_activation_hook( __FILE__, 'certant_kb_activate' );

/**
 * Adds a "Collections" link beside Deactivate on the Plugins screen.
 *
 * @param string[] $links Action links.
 * @return string[] Action links.
 */
function certant_kb_action_links( $links ) {
	$url = admin_url( 'edit-tags.php?taxonomy=' . CERTANT_KB_TAXONOMY . '&post_type=page' );
	array_unshift(
		$links,
		sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Collections', 'certant-knowledgebase' ) )
	);
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'certant_kb_action_links' );
