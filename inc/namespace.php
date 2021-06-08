<?php
/**
 * WordPress integration with AMF.
 */

declare( strict_types=1 );

namespace AMFWordPress;

const SETTINGS_PAGE = 'media';
const SETTINGS_SECTION = 'amf_wordpress';
const URL_SETTING = 'amf_wordpress_url';

/**
 * Bootstrap function.
 */
function bootstrap(): void {

	add_action( 'plugins_loaded', __NAMESPACE__ . '\\register_settings' );
	add_action( 'admin_init', __NAMESPACE__ . '\\register_settings_ui' );

	add_filter( 'amf/provider', __NAMESPACE__ . '\\get_provider' );
}

/**
 * Get the provider for AMF.
 *
 * @return Provider Provider instance.
 */
function get_provider(): Provider {

	return new Provider(
		new Factory()
	);
}

/**
 * Register the settings.
 */
function register_settings(): void {

	register_setting( SETTINGS_PAGE, URL_SETTING, [
		'type'              => 'string',
		'description'       => __( 'URL of the WordPress site to use as media source.', 'amf-wordpress' ),
		'sanitize_callback' => __NAMESPACE__ . '\\sanitize_wordpress_url',
	] );
}

/**
 * Register the UI for the settings.
 */
function register_settings_ui(): void {

	if ( defined( 'AMF_WORDPRESS_URL' ) ) {
		// Skip the UI.
		return;
	}

	add_settings_section(
		SETTINGS_SECTION,
		__( 'Asset Manager Framework (WordPress)', 'amf-wordpress' ),
		__NAMESPACE__ . '\\render_settings_description',
		SETTINGS_PAGE
	);

	add_settings_field(
		URL_SETTING,
		__( 'WordPress URL', 'amf-wordpress' ),
		__NAMESPACE__ . '\\render_field_ui',
		SETTINGS_PAGE,
		SETTINGS_SECTION,
		[
			'label_for' => URL_SETTING,
		]
	);
}

/**
 * Render the description for the settings section.
 */
function render_settings_description(): void {

	?>
	<p>
		<?php _e( 'The Asset Manager Framework (AMF) WordPress provider lets you use another WordPress site as source for your media library.', 'amf-wordpress' ); ?>
	</p>
	<?php
}

/**
 * Render the settings field UI.
 */
function render_field_ui(): void {

	$value = get_option( URL_SETTING, '' );

	?>
	<input
		class="regular-text code"
		id="<?php echo esc_attr( URL_SETTING ); ?>"
		name="<?php echo esc_attr( URL_SETTING ); ?>"
		type="url"
		value="<?php echo esc_attr( $value ); ?>"
	/>
	<p class="description">
		<?php _e( 'URL of the WordPress site to use as media source.', 'amf-wordpress' ); ?>
	</p>
	<?php
}

/**
 * Ensure the user input is only the URL.
 *
 * @param string $input User input to sanitize.
 *
 * @return string URL.
 */
function sanitize_wordpress_url( string $input ): string {

	$url = preg_replace( '~/wp-json/wp/v2([/?].*)?$~', '', $input );
	$url = untrailingslashit( $url );

	return $url;
}

/**
 * Get the media endpoint.
 *
 * @return string Endpoint.
 */
function get_endpoint(): string {

	$url = defined( 'AMF_WORDPRESS_URL' ) ? AMF_WORDPRESS_URL : get_option( URL_SETTING, '' );
	$url = sanitize_wordpress_url( $url );

	if ( empty( $url ) ) {
		$url = home_url();
	}

	$endpoint = "{$url}/wp-json/wp/v2/media";

	return $endpoint;
}


/**
 * Determines if the current site using a local override.
 *
 * @return boolean
 */
function is_using_local_site(): bool {

	return defined( 'AMF_WORDPRESS_SITE_ID' ) && AMF_WORDPRESS_SITE_ID;
}

/**
 * Returns current local site id
 *
 * @return string
 */
function get_local_site_id() {

	if( is_using_local_site() ) {
		return apply_filters( 'amf/local_site_id' , AMF_WORDPRESS_SITE_ID);
	}

	return apply_filters( 'amf/local_site_id' , null);
}