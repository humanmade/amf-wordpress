<?php
/**
 * WordPress integration with AMF.
 */

declare( strict_types=1 );

namespace AMFWordPress;

use AssetManagerFramework\ProviderRegistry;

const SETTINGS_PAGE = 'media';
const SETTINGS_SECTION = 'amf_wordpress';
const URL_SETTING = 'amf_wordpress_url';
const TOKEN_SETTING = 'amf_wordpress_token';

/**
 * Bootstrap function.
 */
function bootstrap(): void {

	add_action( 'plugins_loaded', __NAMESPACE__ . '\\register_settings' );
	add_action( 'admin_init', __NAMESPACE__ . '\\register_settings_ui' );

	add_action( 'amf/register_providers', __NAMESPACE__ . '\\register_provider' );
}

/**
 * Register the provider with AMF.
 *
 * @param ProviderRegistry $provider_registry Provider registry instance.
 */
function register_provider( ProviderRegistry $provider_registry ): void {

	$provider_registry->register( new Provider( new Factory() ) );
}

/**
 * Register the settings.
 */
function register_settings(): void {
	if ( defined( 'AMF_WORDPRESS_URL' ) ) {
		// Skip the UI.
		return;
	}

	register_setting( SETTINGS_PAGE, URL_SETTING, [
		'type'              => 'string',
		'description'       => __( 'URL of the WordPress site to use as media source.', 'amf-wordpress' ),
		'sanitize_callback' => __NAMESPACE__ . '\\sanitize_wordpress_url',
	] );

	register_setting( SETTINGS_PAGE, TOKEN_SETTING, [
		'type'              => 'string',
		'description'       => __( 'Application password for the WordPress site to use as media source. .', 'amf-wordpress' ),
		'sanitize_callback' => 'sanitize_text_field',
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
			'description' => __( 'URL of the WordPress site to use as media source.', 'amf-wordpress' ),
			'label_for' => URL_SETTING,
		]
	);

	add_settings_field(
		TOKEN_SETTING,
		__( 'Application Password', 'amf-wordpress' ),
		__NAMESPACE__ . '\\render_field_ui',
		SETTINGS_PAGE,
		SETTINGS_SECTION,
		[
			'description' => __( 'Application password for the WordPress site to use as media source. .', 'amf-wordpress' ),
			'label_for' => TOKEN_SETTING,
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
 *
 * @param array $args Field callback args.
 */
function render_field_ui( array $args ): void {

	$name = $args['label_for'];
	$value = get_option( $name, '' );

	?>
	<input
		class="regular-text code"
		id="<?php echo esc_attr( $name ); ?>"
		name="<?php echo esc_attr( $name ); ?>"
		type="url"
		value="<?php echo esc_attr( $value ); ?>"
	/>
	<p class="description">
		<?php echo esc_html( $args['description'] ); ?>
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
 * Get the authentication token.
 *
 * @return string|null
 */
function get_auth_token() : ?string {

	$token = defined( 'AMF_WORDPRESS_TOKEN' ) ? AMF_WORDPRESS_TOKEN : get_option( TOKEN_SETTING, null );

	/**
	 * Filters the REST API authentication token.
	 *
	 * @param string $token The authentication token for the REST API authorization header.
	 */
	$token = apply_filters( 'amf_wordpress_token', $token );

	return ! empty( $token ) ? base64_encode( $token ) : null;
}
