<?php
namespace ParseInc;

class ParseCpt {
	const POST_TYPE = 'parse_everything';
	private static $settings;
	private static $templates;
	private static $default_settings;
	private static $default_templates = [
		'template_general' => '{ALL_RESULTS}',
		'template_between' => '<hr>Next result<hr>',
	];

	public static function init() {
		self::$default_settings = apply_filters( 'parse_everything_default_settings', [
			'url' => '',
			'not_html' => 0,
			'recurrence' => 'pe_hourly',
			'selectors' => [ '' ],
			'attributes' => [ '' ],
			'type' => 'email',
			'email' => [
				'email' => '',
				'subject' => '',
			],
			'request_args' => self::get_default_request_args( true ),
			'create_post' => [
				'title' => '',
				'type' => 'post',
				'status' => 'draft',
			]
		] );
		add_action( 'init', [ self::class, 'registration_cpt' ], 0 );
		add_action( 'admin_init', [ self::class, 'add_meta_boxes' ] );
		add_action( 'save_post', [ self::class, 'save_settings' ] );
		add_action( 'wp_ajax_pe_try_to_parse', [ self::class, 'try_to_parse' ] );
		add_action( 'wp_ajax_pe_try_to_send', [ self::class, 'try_to_send' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'include_js_and_css' ] );
	}

	public static function add_meta_boxes() {
		add_meta_box( 'pe-settings-meta', __( 'Settings' ),  [ self::class, 'settings_metabox' ], self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'pe-result-meta', __( 'Result Preview' ),  [ self::class, 'result_metabox' ], self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'pe-send-to-meta', __( 'Action Settings' ),  [ self::class, 'send_to_metabox' ], self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'pe-templates-meta', __( 'Templates' ),  [ self::class, 'templates_metabox' ], self::POST_TYPE, 'normal', 'low' );
		add_meta_box( 'pe-request-arguments', __( 'Request Arguments' ),  [ self::class, 'request_args' ], self::POST_TYPE, 'normal', 'low' );
		add_meta_box( 'pe-information-meta', __( 'Info' ),  [ self::class, 'info_metabox' ], self::POST_TYPE, 'side' );
		add_meta_box( 'pe-recurrence-meta', __( 'Recurrence' ),  [ self::class, 'recurrence_metabox' ], self::POST_TYPE, 'side' );
	}

	public static function get_settings( $post_id = null ) {
		if ( !is_null( $post_id ) ) {
			$settings = get_post_meta( $post_id, 'parse_settings', true );
		} else {
			if ( is_null( self::$settings ) ) {
				global $post;
				self::$settings = $post ? get_post_meta( $post->ID, 'parse_settings', true ) : [];
			}
			$settings = self::$settings;
		}


		return self::validate_settings( $settings );
	}

	public static function get_templates( $post_id = null ) {
		if ( !is_null( $post_id ) ) {
			$templates = get_post_meta( $post_id, 'parse_templates', true );
		} else {
			if ( is_null( self::$templates ) ) {
				global $post;
				self::$templates = $post ? get_post_meta( $post->ID, 'parse_templates', true ) : [];
			}
			$templates = self::$templates;
		}


		return self::validate_templates( $templates );
	}

	private static function validate_settings( $settings ) {
		if ( empty( $settings ) ) {
			$settings = [];
		}

		if ( empty( self::$default_settings['email']['email'] ) && !isset( $settings['email']['email'] ) ) {
			$current_user = wp_get_current_user();
			self::$default_settings['email']['email'] = $current_user->user_email;
		}

		return shortcode_atts( self::$default_settings, $settings );
	}

	private static function validate_templates( $templates ) {
		if ( empty( $templates ) ) {
			$templates = [];
		}

		return shortcode_atts( self::$default_templates, $templates );
	}

	public static function try_to_send() {
		check_ajax_referer( 'try_to_send' );
		$parse_result = \ParseEverything::parse();
		if ( is_wp_error( $parse_result ) ) {
			wp_send_json_error( $parse_result->get_error_message() );
		}

		$post_data = filter_input( INPUT_POST, 'data' );
		$args =[];
		wp_parse_str( $post_data, $args );

		$send_result = ParseSenders::send( $args, $parse_result );

		if ( is_wp_error( $send_result ) ) {
			wp_send_json_error( $send_result->get_error_message() );
		} elseif( ! $send_result ) {
			wp_send_json_error( esc_html__( 'Something went wrong!' ) );
		}

		wp_send_json_success( esc_html__( 'The parse result has been sent' ) );
	}

