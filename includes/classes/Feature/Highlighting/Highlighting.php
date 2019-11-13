<?php
/**
 * Highlighting feature
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\Highlighting;

use ElasticPress\Feature as Feature;

/**
 * Documents Highlighting class.
 */
class Highlighting extends Feature {

	/**
	 * Initialize feature setting it's config
	 *
	 * @since  VERSION
	 */
	public function __construct() {
		$this->slug = 'elasticpress-highlighting';

		$this->title = esc_html__( 'Search Term Highlighting', 'elasticpress' );

		$this->requires_install_reindex = false;

		$this->default_terms = [
			'mark',
			'span',
			'strong',
			'em',
			'i',
		];

		$this->highlighting_configuration = get_option( 'elasticpress_highlighting', [] );

		parent::__construct();
	}

	/**
	 * Setup feature filters
	 *
	 * @since VERSION
	 */
	public function setup() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_menu', [ $this, 'add_term_highlighting_submenu_page' ], 15 );
		add_filter( 'ep_formatted_args', [ $this, 'add_search_highlight_tags' ], 10, 2 );

		// Add filter to overwrite the pre_/post_ tags
		add_filter( 'ep_highlighting_tag', [ $this, 'get_highlighting_tag' ] );
		add_action( 'admin_post_ep-highlighting', [ $this, 'handle_save' ] );

