<?php
/**
 * Plugin Name:       Certant Knowledgebase
 * Plugin URI:        https://github.com/certant-ai/certant-wordpress-kb-plugin
 * Description:       Adds a Knowledgebase label to posts, pages and media, so you choose in WordPress what Certant imports into your knowledgebase.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Certant
 * Author URI:        https://certant.ai
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/certant-ai/certant-wordpress-kb-plugin
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
 * 2. Lets signed-in users who can file content (Contributor and up) read it
 *    through the WordPress REST API. That is how the block editor shows it,
 *    and how Certant reads it, signed in with an application password.
 *    Visitors who are not signed in see no collections and cannot filter by
 *    them. A site can make them public with the `certant_kb_rest_public`
 *    filter.
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
			// In the REST API (the editor and Certant use it); private, see below.
			'show_in_rest'      => true,
			'rest_base'         => CERTANT_KB_TAXONOMY,
		)
	);
}
add_action( 'init', 'certant_kb_register_taxonomy' );

/**
 * Whether the current request may see collections over the REST API.
 *
 * Yes for users who can file content in them (the taxonomy's assign_terms
 * capability: Contributor and up). Return true from the
 * `certant_kb_rest_public` filter to let anyone read them.
 *
 * @return bool
 */
function certant_kb_rest_allowed() {
	if ( apply_filters( 'certant_kb_rest_public', false ) ) {
		return true;
	}
	$taxonomy = get_taxonomy( CERTANT_KB_TAXONOMY );
	return $taxonomy && current_user_can( $taxonomy->cap->assign_terms );
}

/**
 * Refuses REST requests that would reveal collections to anyone else: the
 * collections themselves, and filtering posts, pages or media by them.
 *
 * @param mixed           $result  Response to replace the request with, or null.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request The request.
 * @return mixed
 */
function certant_kb_guard_rest_request( $result, $server, $request ) {
	$reveals = preg_match( '#^/wp/v2/' . CERTANT_KB_TAXONOMY . '(/|$)#', $request->get_route() )
		|| null !== $request->get_param( CERTANT_KB_TAXONOMY )
		|| null !== $request->get_param( CERTANT_KB_TAXONOMY . '_exclude' );
	if ( null === $result && $reveals && ! certant_kb_rest_allowed() ) {
		return new WP_Error(
			'certant_kb_forbidden',
			__( 'Knowledgebase collections are only available to signed-in users who can edit content.', 'certant-knowledgebase' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
	return $result;
}
add_filter( 'rest_pre_dispatch', 'certant_kb_guard_rest_request', 10, 3 );

/**
 * Leaves the collections out of post, page and media responses for anyone
 * who may not see them.
 *
 * @param WP_REST_Response $response The response.
 * @return WP_REST_Response
 */
function certant_kb_hide_rest_field( $response ) {
	if ( isset( $response->data[ CERTANT_KB_TAXONOMY ] ) && ! certant_kb_rest_allowed() ) {
		unset( $response->data[ CERTANT_KB_TAXONOMY ] );
	}
	return $response;
}

/**
 * Hooks the field filter onto every content type the taxonomy is on.
 */
function certant_kb_hide_rest_fields() {
	foreach ( certant_kb_post_types() as $post_type ) {
		add_filter( "rest_prepare_{$post_type}", 'certant_kb_hide_rest_field' );
	}
}
add_action( 'rest_api_init', 'certant_kb_hide_rest_fields' );

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
