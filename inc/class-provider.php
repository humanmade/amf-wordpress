<?php
/**
 * AMF WordPress provider implementation.
 */

declare( strict_types=1 );

namespace AMFWordPress;

use AssetManagerFramework\MediaList;
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
	 */
	public function __construct() {

		$this->factory = new Factory();
	}

	/**
	 * Retrieve the items for a query.
	 *
	 * @param array $args Query args from the media library.
	 *
	 * @return MediaList Found items.
	 *
	 * @throws Exception If the REST API response could not be decoded.
	 */
	protected function request( array $args ): MediaList {

		$args = $this->parse_args( $args );

		$url = get_endpoint();
		$url = add_query_arg( $args, $url );

		$response = self::remote_request( $url, [
			'headers' => [
				'Accept-Encoding' => 'gzip, deflate',
				'Connection'      => 'Keep-Alive',
				'Content-Type'    => 'application/json',
				'Keep-Alive'      => 30,
			],
			'timeout' => 30,
		] );
		$response = json_decode( $response );

		if ( json_last_error() ) {
			throw new Exception( sprintf(
				/* translators: %s: Error message */
				__( 'Error fetching media: %s', 'amf-wordpress' ),
				json_last_error_msg()
			) );
		}

		if ( ! is_array( $response ) || ! $response ) {
			return new MediaList();
		}

		$items = array_map( [ $this->factory, 'create' ], $response );

		return new MediaList( ...$items );
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
			$mime_type = $args['post_mime_type'];

			// Use media type as it matches the query arg for non-application types.
			if ( in_array( $mime_type, [ 'image', 'video', 'audio' ], true ) ) {
				$query['media_type'] = $mime_type;
			} else {
				$query['media_type'] = 'application';
			}
		}

		// Embed featured image data.
		$query['_embed'] = 1;

		return $query;
	}
}