		add_filter( 'ep_highlighting_excerpt', [ $this, 'allow_excerpt_html' ], 10, 2 );
	}

	/**
	 * Output feature box summary
	 *
	 * @since  VERSION
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Inserts tags to wrap search terms in results for custom styling.', 'elasticpress' ); ?></p>
		<?php
	}


	/**
	 * Output feature box long
	 *
	 * @since  VERSION
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'The wrapping HTML tag comes with the "ep-highlight" class for easy styling. Select a different tag, or add a color in the advanced options.' ); ?></p>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elasticpress-highlighting' ) ); ?>"><?php esc_html_e( 'Advanced search term highlighting settings', 'elasticpress' ); ?></a></p>
		<?php
	}


	/**
	 * Adds the submenu page for controlling search term highlighting options
	 */
	public function add_term_highlighting_submenu_page() {
		add_submenu_page( 'elasticpress', __( 'Search Term Highlighting', 'elasticpress' ), __( 'Search Term Highlighting', 'elasticpress' ), 'manage_options', 'elasticpress-highlighting', [ $this, 'render_settings_page' ] );
	}

	/**
	 * Renders the settings page that controls search term highlighting
	 */
	public function render_settings_page() {
		include EP_PATH . '/includes/partials/header.php';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Manage Search Term Highlighting', 'elasticpress' ); ?></h1>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="highlighting-settings metabox-holder">
				<input type="hidden" name="action" value="ep-highlighting">
				<?php wp_nonce_field( 'save-highlighting', 'ep-highlighting-nonce' ); ?>
				<?php
				if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification
					if ( $_GET['settings-updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification
						?>
						<div class="notice notice-success is-dismissible">
							<p><?php esc_html_e( 'Changes Saved!', 'elasticpress' ); ?></p>
						</div>
					<?php else : ?>
						<div class="notice notice-error is-dismissible">
							<p><?php esc_html_e( 'An error occurred when saving!', 'elasticpress' ); ?></p>
						</div>
						<?php
					endif;
				endif;

				$tag_options    = $this->default_terms;
				$current_values = $this->highlighting_configuration;

				$highlight_color = ( ! empty( $current_values['highlight_color'] ) ) ? $current_values['highlight_color'] : null;

				?>
					<div class="postbox">
						<h2 class="hndle"><?php echo esc_html_e( 'Highlight Tag' ); ?></h2>
						<div class="field-group">
							<div class="fields">
								<div class="field">
									<label for="highlight-tag"><?php echo esc_html_e( 'Highlight Tag: ', 'elasticpress' ); ?></label>
									<select id="highlight-tag" name="highlight-tag">
										<?php
										foreach ( $tag_options as $option ) :
											echo '<option value="' . esc_attr( $option ) . '" ' . selected( $option, $current_values['highlight_tag'] ) . '>' . esc_html( $option ) . '</option>';
										endforeach;
										?>
									</select>
								</div>

							</div>
						</div>
					</div>
					<div class="postbox">
						<h2 class="hndle"><?php echo esc_html_e( 'Highlight Color', 'elasticpress' ); ?></h2>
						<div class="field-group">
							<div class="field">
								<label for="highlight-color"><?php echo esc_html( 'Highlight Color: ' ); ?>
								<input type="text" id="highlight-color" name="highlight-color" class="ep-highlight-color-select" value="<?php echo esc_attr( $highlight_color ); ?>" />
							</div>
						</div>
					</div>
					<div class="postbox">
						<h2 class="hndle"><?php echo esc_html_e( 'Highlight Excerpt', 'elasticpress' ); ?></h2>
						<div class="field-group">
							<div class="field">
								<p>By default, WordPress strips HTML from content excerpts. Check to enable the highlight tag in excerpts. </p>
								<label>
									<input type="checkbox" id="highlight-excerpt" value="on" name="highlight-excerpt" <?php checked( ! empty( $current_values['highlight_excerpt'] ) && 'on' === $current_values['highlight_excerpt'] ? true : false ); ?> />
									<?php echo esc_html_e( 'Show highlight tag in Excerpt', 'elasticpress' ); ?>
								</label>
							</div>
						</div>
					</div>
				<?php
				submit_button();
				?>
			</form>
		</div>
		<?php
	}


	/**
	 * Handles processing the new highlighting values and saving them to the elasticpress.io service
	 */
	public function handle_save() {
		if ( ! isset( $_POST['ep-highlighting-nonce'] ) || ! wp_verify_nonce( $_POST['ep-highlighting-nonce'], 'save-highlighting' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['highlight-tag'] ) && in_array( $_POST['highlight-tag'], $this->default_terms, true ) ) {
			$new_highlight_tag = $_POST['highlight-tag'];
		} else {
			$current_config    = $this->highlighting_configuration;
			$new_highlight_tag = $current_config['highlight_tag'];
		}

		// get color
		$new_highlight_color = isset( $_POST['highlight-color'] ) ? $_POST['highlight-color'] : null;

		// get checkbokx value
		$new_highlight_excerpt = ( isset( $_POST['highlight-excerpt'] ) && 'on' === $_POST['highlight-excerpt'] ) ? 'on' : 'off';

		$final_config = array(
			'highlight_tag'     => sanitize_text_field( $new_highlight_tag ),
			'highlight_color'   => sanitize_text_field( $new_highlight_color ),
			'highlight_excerpt' => sanitize_text_field( $new_highlight_excerpt ),
		);

		update_option( 'elasticpress_highlighting', $final_config );

		$redirect_url = admin_url( 'admin.php?page=elasticpress-highlighting' );
		$redirect_url = add_query_arg( 'settings-updated', true, $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit();
	}


	/**
	 * Enqueue styles for highlighting
	 */
	public function enqueue_scripts() {
		wp_enqueue_style(
			'elasticpress-highlighting',
			EP_URL . 'dist/css/highlighting-styles.min.css',
			[],
			EP_VERSION
		);

		// retrieve settings to ge the current color value
		$current_config  = $this->highlighting_configuration;

		// check for value before inlining the style
		if ( ! empty( $current_config['highlight_color'] ) ) {
			$inline_color = "
				:root{
					--highlight-color: {$current_config['highlight_color']};
				}";
			wp_add_inline_style( 'elasticpress-highlighting', $inline_color );
		}

	}


	/**
	 * helper filter to check if the tag is allowed
	 *
	 * @param string $tag - html tag
	 * @return string
	 */
	public function get_highlighting_tag( $tag ) {
		$this->highlighting_tag = $tag;

		$options = $this->default_terms;

		if ( ! in_array( $tag, $options, true ) ) {
			$this->highlighting_tag = 'mark';
		}

		return $tag;
	}


	/**
	 * Set default fields to <h></h>ighilight
	 *
	 * @since VERSION
	 *
	 * @param array $formatted_args ep_formatted_args array
	 * @param array $args WP_Query args
	 * @return array $formatted_args formatted args with search highlight tags
	 */
	public function add_search_highlight_tags( $formatted_args, $args ) {

		apply_filters( 'ep_highlighting_excerpt', [] );

		if ( empty( $args['s'] ) ) {
			return $formatted_args;
		}

		$fields_to_highlight = array();

		// this should inherit the already-defined search fields.
		// get the search fields as defined by weighting, etc.
		if ( ! empty( $args['search_fields'] ) ) {
			$fields_to_highlight = $args['search_fields'];

		} else {
			// fallback to the fields pre-defined in the query
			$should_match = $formatted_args['query']['bool']['should'];

			// next, check for the the weighted fields, in case any are excluded.
			foreach ( $should_match as $item ) {
				$fields = $item['multi_match']['fields'];
				foreach ( $fields as $field ) {
					array_push( $fields_to_highlight, $field );
				}
			}

			$fields_to_highlight = array_unique( $fields_to_highlight );
		}

		// get current config
		$config = $this->highlighting_configuration;

		// define the tag to use
		$current_tag   = $config['highlight_tag'];
		$highlight_tag = apply_filters( 'ep_highlighting_tag', $current_tag );

		// default class
		$highlight_class = 'ep-highlight';

		// tags
		$opening_tag = '<' . $highlight_tag . ' class="' . $highlight_class . '">';
		$closing_tag = '</' . $highlight_tag . '>';

		// only for search query
		if ( ! is_admin() && ! empty( $args['s'] ) ) {
			foreach ( $fields_to_highlight as $field ) {
				$formatted_args['highlight']['fields'][ $field ] = [
					'pre_tags'  => [ $opening_tag ],
					'post_tags' => [ $closing_tag ],
					'type'      => 'plain',
				];
			}
		}
		return $formatted_args;
	}




	/**
	 * called by ep_highlighting_excerpt filter
	 *
	 * Replaces the default excerpt with the custom excerpt, allowing
	 * for the selected tag to be displayed in it.
	 */
	public function allow_excerpt_html() {
		if ( is_admin() ) {
			return;
		}

		if ( empty( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$current_values = $this->highlighting_configuration;

		if ( ! empty( $_GET['s'] ) && ! empty( $current_values['highlight_excerpt'] ) && 'on' === $current_values['highlight_excerpt'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			remove_filter( 'get_the_excerpt', 'wp_trim_excerpt' );
			add_filter( 'get_the_excerpt', [ $this, 'ep_highlight_excerpt' ] );
		}
	}


	/**
	 * called by allow_excerpt_html
	 * logic for the excerpt filter allowing the currentlty selected tag
	 *
	 * @param string $text - excerpt string
	 * @return string $text - the new excerpt
	 */
	public function ep_highlight_excerpt( $text ) {

		$current_values = $this->highlighting_configuration;

		echo $text;

		// reproduces wp_trim_excerpt filter, preserving the excerpt_more and excerpt_length filters
		if ( '' === $text ) {
			$text = get_the_content( '' );
			$text = apply_filters( 'the_content', $text );
			$text = str_replace( '\]\]\>', ']]&gt;', $text );
			$text = strip_tags( $text, '<' . esc_html( $current_values['highlight_tag'] ) . '>' );

			// use the defined length, if already applied...
			$excerpt_length = apply_filters( 'excerpt_length', 55 );

			// use defined excerpt_more filter if it is used
			$excerpt_more = apply_filters( 'excerpt_more', $text );

			$excerpt_more = $excerpt_more !== $text ? $excerpt_more : '[&hellip;]';

			$words = explode( ' ', $text, $excerpt_length + 1 );
			if ( count( $words ) > $excerpt_length ) {
				array_pop( $words );
				array_push( $words, $excerpt_more );
				$text = implode( ' ', $words );
			}
		}

		return $text;
	}

};