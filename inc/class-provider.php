<?php
/**
 * AMF WordPress provider implementation.
 */

declare( strict_types=1 );

namespace AMFWordPress;

use AssetManagerFramework\FileUpload;
use AssetManagerFramework\Media;
use AssetManagerFramework\MediaList;
use AssetManagerFramework\MediaResponse;
use AssetManagerFramework\Provider as BaseProvider;
use Exception;

/**
 * AMF WordPress provider implementation.
 *
 * @package AMFWordPress
 */
class Provider extends BaseProvider {

	/**
	 * AMF media item factory.
	 *
	 * @var Factory
	 */
	private $factory;

	/**
	 * Provider constructor.
	 *
	 * @param Factory $factory AMF media item factory.
	 */
	public function __construct( Factory $factory ) {

		$this->factory = $factory;
	}

	/**
	 * Return the provider ID.
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'wordpress';
	}

	/**
	 * Return the provider name.
	 *
	 * @return string
	 */
	public function get_name(): string {

		return (string) apply_filters( 'amf/wordpress/provider_name', __( 'External WordPress Media', 'amf-wordpress' ) );
	}

	/**
	 * Retrieve the items for a query.
	 *
	 * @param array $args Query args from the media library.
	 *
	 * @return MediaResponse Found items.
	 *
	 * @throws Exception If the REST API response could not be decoded.
	 */
	protected function request( array $args ): MediaResponse {

		$args = $this->parse_args( $args );

		$url = get_endpoint();
		$url = add_query_arg( $args, $url );

		$response = $this->remote_request( $url, [
			'headers' => [
				'Accept-Encoding' => 'gzip, deflate',
				'Connection'      => 'Keep-Alive',
				'Content-Type'    => 'application/json',
				'Keep-Alive'      => 30,
			],
			'timeout' => 30,
		] );

		$data = json_decode( $response->get_data() );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( sprintf(
				/* translators: %s: Error message */
				__( 'Media error: %s', 'amf-wordpress' ),
				json_last_error_msg()
			) );
		}

		// Fall back to 40 as this is the default value for media library requests.
		$per_page = absint( $args['per_page'] ?? 40 );

		if ( ! is_array( $data ) || ! $data ) {
			return new MediaResponse(
				new MediaList(),
				0,
				$per_page
			);
		}

		$total = absint( $response->get_headers()['x-wp-total'] ?? 0 );
		$items = array_map( [ $this->factory, 'create' ], $data );

		return new MediaResponse(
			new MediaList( ...$items ),
			$total,
			$per_page
		);
	}

	/**
	 * Parse the given input query arguments into the according REST query arguments.
	 *
	 * @param array $args Input query arguments.
	 *
	 * @return array REST query arguments.
	 */
	private function parse_args( array $args ): array {

		$query = [];

		if ( isset( $args['paged'] ) ) {
			$query['page'] = absint( $args['paged'] );
		}

		if ( isset( $args['posts_per_page'] ) ) {
			// Check if 0 or -1 has been passed and reset to default.
			if ( intval( $args['posts_per_page'] ) <= 0 ) {
				$args['posts_per_page'] = 40;
			}
			$query['per_page'] = absint( $args['posts_per_page'] );
		}

		if ( isset( $args['order'] ) ) {
			$query['order'] = strtolower( $args['order'] );
		}

		if ( isset( $args['s'] ) ) {
			$query['search'] = $args['s'];

			// Override to sort by relevance.
			$query['order_by'] = 'relevance';
		}

		if ( isset( $args['post_mime_type'] ) ) {
			// Depending on the context of the request (e.g., Media Library page, Image block, Media and Text block),
			// the post_mime_type argument may be specified as a string or an array of strings. If a string, it may
			// be a single type only, or a comma-separated list of types.
			$mime_type = (array) $args['post_mime_type'];

			if ( count( $mime_type ) === 1 ) {
				$mime_type = reset( $mime_type );
				// Use media type as it matches the query arg for non-application types.
				if ( in_array( $mime_type, [ 'image', 'video', 'audio' ], true ) ) {
					$query['media_type'] = $mime_type;
				} else {
					$query['media_type'] = 'application';
				}
			} else {
				// TODO: Maybe request media items of any type, and then filter?
			}
		}

		// Embed author and featured media data.
		$query['_embed'] = 1;

		return $query;
	}

	/**
	 * Allow asset creation.
	 *
	 * @return boolean
	 */
	public function supports_asset_create() : bool {
		return true;
	}

	/**
	 * Allow updating assets.
	 *
	 * @return boolean
	 */
	public function supports_asset_update() : bool {
		return false;
	}

	/**
	 * Allow deleting assets.
	 *
	 * @return boolean
	 */
	public function supports_asset_delete() : bool {
		return false;
	}

	/**
	 * Handle uploading the file via the REST API.
	 *
	 * @param FileUpload $file The uploaded file object.
	 * @return Media
	 * @throws Exception If there was an error uploading.
	 */
	public function upload( FileUpload $file ) : Media {

		$url = get_endpoint();

		/**
		 * Filters the args used to POST an upload to the WP REST API.
		 *
		 * @param array $args The remote request args.
		 * @param FileUpload $file The current AMF file upload object.
		 */
		$args = apply_filters( 'amf_wordpress_upload_args', [
			'method' => 'POST',
			'headers' => [
				'content-disposition' => sprintf( 'attachment; filename="%s"', $file->name ),
				'content-type' => $file->type,
				'authorization' => sprintf( 'Basic %s', get_auth_token() ),
			],
			'body' => file_get_contents( $file->tmp_name ),
			'timeout' => 3600,
		], $file );

		$response = $this->remote_request( $url, $args );
		$response = json_decode( $response );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( sprintf(
				/* translators: %s: Error message */
				__( 'Media error: %s', 'amf-wordpress' ),
				json_last_error_msg()
			) );
		}

		return $this->factory->create( $response );
	}
}
