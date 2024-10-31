<?php

if ( ! class_exists( 'ParseEverythingTelegramSender' ) ) {
	class ParseEverythingTelegramSender {
		const SLUG = 'telegram_channel';
		public static function init() {
			add_filter( 'parse_everything_send_methods', [ self::class, 'add_send_method' ] );
			add_filter( 'parse_everything_default_settings', [ self::class, 'default_settings' ] );
			add_filter( 'parse_everything_custom_send', [ self::class, 'send' ], 10, 3 );
			add_action( 'parse_everything_setting_fields', [ self::class, 'setting_fields' ] );
		}

		public static function default_settings( $settings ) {
			$settings[ self::SLUG ] = [
				'token' => '',
				'channel_id' => '',
			];

			return $settings;
		}

		public static function send( $result, $sender_type, $args ) {
			if ( self::SLUG !== $sender_type ) {
				return $result;
			}

			require_once dirname( dirname( __FILE__ ) ) . "/vendor/autoload.php";

			try {
				$bot = new \TelegramBot\Api\Client( $args['token'] );
				$result = $bot->sendMessage( $args['channel_id'], $args['message'] );

			} catch ( \TelegramBot\Api\Exception $e ) {
				$error = new WP_Error();
				$error->add( 'wrong_email', esc_html( $e->getMessage() ) );
				return $error;
			}

			return $result;
		}

		public static function setting_fields( $settings ) {
			?>
			<tr class="pe_<?php echo esc_attr( self::SLUG ); ?> pe_toggle">
				<td><?php esc_html_e( 'API token:' ); ?></td>
				<td>
					<input name="<?php echo esc_attr( self::SLUG ); ?>[token]" type="text" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" value="<?php echo esc_attr( $settings[ self::SLUG ]['token'] ); ?>" />
					<a href="https://core.telegram.org/bots#3-how-do-i-create-a-bot" target="_blank"><?php esc_html_e( 'How to get API token' ); ?></a>
				</td>
			</tr>
			<tr class="pe_<?php echo esc_attr( self::SLUG ); ?> pe_toggle">
				<td><?php esc_html_e( 'Channel ID:' ); ?></td>
				<td>
					<input name="<?php echo esc_attr( self::SLUG ); ?>[channel_id]" type="text" placeholder="<?php esc_attr_e( 'Channel ID' ); ?>" value="<?php echo esc_attr( $settings[ self::SLUG ]['channel_id'] ); ?>" />
					<a href="https://gist.github.com/mraaroncruz/e76d19f7d61d59419002db54030ebe35" target="_blank"><?php esc_html_e( 'How to get Channel ID' ); ?></a>
				</td>
			</tr>
			<?php
		}

		public static function add_send_method( $methods ) {
			$methods[ self::SLUG ] = __( 'Send to Telegram Channel' );

			return $methods;
		}
	}

	ParseEverythingTelegramSender::init();
}
