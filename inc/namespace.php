<?php

namespace AMFWPMultisite;

/**
 * Bootstrap function.
 */
function bootstrap() : void {
    add_filter( 'amf/provider_class', __NAMESPACE__ . '\\get_provider' );
    add_action( 'plugins_loaded', __NAMESPACE__ . '\\register_key_setting' );
    add_action( 'admin_init', __NAMESPACE__ . '\\register_settings_ui' );
    // add_filter( 'amfwpmu_endpoint', __NAMESPACE__ . '\\media_endpoint', 10, 3 );

}

/**
 * Get the provider for AMF.
 *
 * @return string
 */
function get_provider() : string {
    require_once __DIR__ . '/class-provider.php';

    return Provider::class;
}


/**
 * Register the API key setting.
 */
function register_key_setting() : void {
	register_setting( 'media', 'amfwpmu_domain', [
		'type' => 'string',
		'description' => 'Wordpress Blog for Media',
		'default' => get_site_url(),
	] );
}
// Media endpoint filter callback function.
function get_media_endpoint( $string ) {

    $string = apply_filters( 'amfwpmu_endpoint', '/wp-json/wp/v2/media/' );
    // (maybe) modify $string.
    return $string;
}


/**
 * Get the API key.
 */
function get_media_domain() : ?string {
    $domain = '';

	if ( defined( 'AMFWPMU_DOMAIN' ) ) {
		$domain = AMFWPMU_DOMAIN;
	} else {
        $domain = get_option( 'amfwpmu_domain', get_site_url() );
    }

    $url = $domain .  '/wp-json/wp/v2/media/';

	return $url;
}

/**
 * Register the UI for the settings.
 */
function register_settings_ui() : void {
	if ( defined( 'AMFWPMU_DOMAIN' ) ) {
		// Skip the UI.
		return;
	}

	add_settings_section(
		'amfwpmultisite',
		'AMF WPMultisite',
		__NAMESPACE__ . '\\render_settings_description',
		'media'
	);

	add_settings_field(
		'amfwpmu_domain',
		'Media Domain',
		__NAMESPACE__ . '\\render_field_ui',
		'media',
		'amfwpmultisite',
		[
			'label_for' => 'amfwpmu_domain',
		]
	);
}

/**
 * Render the description for the settings section.
 */
function render_settings_description() : void {
	echo '<p>';
	printf(
		'Set the default blog url to query for the media library. It must be a WordPress blog.'
	);
	echo '</p>';
}

/**
 * Render the field input.
 */
function render_field_ui() : void {
	$value = get_option( 'amfwpmu_domain', '' );
	printf(
		'<input
			class="regular-text code"
			id="amfwpmu_domain"
			name="amfwpmu_domain"
			type="text"
			value="%s"
		/>',
		esc_attr( $value )
	);
}