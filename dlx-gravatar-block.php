<?php
/**
 * Plugin Name:       DLX Gravatar Block
 * Plugin URI:        https://dlxplugins.com/tutorials/
 * Description:       A sample Gravatar block for the block editor.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.2
 * Author:            DLX Plugins
 * Author URI:        https://dlxplugins.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package DLXGravatarBlock
 */

namespace DLXPlugins\DLXGravatarBlock;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', __NAMESPACE__ . '\register_block' );

/**
 * Register the block.
 */
function register_block() {
	register_block_type(
		plugin_dir_path( __FILE__ ) . 'build/blocks/gravatar-block/block.json',
		array(
			'render_callback' => __NAMESPACE__ . '\render_block',
		)
	);
}

/**
 * Render the block.
 *
 * @param array $attributes The block attributes.
 *
 * @return string The block output.
 */
function render_block( $attributes ) {
	$gravatar_hash = $attributes['gravatarHash'] ?? '';
	$gravatar_size = $attributes['gravatarSize'] ?? 96;
	$alignment     = $attributes['align'] ?? 'none';

	// Return if no hash is found.
	if ( empty( $gravatar_hash ) ) {
		return '';
	}

	// Get CSS classes.
	$classes = array(
		'wp-block-dlx-gravatar-block',
		'align' . $alignment,
	);

	// Get the avatar based on hash.
	$avatar = get_avatar(
		$gravatar_hash,
		$gravatar_size,
		'',
		'',
		array( 'class' => implode( ' ', $classes ) )
	);
	return $avatar;
}

// Enqueue the block editor JavaScript.
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\register_block_editor_js' );

/**
 * Enqueue the block editor JavaScript.
 */
function register_block_editor_js() {
	$deps = require plugin_dir_path( __FILE__ ) . 'build/index.asset.php';
	wp_register_script(
		'dlx-gravatar-block-editor',
		plugins_url( 'build/index.js', __FILE__ ),
		$deps['dependencies'],
		$deps['version'],
		true,
	);

	// Get avatar sizes.
	$avatar_sizes = rest_get_avatar_sizes();

	// Map to label/value pair.
	$avatar_sizes = array_map(
		function ( $size ) {
			return array(
				'label' => $size,
				'value' => $size,
			);
		},
		$avatar_sizes
	);

	// Add localized vars we'll need.
	wp_localize_script(
		'dlx-gravatar-block-editor',
		'dlxGravatarBlock',
		array(
			'apiUrl'      => rest_url( 'dlx-gravatar-block/v1/gravatar/' ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'avatarSizes' => $avatar_sizes,
		)
	);
}

// Enqueue the block editor CSS.
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\register_block_css' );

/**
 * Enqueue the block editor CSS.
 */
function register_block_css() {
	$deps = require plugin_dir_path( __FILE__ ) . 'build/index.asset.php';
	wp_enqueue_style(
		'dlx-gravatar-block-editor',
		plugins_url( 'build/index.css', __FILE__ ),
		array(),
		$deps['version'],
		'all'
	);
}

/**
 * Start REST.
 */
add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_fields' );

/**
 * Register REST fields.
 */
function register_rest_fields() {
	register_rest_route(
		'dlx-gravatar-block/v1',
		'/gravatar/(?P<id>.+)',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\rest_get_gravatar',
			'sanitize_callback'   => 'sanitize_text_field',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		),
	);
}

/**
 * Get the Gravatar.
 *
 * @param \WP_REST_Request $request The REST request.
 *
 * @return \WP_REST_Response The REST response.
 */
function rest_get_gravatar( $request ) {
	$maybe_id_or_email = sanitize_text_field( urldecode( $request->get_param( 'id' ) ) ); //Accepts a user ID, Gravatar MD5 hash, username, user email. Assumes urlencoded.

	// Call local plugin function get_user to get a user object.
	$maybe_user = get_user( $maybe_id_or_email );

	// Get avatar URLs.
	$avatar_urls = rest_get_avatar_urls( $maybe_id_or_email );

	// Format email into hash.
	$email_hash = false;
	if ( $maybe_user ) {
		$maybe_id_or_email = $maybe_user->user_email;
		$email_hash        = hash( 'sha256', strtolower( $maybe_id_or_email ) ) . '@md5.gravatar.com';
	} elseif ( is_email( $maybe_id_or_email ) ) {
		// Make sure email isn't a gravatar email.
		if ( strpos( $maybe_id_or_email, '@md5.gravatar.com' ) === false ) {
			$email_hash = hash( 'sha256', strtolower( $maybe_id_or_email ) ) . '@md5.gravatar.com';
		}
	}

	if ( ! empty( $avatar_urls ) ) {
		$return = array(
			'avatarUrls' => $avatar_urls,
			'emailHash'  => $email_hash,
		);
		return rest_ensure_response( $return );
	}

	return rest_ensure_response(
		new \WP_Error(
			'no_avatar',
			__( 'No avatar found.', 'dlx-gravatar-block' ),
		)
	);
}

/**
 * Get the user.
 *
 * @param int|string|object $id_or_email The user ID, email address, or comment object.
 *
 * @return false|\WP_User The user object.
 */
function get_user( $id_or_email ) {
	$id_or_email = sanitize_text_field( $id_or_email );

	$user = false;
	// Get user data.
	if ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'id', (int) $id_or_email );
	} elseif ( is_object( $id_or_email ) ) {
		$comment = $id_or_email;
		if ( empty( $comment->user_id ) ) {
			$user = get_user_by( 'id', $comment->user_id );
		} else {
			$user = get_user_by( 'email', $comment->comment_author_email );
		}
	} elseif ( is_string( $id_or_email ) ) {
		if ( is_email( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
		} else {
			$user = get_user_by( 'login', $id_or_email );
		}
	}
	return $user;
}
