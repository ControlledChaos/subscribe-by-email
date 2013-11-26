<?php


class Incsub_Subscribe_By_Email_Admin_Subscribers_Page extends Incsub_Subscribe_By_Email_Admin_Page {

	private $subscriber;
	private $errors;

	public function __construct() {

		$args = array(
			'slug' => 'sbe-subscribers',
			'page_title' => __( 'Subscriptions', INCSUB_SBE_LANG_DOMAIN ),
			'menu_title' => __( 'Subscriptions', INCSUB_SBE_LANG_DOMAIN ),
			'capability' => 'manage_options'
		);
		parent::__construct( $args );

		add_action( 'admin_init', array( &$this, 'validate_form' ) );
		//add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
		//add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_styles' ) );

	}



	public function render_page() {

		$add_new_link = add_query_arg(
			'page',
			'sbe-add-subscribers',
			admin_url( 'admin.php' )
		);

		$title_link = '<a href="' . $add_new_link . '" class="add-new-h2">' . __( 'Add New', INCSUB_SBE_LANG_DOMAIN ) . '</a>';

		?>
			<div class="wrap">
				
				<?php screen_icon( 'sbe' ); ?>
				
				<?php if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' && isset( $_GET['sid'] ) ): ?>
					<?php 

						$subscriber = incsub_sbe_get_subscriber( $_GET['sid'] );

						if ( empty( $subscriber ) )
							wp_die( __( 'The subscriber does not exist', INCSUB_SBE_LANG_DOMAIN ) );

						$errors = get_settings_errors( 'subscribe' );

						if ( ! empty( $errors ) )
							settings_errors( 'subscribe' );

						if ( isset( $_GET['updated'] ) && $_GET['updated'] == 'true' && empty( $errors ) ) {
							?>
								<div class="updated">
									<p><?php _e( 'Subscriber updated', INCSUB_SBE_LANG_DOMAIN ); ?></p>
								</div>
							<?php
						}

						$settings = incsub_sbe_get_settings();
						$extra_fields = $settings['extra_fields'];
					?>
					<h2><?php _e( 'Edit subscriber', INCSUB_SBE_LANG_DOMAIN ); ?></h2>

					<form id="form-subscriptions-edit" action="" method="post">
						<?php wp_nonce_field( 'edit_subscriber' ); ?>
						<input type="hidden" name="sid" value="<?php echo $subscriber->get_subscription_ID(); ?>">
						<table class="form-table">
							<?php $this->render_row( __( 'Email', INCSUB_SBE_LANG_DOMAIN ), array( &$this, 'render_email_row' ), $subscriber ); ?>
							<?php 

								foreach ( $extra_fields as $extra_field ) {
									$callback = 'render_extra_field_' . $extra_field['type'];
									$args = array(
										'subscriber' => $subscriber,
										'atts' => $extra_field
									);
									$this->render_row( $extra_field['title'], array( &$this, $callback ), $args );
								}
							?>
							
						</table>
						<p class="submit">
							<?php submit_button( __( 'Submit changes', INCSUB_SBE_LANG_DOMAIN ), 'primary', 'submit_edit_subscriber', false ); ?>
							<a href="<?php echo esc_url( $this->get_permalink() ) ?>" class="button-secondary"><?php _e( 'Cancel', INCSUB_SBE_LANG_DOMAIN ); ?></a>
						</p>
					</form>
				<?php else: ?>
					<h2><?php _e( 'Subscribers', INCSUB_SBE_LANG_DOMAIN ); ?> <?php echo $title_link; ?></h2>
					
					<form id="form-subscriptions-list" action="" method="post">
						<?php
							require_once( INCSUB_SBE_PLUGIN_DIR . 'admin/tables/subscribers-table.php' );
							$the_table = new Incsub_Subscribe_By_Email_Subscribers_Table();

							$the_table->prepare_items();
							$the_table->search_box( __( 'Search by email', INCSUB_SBE_LANG_DOMAIN ), 'search-email' );
							echo '<br/><br/>';
							$the_table->display();

						?>
					</form>
				<?php endif; ?>

			</div>

		<?php
	}

	public function render_content() {

	}

	public function render_email_row( $subscriber ) {
		?>
			<input type="text" class="regular-text" name="subscribe-email" value="<?php echo esc_attr( $subscriber->get_subscription_email() ); ?>" />
		<?php
	}

	public function render_extra_field_text( $args ) {
		$subscriber = $args['subscriber'];
		$extra_field = $args['atts'];

		$meta_value = isset( $_POST['subscribe-meta'][ $extra_field['slug'] ] ) ? 
			incsub_sbe_validate_extra_field( $extra_field['type'], $_POST['subscribe-meta'][ $extra_field['slug'] ] ) : 
			$subscriber->get_meta( $extra_field['slug'], '' );
		?>
			<input type="text" name="subscribe-meta[<?php echo $extra_field['slug']; ?>]" value="<?php echo esc_attr( $meta_value ); ?>">
			<?php echo $extra_field['required'] ? '(*)' : ''; ?>
		<?php
	}

	public function render_extra_field_checkbox( $args ) {
		$subscriber = $args['subscriber'];
		$extra_field = $args['atts'];

		$meta_value = isset( $_POST['subscribe-meta'][ $extra_field['slug'] ] ) ? 
			incsub_sbe_validate_extra_field( $extra_field['type'], $_POST['subscribe-meta'][ $extra_field['slug'] ] ) : 
			$subscriber->get_meta( $extra_field['slug'] );
		?>
			<input type="checkbox" name="subscribe-meta[<?php echo $extra_field['slug']; ?>]" <?php checked( $meta_value ); ?>>
			<?php echo $extra_field['required'] ? '(*)' : ''; ?>
		<?php
		
	}


	public function validate_form() {
		if ( isset( $_POST['submit_edit_subscriber'] ) ) {
			check_admin_referer( 'edit_subscriber' );

			$model = incsub_sbe_get_model();

			$email = $_POST['subscribe-email'];
			$sid = $_POST['sid'];
			$error = false;

			$subscriber = incsub_sbe_get_subscriber( $sid );

			if ( ! is_email ( $email ) ) {
				add_settings_error( 'subscribe', 'wrong-email', __( 'Please, insert a valid email', INCSUB_SBE_LANG_DOMAIN ) );
				$error = true;
			}
			else {
				$model->update_subscriber_email( $sid, $email );
			}

			$settings = incsub_sbe_get_settings();
			$extra_fields = $settings['extra_fields'];

			foreach ( $extra_fields as $extra_field ) {
				$meta_value = isset( $_POST['subscribe-meta'][ $extra_field['slug'] ] ) ? $_POST['subscribe-meta'][ $extra_field['slug'] ] : '';
				$meta_value = incsub_sbe_validate_extra_field( $extra_field['type'], $meta_value );

				if ( empty( $meta_value ) && $extra_field['required'] ) {
					add_settings_error( 'subscribe', 'required-extra-field', sprintf( __( '%s is a required field', INCSUB_SBE_LANG_DOMAIN ), $extra_field['title'] ) );
					$_POST['subscribe-meta'][ $extra_field['slug'] ] = $subscriber->get_meta( $extra_field['slug'] );
					$error = true;
				}

				if ( $error )
					break;

				$model->update_subscriber_meta( $sid, $extra_field['slug'], $meta_value );
			}

			if ( ! $error )
				wp_redirect( add_query_arg( 'updated', 'true' ) );


		}
	}
}
