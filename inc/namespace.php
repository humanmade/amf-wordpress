<?php

namespace AMFWPMultisite;

/**
 * Bootstrap function.
 */
function bootstrap() : void {
    add_filter( 'amf/provider_class', __NAMESPACE__ . '\\get_provider' );
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

