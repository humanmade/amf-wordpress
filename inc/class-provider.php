<?php

namespace AMFWPMultisite;

use WP_REST_Request;
use AssetManagerFramework\{
	Audio,
	Document,
	Image,
	Media,
	MediaList,
	Video
};
use AssetManagerFramework\Provider as BaseProvider;
use stdClass;

class Provider extends BaseProvider {
    /**
     * Base URL for the Wordpress API.
     */

    /**
     * Parse input query args into an Unsplash query.
     *
     * @param array $input
     * @return array
     */
    protected function parse_args( array $input ) : array {
        $query = [
            'page'     => 1,
            'per_page' => 10,
            '_embed'   => 1,
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
            $query['search'] = $input['s'];

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
        $args = $this->parse_args( $args );

        $response = $this->fetch( $args );
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
        $args = $this->parse_args( $args );

        $response = $this->fetch( $args );
        $items = [];

        foreach ( $response['data'] as $image ) {
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
    protected function prepare_image_for_response( object $media ) : Media {

        $media_type = explode( '/', $media->mime_type );

        switch ( $media_type[0] ) {
            case 'image':
            case 'icon':
                $item = new Image( $media->id, $media->mime_type );
                $item->set_width( $media->media_details->width );
                $item->set_height( $media->media_details->height );
                $item->set_alt( $media->alt_text ?? '' );
                // Generate sizes.
                $sizes = $this->get_image_sizes( $media );
                $item->set_sizes( $sizes );
                break;

            case 'video':
                $item = new Video( $media->id, $media->mime_type );
                if ( ! empty( $media->_embedded->{'wp:featuredmedia'} ) && isset( $media->_embedded->{'wp:featuredmedia'} ) ) {
                    $thumb = $media->_embedded->{'wp:featuredmedia'}[0]->source_url;
                    $item->set_image( $media->_embedded->{'wp:featuredmedia'}[0]->source_url );
                }
                break;

            case 'audio':
                $item = new Audio( $media->id, $media->mime_type );
                if ( ! empty( $media->_embedded->{'wp:featuredmedia'} ) && isset( $media->_embedded->{'wp:featuredmedia'} ) ) {
                    $thumb = $media->_embedded->{'wp:featuredmedia'}[0]->source_url;
                    $item->set_image( $media->_embedded->{'wp:featuredmedia'}[0]->source_url );
                }
                break;

            case 'application':
                $item = new Document( $media->id, $media->mime_type );
                break;

            default:
                $item = new Media( $media->id, $media->mime_type );

                break;
        }

        // Map data directly.
        $item->set_url( $media->source_url );
        $item->set_filename( $media->source_url );
        $item->set_name( $media->id );
        $item->set_link( $media->link );
        $item->set_title(
            $media->title->rendered ?? $media->caption->rendered ?? ''
        );


        // Generate attribution.
        $item->author = $media->author;
        $item->set_caption( $media->caption->rendered );
        $item->set_date( strtotime( $media->date ) );
        $item->set_modified(  strtotime( $media->modified ) );
        $item->set_file_size( $this->getfilesize( $media->source_url ) );


        // Add additional metadata for later.
        $item->add_amf_meta( 'media_id', $media->id );

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
    protected static function fetch( array $args = [] ) {
        $endpoint = get_media_domain();
        $url = add_query_arg( urlencode_deep( $args ), $endpoint );

        $request = wp_remote_get( $url );

        if ( is_wp_error( $request ) ) {
            return null;
        }
        $data = json_decode( wp_remote_retrieve_body( $request ) );

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
