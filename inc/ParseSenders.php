<?php
namespace ParseInc;

use WP_Error;

class ParseSenders {

	public static function send( $settings, $parse_result ) {
		$result = new WP_Error();

		$sender_type = $settings['type'];
		if ( ! $sender_type ) {
			$result->add( 'no_sender_type', esc_html__( 'Sender type is not found' ) );
		} else if ( empty( $settings[ $sender_type ] ) || !is_array( $settings[ $sender_type ] ) ) {
			$result->add( 'no_sender_settings', sprintf( esc_html__( 'There are no settings for %s sender' ), esc_html( $sender_type ) ) );
		}
		$args = $settings[ $sender_type ];
		$args[ 'message' ] = $parse_result;

		if ( method_exists( self::class, $sender_type ) ) {
			$result = self::$sender_type( $args );
		} else {
			$custom_sender = apply_filters( 'parse_everything_custom_send', null, $sender_type, $args );
			if ( is_null( $custom_sender ) ) {
				$result->add( 'no_senders', sprintf( esc_html__( 'Send method %s doesn\'t found' ), esc_html( $sender_type ) ) );
			} else {
				$result = $custom_sender;
			}
		}

		return $result;
	}

	public static function email( $args ) {
		$error = new WP_Error();
		$default = [
			'email' => '',
			'subject' => '',
			'message' => '',
		];
		$args = array_merge( $default, $args );
		if ( ! is_email( $args['email'] ) ) {
			$error->add( 'wrong_email', esc_html__( 'A wrong Email' ) );
			return $error;
		}

		$result = wp_mail( $args['email'], $args['subject'], $args['message'], [ 'Content-Type: text/html; charset=UTF-8\r\n' ] );

		return $result;
	}

	public static function post( $args ) {
		$error = new WP_Error();
		$default = [
			'title' => '',
			'type' => 'post',
			'status' => 'draft',
			'message' => '',
		];
		$args = array_merge( $default, $args );
		$post_types = get_post_types( [ 'public' => true ] );
		if ( ! in_array( $args['type'], $post_types, true ) ) {
			$error->add( 'wrong_post_type', esc_html__( 'A wrong Post Type' ) );
			return $error;
		}

		$post_data = [
			'post_title' => wp_strip_all_tags( $args['title'] ),
			'post_content' => wp_slash( $args['message'] ),
			'post_status' => (string)$args['status'],
			'post_type' => (string)$args['type'],
		];

		$result = wp_insert_post( $post_data );

		return $result;
	}

}