	public static function try_to_parse() {
		check_ajax_referer( 'parse' );

		$result = \ParseEverything::parse();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

//			$result = '<iframe src="data:text/html;charset=utf-8,' . rawurlencode( $result ) . '"></iframe>';

		wp_send_json_success( $result );
	}

	public static function include_js_and_css() {
		if (  self::POST_TYPE === get_post_type() ) {
			wp_enqueue_style( 'wp-jquery-ui-dialog' );
			wp_enqueue_script( 'pe_admin_script', \ParseEverything::plugin_dir_url() . 'assets/js/admin.js', [ 'jquery-ui-dialog' ] );
			wp_enqueue_style( 'pe_admin_styles', \ParseEverything::plugin_dir_url() . 'assets/css/styles.css' );
		}
	}

	public static function result_metabox() {
		echo '<div id="pe_result">' . esc_html__( 'You\'ll see a result of parsing here' ) . '</div>';
	}

	public static function send_to_metabox() {
		$settings = self::get_settings();
		$actions = apply_filters( 'parse_everything_send_methods', [
			'' => __( 'None' ),
			'email' => __( 'Send Email' ),
			'create_post' => __( 'Create Post' ),
		] );
		?>
<table id="pe_send_settings" class="pe_settings">
<tbody>
	<tr>
		<td><label><?php esc_html_e( 'Action:' ); ?></label></td><td>
			<select id="pe_send_type" name="type">
				<?php foreach ( $actions as $slug => $title ) { ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $settings['type'], $slug ); ?>><?php echo esc_html( $title ); ?></option>
				<?php } ?>
			</select>
			<span class="description"><?php esc_html_e( 'Only if the parse result is changed this action will happen.'  ); ?></span>
		</td>
	</tr>
	<tr><td colspan="2">&nbsp;</td></tr>
	<tr class="pe_email pe_toggle"><td><?php esc_html_e( 'Email:' ); ?></td><td><input name="email[email]" type="email" placeholder="johnsmith@example.com" value="<?php echo esc_attr( $settings['email']['email'] ); ?>" /></td></tr>
	<tr class="pe_email pe_toggle"><td><?php esc_html_e( 'Subject:' ); ?></td><td><input name="email[subject]" type="text" placeholder="<?php esc_attr_e( 'Subject' ); ?>" value="<?php echo esc_attr( $settings['email']['subject'] ); ?>" /></td></tr>
	<tr class="pe_post pe_toggle"><td><?php esc_html_e( 'Post Title:' ); ?></td><td><input name="post[title]" type="text" placeholder="<?php esc_attr_e( 'Post Title' ); ?>" value="<?php echo esc_attr( $settings['create_post']['title'] ); ?>" /></td></tr>
	<tr class="pe_post pe_toggle">
		<td><label><?php esc_html_e( 'Post Type:' ); ?></label></td><td>
			<select name="post[type]">
				<?php
					$post_types = get_post_types( [ 'public' => true ], 'objects' );
					foreach ( $post_types as $slug => $value ) {
						echo '<option value="' . esc_html( $slug ) . '" ' . selected( $settings['create_post']['type'], $slug, false ) . '>' . esc_attr( $value->label ) . '</option>';
					}
				?>
			</select>
		</td>
	</tr>
	<tr class="pe_post pe_toggle">
		<td><label><?php esc_html_e( 'Post Status:' ); ?></label></td><td>
			<select name="post[status]">
				<option value="draft" <?php selected( $settings['create_post']['status'], 'draft' ); ?>><?php esc_html_e( 'Draft' ); ?></option>
				<option value="publish" <?php selected( $settings['create_post']['status'], 'publish' ); ?>><?php esc_html_e( 'Publish' ); ?></option>
			</select>
			<span class="description"><?php esc_html_e( 'Should it publish new posts or not?'  ); ?></span>
		</td>
	</tr>
	<?php do_action( 'parse_everything_setting_fields', $settings ); ?>
	<tr><td colspan="2">&nbsp;</td></tr>
	<tr>
		<td colspan="2"><input id="pe_try_to_send" type="button" class="button button-primary" data-nonce="<?php echo esc_attr( wp_create_nonce( 'try_to_send' ) ); ?>" value="<?php esc_attr_e( 'Try to send' ); ?>" /><span class="spinner"></span><span id="pe_send_result"></span></td>
	</tr>
</tbody>
</table>
		<?php
	}

	public static function templates_metabox() {
		$templates = self::get_templates();
		?><strong>
			<?php esc_html_e( 'General template' ); ?>
		</strong>
		<br>
		<textarea id="template_general" name="template_general" rows="4" cols="100"><?php echo $templates['template_general']; ?></textarea>
		<p class="description">
			<?php
				printf( esc_html__( 'You can use the following placeholders: %1$s{ALL_RESULTS}%2$s - is all results of parsing; %1$s{RESULT_1}%2$s, %1$s{RESULT_2}%2$s, ... - is relevant result to `CSS selectors` option; %1$s{LAST_UPDATE}%2$s - is the last update date.' ), '<strong>', '</strong>' );
			?>
		</p>
		<br>
		<strong><?php esc_html_e( 'Template between results' ); ?></strong>
			<?php esc_html_e( '(it will be inside {ALL_RESULTS} placeholder if you have several CSS selectors)' ); ?>
		<textarea id="template_between" name="template_between" rows="4" cols="100"><?php echo $templates['template_between']; ?></textarea>
		<?php
	}

	public static function request_args() {
		$args     = self::get_default_request_args();
		$settings = self::get_settings();
		?>
		<a href="https://developer.wordpress.org/reference/classes/WP_Http/request/#parameters" target="_blank"><?php esc_html_e( 'Read more' ); ?></a>
		<table id="pe_request_headers" class="pe_settings">
		<tbody>
			<?php foreach ( $args as $type => $data ) { ?>
			<tr>
				<td><?php echo $data['title']; ?></td>
				<td>
					<?php if ( isset( $data['type'] ) && 'bool' === $data['type'] ) { ?>
					<select name="request_args[<?php echo esc_attr( $type ); ?>]">
						<option value="true" <?php selected( in_array( $settings['request_args'][$type], [true, 'true'], true ) ); ?>>true</option>
						<option value="false" <?php selected( ! in_array( $settings['request_args'][$type], [true, 'true'], true ) ); ?>>false</option>
					</select>
					<?php } elseif ( isset( $data['type'] ) && 'textarea' === $data['type'] ) { ?>
						<textarea name="request_args[<?php echo esc_attr( $type ); ?>]" rows="10" cols="100"><?php echo $settings['request_args'][$type]; ?></textarea>
					<?php } elseif ( 'method' === $type ) { ?>
						<select name="request_args[<?php echo esc_attr( $type ); ?>]">
							<option value="GET" <?php selected( 'POST' !== $settings['request_args'][ $type ] ); ?>>GET</option>
							<option value="POST" <?php selected( 'POST' === $settings['request_args'][ $type ] ); ?>>POST</option>
						</select>
					<?php } else { ?>
					<?php $input_type = isset( $data['type'] ) ? $data['type'] : 'text'; ?>
					<input name="request_args[<?php echo esc_attr( $type ); ?>]" type="<?php echo esc_attr( $input_type ); ?>" value="<?php echo esc_attr( $settings['request_args'][$type] ); ?>" />
					<?php } ?>
				</td>
			</tr>
			<?php } ?>
		</tbody>
		</table>
		<?php
	}

	private static function get_default_request_args( $only_values = false ) {
		$args = [
			'method' => [
				'title' => esc_html__( 'Method' ),
				'value' => 'get',
			],
			'body' => [
				'title' => esc_html__( 'Body' )
					. '<br><a target="_blank" href="https://wpscholar.com/blog/view-form-data-in-chrome/">'
					. esc_html__( 'How to get it?' ) . '</a>',
				'value' => '',
				'type'  => 'textarea',
			],
			'headers' => [
				'title' => esc_html__( 'Headers' )
					. '<br><a target="_blank" href="https://mkyong.com/computer-tips/how-to-view-http-headers-in-google-chrome/">'
					. esc_html__( 'How to get it?' ) . '</a>',
				'value' => "X-Requested-With: XMLHttpRequest\n",
				'type'  => 'textarea',
			],
//			'cookies' => [
//				'title' => esc_html_e( 'Cookies' ),
//				'value' => '',
//			],
			'httpversion' => [
				'title' => esc_html__( 'HTTP version' ),
				'value' => '',
			],
			'user-agent' => [
				'title' => esc_html__( 'User Agent' ),
				'value' => '',
			],
			'timeout' => [
				'title' => esc_html__( 'Timeout' ),
				'value' => 5,
				'type'  => 'number',
			],
			'redirection' => [
				'title' => esc_html__( 'Redirection' ),
				'value' => 5,
				'type'  => 'number',
			],
			'sslverify' => [
				'title' => esc_html__( 'SSL verify' ),
				'value' => true,
				'type'  => 'bool',
			],
		];

		if ( $only_values ) {
			$args = wp_list_pluck( $args, 'value' );
		}
		return $args;
	}

	public static function info_metabox() {
		global $post;

		$period = '-';
		if ( ! empty( $post->ID ) && 'publish' === $post->post_status ) {
			$pe_period = get_post_meta( $post->ID, 'pe_period', true );
			if ( $pe_period ) {
				$periods = ParseCrons::get_periods();
				$period = ! empty( $periods[ $pe_period ]['display'] ) ? $periods[ $pe_period ]['display'] : $period;
			}
		}

		if ( !empty( $post->ID ) ) {
			$data = get_post_meta( $post->ID, 'parse_result', true );
			if ( !empty( $data['time'] ) ) {
				$last_update = get_date_from_gmt( date( 'Y/m/d H:i:s',  $data['time'] ), 'H:i:s Y/m/d' );
			}
		}

		echo esc_html__( 'Shortcode:' ) . ' ' . ( ! empty( $post->ID ) ? ' <strong>[parse_result id="' . esc_attr( $post->ID ) . '"]</strong>' : '' );
		echo '<br>';
		echo esc_html__( 'Last update:' ) . ' ' . ( ! empty( $last_update ) ? esc_html( $last_update ) : '-' );
		echo '<br>';
		echo esc_html__( 'Cron recurs:' ) . ' ' . $period;
	}

	public static function recurrence_metabox() {
		$periods = ParseCrons::get_periods();
		$settings = self::get_settings();
		$recurrence = $settings['recurrence'];
		?>
		<select name="recurrence">
			<?php foreach ( $periods as $key => $period ) { ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key === $recurrence ); ?>><?php echo esc_attr( $period['display'] ); ?></option>
			<?php } ?>
		</select>
		<p><strong><?php echo esc_html__( 'Note:' ); ?></strong> <?php printf( esc_html__( 'Running default WP cron can take more time than expected if you don\'t have enough activity on your site. We recommend %1$susing a server cron%2$s.' ), '<a href="https://kinsta.com/knowledgebase/disable-wp-cron/" target="_blank">', '</a>' ); ?></p>
		<?php
	}

	public static function settings_metabox() {
		$settings = self::get_settings();
		$url = $settings['url'];
		$not_html = $settings['not_html'];
		$selectors = $settings['selectors'];
		$attributes = $settings['attributes'];
		?>
<table class="pe_settings">
<tbody>
	<tr>
		<td><label><?php esc_html_e( 'Link to parse:' ); ?></label></td><td>
			<input id="pe_url" name="url" type="url" value="<?php echo esc_attr( $url ); ?>" size="100" />
			<a href="<?php echo esc_url( $url ); ?>" class="pe_goto_url dashicons-arrow-right-alt" target="_blank"></a>
		</td>
	</tr>
	<tr>
		<td></td><td>
			<label><input name="not_html" type="checkbox" <?php checked( $not_html, 1 ); ?> value="1" />
			<?php esc_html_e( 'It\'s not HTML' ); ?></label>
		</td>
	</tr>
	<tr>
		<td></td><td></td>
	</tr>
	<?php foreach ( $selectors as $key =>  $selector ) { ?>
		<tr>
			<td>
				<?php if ( 0 === $key ) { ?>
					<label><?php esc_html_e( 'CSS Selectors:' ); ?></label>
				<?php } ?>
			</td>
			<td>
				<input class="pe_selector" name="selectors[]" type="text" value="<?php echo esc_attr( $selector ); ?>" />
				<input class="pe_selector_attribute" name="attributes[]" type="hidden" value="<?php echo ! empty( $attributes[ $key ] ) ? esc_attr( $attributes[ $key ] ) : ''; ?>" />
				<span class="pe_add_selector dashicons-plus-alt"></span>
				<span class="pe_edit_selector dashicons-edit open-pe-dialog" data-dialog_name="pe_edit-dialog"></span>
				<span class="pe_remove_selector dashicons-dismiss"></span>
			</td>
		</tr>
	<?php } ?>
	<tr><td colspan="2">&nbsp;</td></tr>
	<tr>
		<td colspan="2"><input id="pe_try_to_parse" type="button" class="button button-primary" data-nonce="<?php echo esc_attr( wp_create_nonce( 'parse' ) ); ?>" value="<?php esc_attr_e( 'Try to parse' ); ?>" /><span class="spinner"></span></td>
	</tr>
</tbody>
</table>
<div class="pe-dialog pe_edit-dialog hidden">
	<span class="description"><?php esc_attr_e( 'HTML attribute' ); ?></span>
	<input class="pe_selector_attribute_dialog" name="attribute" type="text" />
	<br>
	<br>
	<label><input name="only_text" class="pe_selector_attribute_dialog" type="checkbox" value="1" />
			<?php esc_html_e( 'Get only text without HTML tags' ); ?></label>
	<br>
	<br>
	<div>
		<?php submit_button( __( 'Save' ), 'primary', 'pe_save', false ); ?>
	</div>
</div>
		<?php
	}

	public static function save_settings() {
		global $post;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE
				|| empty( $post->ID )
				|| get_post_status( $post->ID ) === 'auto-draft' ) {
			return;
		}

		$settings = self::get_settings();
		$url = filter_input( INPUT_POST, 'url', FILTER_VALIDATE_URL );
		$not_html = filter_input( INPUT_POST, 'not_html', FILTER_VALIDATE_INT );
		$recurrence = filter_input( INPUT_POST, 'recurrence' );
		$type = filter_input( INPUT_POST, 'type' );

		$senders_settings = array_diff_key( self::$default_settings, [ 'url', 'not_html', 'type', 'recurrence' ] );

		foreach ( $senders_settings as $slug => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}
			$$slug = filter_input( INPUT_POST, $slug, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			if ( is_array( $$slug ) ) {
				$settings[ $slug ] = $$slug;
			}
		}
		if ( is_string( $url ) ) {
			$settings['url'] = $url;
		}
		$settings['not_html'] = intval( ! empty( $not_html ) );
		if ( is_string( $recurrence ) ) {
			$settings['recurrence'] = $recurrence;
			update_post_meta( $post->ID, 'pe_period', $recurrence );
		}
		if ( is_string( $type ) ) {
			$settings['type'] = $type;
		}

		update_post_meta( $post->ID, 'parse_settings', $settings );

		$templates = self::get_templates();
