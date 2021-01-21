<?php

namespace AMFWPMultisite;

use WP_REST_Request;
use AssetManagerFramework\Image;
use AssetManagerFramework\MediaList;
use AssetManagerFramework\Provider as BaseProvider;
use stdClass;

class Provider extends BaseProvider {
    /**
     * Base URL for the Wordpress API.
     */
    const BASE_URL = 'https://hm-playbook.altis.dev/wp-json/wp/v2';

    /**
     * Parse input query args into an Unsplash query.
     *
     * @param array $input
     * @return array
     */
    protected function parse_args( array $input ) : array {
        $query = [
            'page' => 1,
            'per_page' => 10,
        ];

        if ( isset( $input['posts_per_page'] ) ) {
            $query['per_page'] = absint( $input['posts_per_page'] );
        }
        if ( isset( $input['paged'] ) ) {
            $query['page'] = absint( $input['paged'] );
        }
        if ( ! empty( $input['orderby'] ) ) {
            $dir = strtolower( $input['order'] ?? 'desc' );
            switch ( $input['orderby'] ) {
                case 'date':
                    $query['order_by'] = $dir === 'desc' ? 'latest' : 'oldest';
                    break;
            }
        }
        if ( isset( $input['s'] ) ) {
            $query['query'] = $input['s'];

            // Override to sort by relevance. (Requires hack in search_images)
            $query['order_by'] = 'relevant';
        }

        return $query;
    }

    /**
     * Retrieve the images for a query.
     *
     * @param array $args Query args from the media library
     * @return MediaList Found images.
     */
    protected function request( array $args ) : MediaList {
        if ( ! empty( $args['s'] ) ) {
            return $this->search_images( $args );
        } else {
            return $this->request_images( $args );
        }
    }

    /**
     * Retrieve the images for a list query.
     *
     * @param array $args Query args from the media library
     * @return MediaList Found images.
     */
    protected function request_images( array $args ) : MediaList {
        $query = $this->parse_args( $args );

        $response = $this->fetch( '/media', $query );
        $items = $this->prepare_images( $response['data'] );

        return new MediaList( ...$items );
    }

    /**
     * Retrieve the images for a search query.
     *
     * @param array $args Query args from the media library
     * @return MediaList Found images.
     */
    protected function search_images( array $args ) : MediaList {
        $query = $this->parse_args( $args );

        $response = $this->fetch( '/media', $query );
        $items = [];
        $i = $query['page'] * $query['per_page'];

        foreach ( $response['data']->results as $image ) {
            $item = $this->prepare_image_for_response( $image );
            $items[] = $item;
        }

        return new MediaList( ...$items );
    }

    /**
     * Prepare a list of images for the response.
     *
     * @param array $images
     * @return array
     */
    protected function prepare_images( array $images ) : array {
        $items = [];

        foreach ( $images as $image ) {
            $item = $this->prepare_image_for_response( $image );
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Get the remote file's size without downloading full image.
     *
     * @param  string $path
     * @return int
     */
    protected function getfilesize( string $path ) : int {
        $head = array_change_key_case( get_headers( $path, 1 ) );
        $filesize = $head['content-length'] ?? 0;

        return $filesize;
    }

    /**
     * Prepare an image's data for the response.
     *
     * @param stdClass $image Raw data from the Wordpress API
     * @return Image Formatted image for use in AMF.
     */
    protected function prepare_image_for_response( object $image ) : Image {
        $item = new Image(
            $image->id,
            $image->mime_type
        );

        // Map data directly.
        $item->set_url( $image->source_url );
        $item->set_filename( $image->guid->rendered );
        $item->set_link( $image->link );
        $item->set_title(
            $image->title->rendered ?? $image->caption->rendered ?? ''
        );
        $item->set_width( $image->media_details->width );
        $item->set_height( $image->media_details->height );
        $item->set_alt( $image->alt_text ?? '' );

        // Generate attribution.
        $item->author = $image->author;
        $item->set_caption( $image->caption->rendered );
        $item->set_date( strtotime( $image->date ) );
        $item->set_modified(  strtotime( $image->modified ) );
        $item->set_file_size( $this->getfilesize( $image->source_url ) );
        // Generate sizes.
        $sizes = $this->get_image_sizes( $image );
        $item->set_sizes( $sizes );

        // Add additional metadata for later.
        $item->add_amf_meta( 'media_id', $image->id );

        return $item;
    }



    /**
     * Fetch an API endpoint.
     *
     * @param string $path API endpoint path (prefixed with /)
     * @param array $args Query arguments to add to URL.
     * @param array $options Other options to pass to WP HTTP.
     * @return array
     */
    protected static function fetch( string $path, array $args = [], array $options = [] ) {
        $url = static::BASE_URL . $path;
        $url = add_query_arg( urlencode_deep( $args ), $url );

        // $options = array_merge( $defaults, $options );

        $request = new WP_REST_Request( 'GET', '/wp/v2/media' );
        $request->set_query_params( [ 'per_page' => 30 ] );

        // Switch to media blog to make internal request.
        switch_to_blog( 1 ); // @TODO Get blog ID from settings.
        $response = rest_do_request( $request );
        $server = rest_get_server();
        $data = $server->response_to_data( $response, false );
        restore_current_blog();

        $json = wp_json_encode( $data, true );


        if ( is_wp_error( $response ) ) {
            return null;
        }

        $data = json_decode( $json );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return null;
        }

        return [
            'headers' => wp_remote_retrieve_headers( $request ),
            'data' => $data,
        ];
    }

    /**
     * Get size mapping from a given image.
     *
     * From the API documentation:
     *
     * - `full` returns the photo in jpg format with its maximum dimensions.
     *   For performance purposes, we don’t recommend using this as the photos
     *   will load slowly for your users.
     *
     * - `regular` returns the photo in jpg format with a width of 1080 pixels.
     *
     * - `small` returns the photo in jpg format with a width of 400 pixels.
     *
     * - `thumb` returns the photo in jpg format with a width of 200 pixels.
     *
     * - `raw` returns a base image URL with just the photo path and the ixid
     *   parameter for your API application. Use this to easily add additional
     *   image parameters to construct your own image URL.
     *
     * @param stdClass $image
     * @return array
     */
    protected static function get_image_sizes( stdClass $image ) : array {
        $registered_sizes = wp_get_registered_image_subsizes();
        $registered_sizes['full'] = [
            'width' => $image->media_details->width,
            'height' => $image->media_details->height,
            'crop' => false,
        ];
        if ( isset( $registered_sizes['medium'] ) ) {
            $registered_sizes['medium']['crop'] = true;
        }

        $orientation = $image->media_details->height > $image->media_details->width ? 'portrait' : 'landscape';
        $sizes = [];
        foreach ( $registered_sizes as $name => $size ) {
            $imgix_args = [
                'w' => $size['width'],
                'h' => $size['height'],
                'fit' => $size['crop'] ? 'crop' : 'max',
            ];
            $sizes[ $name ] = [
                'width' => $size['width'],
                'height' => $size['height'],
                'orientation' => $orientation,
                'url' => add_query_arg( urlencode_deep( $imgix_args ), $image->source_url ),
            ];
        }

        return $sizes;
    }

}
