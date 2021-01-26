<?php
/**
 * WordPress REST Provider
 */

namespace AMFWordPress;

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
     * Parse input query args into REST query.
     *
     * @param array $input
     * @return array
     */
    protected function parse_args( array $input ) : array {
        $query = [
            'page'     => 1,
            'per_page' => 30,
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

            // Override to sort by relevance.
            $query['order_by'] = 'relevant';
        }

        return $query;
    }

    /**
     * Retrieve the items for a query.
     *
     * @param array $args Query args from the media library
     *
     * @return MediaList Found items.
     */
    protected function request( array $args ) : MediaList {
        $args = $this->parse_args( $args );

        $response = self::remote_request( get_media_domain(), $args );
        $response = json_decode( $response );

        $items = $this->prepare_items( $response );

        return new MediaList( ...$items );
    }

    /**
     * Prepare a list of media for the response.
     *
     * @param array $media Array of image objects
     *
     * @return array
     */
    protected function prepare_items( array $media ) : array {
        $items = [];

        foreach ( $media as $item ) {
            $item = $this->prepare_item_for_response( $item );
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Get the remote file's size without downloading full file.
     *
     * @param string $path Path to file.
     *
     * @return int
     */
    protected function getfilesize( string $path ) : int {
        $head = array_change_key_case( get_headers( $path, 1 ) );
        $filesize = $head['content-length'] ?? 0;

        return $filesize;
    }

    /**
     * Prepare a media item's data for the response.
     *
     * @param stdClass $media Raw data from the Wordpress API
     *
     * @return Image Formatted media item for use in AMF.
     */
    protected function prepare_item_for_response( object $media ) : Media {
        // Use mime_type instead of media_type as WP sets non-images to just 'file'.
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
                    $item->set_image( $media->_embedded->{'wp:featuredmedia'}[0]->source_url );
                }
                break;

            case 'audio':
                $item = new Audio( $media->id, $media->mime_type );
                if ( ! empty( $media->_embedded->{'wp:featuredmedia'} ) && isset( $media->_embedded->{'wp:featuredmedia'} ) ) {
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
     *
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
