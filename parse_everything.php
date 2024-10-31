<?php
/*
Plugin Name: Parse Everything
Plugin URI: https://
Description: The plugin allows you to parse info from another site and send to your email  when the info is changed
Version: 1.0
Author: ioannup
Author URI: https://www.upwork.com/freelancers/~0165d3dc4b2ffbbd7d
License: GPLv2
Requires at least: 5.0
Tested up to: 5.6
*/

require_once 'vendor/autoload.php';

if ( ! class_exists( 'ParseEverything' ) ) {
	class ParseEverything {

		public static function init() {
			self::include_pro();
			ParseInc\ParseCpt::init();
			ParseInc\ParseCrons::init();
			add_shortcode( 'parse_result', [ 'ParseEverything', 'shortcode' ] );
			add_action( 'publish_' . ParseInc\ParseCpt::POST_TYPE, [ 'ParseEverything', 'parse_after_publish'], 10, 2 );
		}

		public static function plugin_dir() {
			return trailingslashit( plugin_dir_path( __FILE__ ) );
		}

		public static function plugin_dir_url() {
			return trailingslashit( plugin_dir_url( __FILE__ ) );
		}

		private static function include_pro() {
			$pro_dir = self::plugin_dir() . 'pro/';
			if ( ! is_dir( $pro_dir ) ) {
				return;
			}

			$folders = scandir( $pro_dir );
			foreach ( $folders as $folder ) {
				$main_file = $pro_dir . $folder . '/' . $folder . '.php';
				if ( in_array( $folder, [ '.', '..', 'vendor' ], true ) ) {
					continue;
				} elseif ( is_dir( $pro_dir . $folder ) && file_exists( $main_file ) ) {
					require_once $main_file;
				}
			}
		}

		public static function parse_after_publish( $id, $post_obj ) {
			ParseInc\ParseCrons::run_cron( $id );
		}

		public static function shortcode( $atts ) {
			$atts = shortcode_atts( array(
				'id'  => '',
			), $atts );

			if ( empty( $atts['id'] ) || ParseInc\ParseCpt::POST_TYPE !== get_post_type( $atts['id'] ) ) {
				return '';
			}

			$data = get_post_meta( $atts['id'], 'parse_result', true );

			return !empty( $data['result'] ) ? $data['result'] : '';
		}

		public static function parse() {
			$post_data = filter_input( INPUT_POST, 'data' );
			$args = [];
			wp_parse_str( $post_data, $args );
			$default = [
				'url' => '',
				'not_html' => '',
				'selectors' => [],
				'attributes' => [],
				'request_args' => [ 'headers' => "X-Requested-With: XMLHttpRequest\n" ],
				'template_general' => '',
				'template_between' => '',
				'id' => '',
			];
			if ( ! empty( $args['post_ID'] ) ) {
				$args['id'] = $args['post_ID'];
			}

			$args = shortcode_atts( $default, $args );

			$result = self::run_parse( $args );

			return $result;
		}

		public static function run_parse( $args ) {
			$error = new WP_Error();
			$preg_match = preg_match_all( '/{RESULT_\d+}/', $args['template_general'], $result_matches );
			if ( false === strpos( $args['template_general'], '{ALL_RESULTS}' ) && 1 > $preg_match ) {
				$error->add( 'general_template_error', esc_html__( 'General tempate should consist {ALL_RESULTS} or {RESULT_[number]} placeholder' ) );
				return $error;
			}
			if ( empty( $args['url'] ) ) {
				$error->add( 'invalid_url', esc_html__( 'A wrong URL' ) );
				return $error;
			}
			if ( empty( $args['selectors'] ) || ! is_array( $args['selectors'] ) ) {
				$error->add( 'empty_selectors', esc_html__( 'Empty selectors' ) );
				return $error;
			}

			$request_args = array_filter( $args['request_args'], function($var){ return '' !== $var && 'true' !== $var; } );
			if ( isset( $request_args['method'] ) && 'POST' === $request_args['method'] ) {
				$request_args['method'] = 'POST';
			} else {
				$request_args['method'] = 'GET';
				unset( $request_args['body'] );
			}
			$request_args = array_map( function($var){
				if ( in_array( $var, array( 'true', 'false' ), true ) )
					$var = 'true' === $var;
				return $var;
			}, $request_args );

			// Prepare headers.
			if ( ! empty( $request_args['headers'] ) ) {
				$headers = explode( PHP_EOL, $request_args['headers'] );
				$array_headers = [];
				foreach ( $headers as $header ) {
					$header = trim( $header );

					list( $name, $value ) = explode( ':', $header, 2 );

					$name  = trim( $name );
					$value = trim( $value );
					if ( $name && $value ) {
						$array_headers[ $name ] = $value;
					}
					if ( 'accept-encoding' === $name ) {
						$array_headers[ $name ] = 'gzip';
					}
				}
				$request_args['headers'] = $array_headers;
			}
			if ( ! empty( $request_args['body'] ) ) {
				$body = explode( PHP_EOL, $request_args['body'] );
				$array_body = [];
				foreach ( $body as $item ) {
					$item = trim( $item );

					list( $name, $value ) = explode( ':', $item, 2 );

					$name  = trim( $name );
					$value = trim( $value );
					if ( $name && $value ) {
						$array_body[ $name ] = $value;
					}
				}
				$request_args['body'] = $array_body;
			}

			$request_args = apply_filters( 'pe_request_args', $request_args, $args['url'] );

			$response = wp_remote_request( $args['url'], $request_args );

			if ( ! $response ) {
				$error->add( 'remote_get_nothing', esc_html__( 'URL returned nothing' ) );
				return $error;
			} elseif ( is_wp_error( $response ) ) {
				return $response;
			}
			$html = wp_remote_retrieve_body( $response );
			$doc = phpQuery::newDocument( $html );
			$all_results = '';
			$results = [];
			foreach ( $args['selectors'] as $key => $selector ) {
				if ( 0 !== $key ) {
					if ( empty( $selector ) ) {
						continue;
					}
					$all_results .= ( !empty( $args['template_between'] ) ? $args['template_between'] : '' );
				}
				if ( empty( $selector ) ) {
					$selector = 'body';
				}
				if ( ! empty( $args['not_html'] ) ) {
					$original_result = $doc->text();
					$result = $original_result;
				} else {
					$original_result = pq( $selector );
					$get = $original_result->find('script')->remove()->end();
					$result = $get->html();
					$attributes = self::get_attributes( $args['attributes'], $key );

					if ( ! empty( $attributes['attribute'] ) ) {
						$result = $get->attr( $attributes['attribute'] );
					}

					if ( ! empty( $attributes['only_text'] ) ) {
						$result = $get->text();
					}
				}
				$id = (int)$args['id'];
				$result = apply_filters( 'pe_get_html_' . $id, $result, $id, $args['url'], $selector, $original_result, $doc );
				$all_results .= $result;
				$results[] = $result;
			}
			phpQuery::unloadDocuments();
			if ( ! count( $results ) ) {
				$error->add( 'nothing found', esc_html__( 'Nothing found' ) );
				return $error;
			}

			$return = $args['template_general'];
			foreach ( $result_matches[0] as $match ) {
				$number = (int)str_replace( [ '{RESULT_', '}' ], '', $match );
				if ( $number && ! empty( $results[ $number - 1 ] ) ) {
					$return = str_replace( $match, $results[ $number - 1 ], $return );
				}
			}

			$return = str_replace( [ '{ALL_RESULTS}', '{LAST_UPDATE}' ], [ $all_results, get_date_from_gmt( date( 'Y/m/d H:i:s' ), 'H:i:s Y/m/d' ) ], $return );

			return $return;
		}

		/**
		 * Get and prepare the relevant attributes for the current Selector
		 *
		 * @param array $attributes_array All attributes.
		 * @param int   $key Current Selector key.
		 * @return array
		 */
		private static function get_attributes( $attributes_array, $key ) {
			$attrs = [];
			if ( ! empty( $attributes_array[ $key ] ) ) {
				$attributes = json_decode( $attributes_array[ $key ], true );
				if ( json_last_error() == JSON_ERROR_NONE ) {
					$attrs = $attributes;
				}
			}

			return $attrs;
		}

	}

	ParseEverything::init();
}