//		$template_general = wp_json_encode( filter_input( INPUT_POST, 'template_general' ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK | JSON_UNESCAPED_LINE_TERMINATORS );
		$template_general = filter_input( INPUT_POST, 'template_general' );
		$template_between = filter_input( INPUT_POST, 'template_between' );
		if ( is_string( $template_general ) ) {
			$templates['template_general'] = $template_general;
		}
		if ( is_string( $template_between ) ) {
			$templates['template_between'] = $template_between;
		}

		update_post_meta( $post->ID, 'parse_templates', $templates );
	}

	// Register Custom Post Type
	public static function registration_cpt() {
		$labels = array(
			'name'                  => 'Parse Tasks',
			'singular_name'         => 'Parse Task',
			'menu_name'             => 'Parse Tasks',
			'name_admin_bar'        => 'Parse Task',
			'archives'              => 'Item Archives',
			'attributes'            => 'Item Attributes',
			'parent_item_colon'     => 'Parent Item:',
			'all_items'             => 'All Tasks',
			'add_new_item'          => 'Add New Parse Task',
			'add_new'               => 'Add New',
			'new_item'              => 'New Task',
			'edit_item'             => 'Edit Task',
			'update_item'           => 'Update Task',
			'view_item'             => 'View Task',
			'view_items'            => 'View Tasks',
			'search_items'          => 'Search Tasks',
			'not_found'             => 'Not found',
			'not_found_in_trash'    => 'Not found in Trash',
			'featured_image'        => 'Featured Image',
			'set_featured_image'    => 'Set featured image',
			'remove_featured_image' => 'Remove featured image',
			'use_featured_image'    => 'Use as featured image',
			'insert_into_item'      => 'Insert into item',
			'uploaded_to_this_item' => 'Uploaded to this item',
			'items_list'            => 'Items list',
			'items_list_navigation' => 'Items list navigation',
			'filter_items_list'     => 'Filter items list',
		);
		$capabilities = array(
			'edit_post'             => 'manage_options',
			'read_post'             => 'manage_options',
			'delete_post'           => 'manage_options',
			'delete_posts'           => 'manage_options',
			'edit_posts'            => 'manage_options',
			'publish_posts'         => 'manage_options',
			'read_private_posts'    => 'manage_options',
		);
		$args = array(
			'label'                 => 'Parse Item',
			'description'           => 'Destination for parsing',
			'labels'                => $labels,
			'supports'              => array( 'title' ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 5,
			'menu_icon'             => 'dashicons-megaphone',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => true,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'rewrite'               => false,
			'capabilities'          => $capabilities,
			'show_in_rest'          => false,
		);
		register_post_type( self::POST_TYPE, $args );
	}

}
