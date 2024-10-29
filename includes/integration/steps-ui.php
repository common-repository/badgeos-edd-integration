<?php
/**
 * Custom Achievement Steps UI.
 *
 * @package BadgeOS EDD
 * @subpackage Achievements
 * @author Credly, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://credly.com
 */

/**
 * Update badgeos_get_step_requirements to include our custom requirements.
 *
 * @param $requirements
 * @param $step_id
 * @return mixed
 */
function badgeos_edd_step_requirements( $requirements, $step_id ) {

	/**
     * Add our new requirements to the list
     */
    $requirements['badgeos_edd_trigger'] = get_post_meta( $step_id, '_badgeos_edd_trigger', true );
    $requirements['bosedd_download_for_review_id'] = (int) get_post_meta( $step_id, '_bosedd_download_for_review_id', true );
    $requirements['bosedd_specific_download'] = (int) get_post_meta( $step_id, '_bosedd_specific_download', true );
    $requirements['bosedd_download_type'] = get_post_meta( $step_id, '_bosedd_download_type', true );
    $requirements['bosedd_download_price'] = (int) get_post_meta( $step_id, '_bosedd_download_price', true );
    $requirements['bosedd_commission_price'] = (int) get_post_meta( $step_id, '_bosedd_commission_price', true );
    $requirements['bosedd_cart_price'] = (int) get_post_meta( $step_id, '_bosedd_cart_price', true );

	return $requirements;
}
add_filter( 'badgeos_get_step_requirements', 'badgeos_edd_step_requirements', 10, 2 );
add_filter( 'badgeos_get_rank_req_step_requirements', 'badgeos_edd_step_requirements', 10, 2 );
add_filter( 'badgeos_get_award_step_requirements', 'badgeos_edd_step_requirements', 10, 2 );
add_filter( 'badgeos_get_deduct_step_requirements', 'badgeos_edd_step_requirements', 10, 2 );

/**
 * Filter the BadgeOS Triggers selector with our own options.
 *
 * @param $triggers
 * @return mixed
 */
function badgeos_edd_activity_triggers( $triggers ) {
	$triggers[ 'bosedd_triggers' ] = __( 'EDD Activity', 'bos-awp' );

	return $triggers;
}
add_filter( 'badgeos_activity_triggers', 'badgeos_edd_activity_triggers' );
add_filter( 'badgeos_ranks_req_activity_triggers', 'badgeos_edd_activity_triggers' );
add_filter( 'badgeos_award_points_activity_triggers', 'badgeos_edd_activity_triggers' );
add_filter( 'badgeos_deduct_points_activity_triggers', 'badgeos_edd_activity_triggers' );

/**
 * Add EDD Triggers selector to the Steps UI.
 *
 * @param $step_id
 * @param $post_id
 */
function badgeos_edd_trigger_step_fields( $step_id, $post_id ) {
    $current_trigger = get_post_meta( $step_id, '_badgeos_edd_trigger', true );

    //Select Trigger Field
    echo '<select name="bosedd_trigger" class="bos-edd-step-field select-edd-trigger">';
    echo '<option value="">' . __( 'Select an EDD Trigger', 'bosedd' ) . '</option>';

    $badgeos_triggers = $GLOBALS[ 'badgeos_edd' ]->triggers;

    if ( ! empty( $badgeos_triggers ) ) {
        foreach ( $badgeos_triggers as $trigger => $trigger_label ) {
            echo '<option' . selected( $current_trigger, $trigger, false ) . ' value="' . esc_attr( $trigger ) . '">' . esc_html( $trigger_label ) . '</option>';
        }
    }
    echo '</select>';

    //Select Specific Download Field
    $selected_download_id = ( int ) get_post_meta( $step_id, '_bosedd_specific_download', true );

    echo '<select name="select_edd_download" class="bos-edd-step-field edd-trigger-download specific-edd-download">';
    echo '<option value="">' . __( 'Select an EDD Download', 'bosedd' ) . '</option>';

    $downloads = get_posts( array(
        'post_type' => 'download',
        'post_status' => 'publish',
        'posts_per_page' => -1
    ) );

    if ( ! empty( $downloads ) ) {
        foreach ( $downloads as $download ) {
            $selected = selected( $selected_download_id, $download->ID, false );
            echo '<option' . $selected . ' value="' . $download->ID . '">' . esc_html( get_the_title( $download->ID ) ) . '</option>';
        }
    }
    echo '</select>';

    //Select Specific Download Type Field
    $selected_download_type = get_post_meta( $step_id, '_bosedd_download_type', true );

    echo '<select name="select_download_type" class="bos-edd-step-field edd-trigger-download-type download_type_option">';
    echo '<option value="">' . __( 'Select Type of EDD Download', 'bosedd' ) . '</option>';

    $types = edd_get_download_types();
    if ( ! empty( $types ) ) {
        foreach ( $types as $key => $type ) {
            $selected = selected( $selected_download_type, $key, false );
            echo '<option' . $selected . ' value="' . $key . '">' . esc_html( $type ) . '</option>';
        }
    }
    echo '</select>';

    //Download Price Field
    $required_download_price = ( int ) get_post_meta( $step_id, '_bosedd_download_price', true );
    echo '<input type="number" name="download_price" value="' . $required_download_price . '" class="bos-edd-step-field bosedd-download-price edd-trigger-download-price" />';

    //Cart Total Price Field
    $required_cart_price = ( int ) get_post_meta( $step_id, '_bosedd_cart_price', true );
    echo '<input type="number" name="cart_price" value="' . $required_cart_price . '" class="bos-edd-step-field bosedd-cart-price edd-trigger-cart-price" />';
}
add_action( 'badgeos_steps_ui_html_after_trigger_type', 'badgeos_edd_trigger_step_fields', 10, 2 );
add_action( 'badgeos_rank_req_steps_ui_html_after_trigger_type', 'badgeos_edd_trigger_step_fields', 10, 2 );
add_action( 'badgeos_award_steps_ui_html_after_achievement_type', 'badgeos_edd_trigger_step_fields', 10, 2 );
add_action( 'badgeos_deduct_steps_ui_html_after_trigger_type', 'badgeos_edd_trigger_step_fields', 10, 2 );


/**
 * AJAX Handler for saving all steps.
 *
 * @param $title
 * @param $step_id
 * @param $step_data
 * @return string|void
 */
function badgeos_edd_save_step( $title, $step_id, $step_data ) {

    if ( 'bosedd_triggers' == $step_data['trigger_type'] ) {

        update_post_meta( $step_id, '_badgeos_edd_trigger', $step_data['bosedd_triggers'] );

        if ( 'purchase_specific_download' == $step_data['bosedd_triggers'] ) {

            $download_id = ( int ) $step_data['edd_specific_download'];
            if ( !empty( $download_id ) ) {
                $title = sprintf( __( 'Purchase "%s"', 'bosedd' ), get_the_title( $download_id ) );
            } else {
                $title = __( 'Purchase Download', 'bosedd' );
            }

            update_post_meta( $step_id, '_bosedd_specific_download', $download_id );
        } elseif ( 'purchase_download_of_specific_type' == $step_data['bosedd_triggers'] ) {

            $download_type = $step_data['edd_download_type'];
            $title = sprintf( __( 'Purchase "%s" type downloads', 'bosedd' ), $download_type );
            update_post_meta( $step_id, '_bosedd_download_type', $download_type );
        } elseif ( 'purchase_download_price' == $step_data['bosedd_triggers'] ) {

            $download_price = ( int ) $step_data['edd_download_price'];
            $title = sprintf( __( 'Purchase download of price "%s"', 'bosedd' ), $download_price );
            update_post_meta( $step_id, '_bosedd_download_price', $download_price );
        } elseif ( 'purchase_cart_price' == $step_data['bosedd_triggers'] ) {

            $cart_price = ( int ) $step_data['edd_cart_price'];
            $title = sprintf( __( 'Cart total "%s"', 'bosedd' ), $cart_price );
            update_post_meta( $step_id, '_bosedd_cart_price', $cart_price );
        }
    }

    return $title;
}
add_filter( 'badgeos_save_step', 'badgeos_edd_save_step', 10, 3 );

/**
 * Include custom JS for the BadgeOS Steps UI.
 */
function badgeos_edd_step_js() {
	?>
	<script type="text/javascript">
		jQuery( document ).ready( function ( $ ) {

			var times = $( '.required-count' ).val();

            /**
             * Listen for our change to our trigger type selector
             */
			$( document ).on( 'change', '.select-trigger-type', function () {

				var trigger_type = $( this ); 

                /**
                 * Show our group selector if we're awarding based on a specific group
                 */
				if ( 'bosedd_triggers' == trigger_type.val() ) {
					trigger_type.siblings( '.select-edd-trigger' ).show().change();
					var trigger = $('.select-edd-trigger').val();

				}  else {
                    trigger_type.siblings('.bos-edd-step-field').val('').hide();
				    trigger_type.siblings( '.select-edd-trigger' ).val('').hide().change();
					$( '.required-count' ).val( times );
				}
			} );



                /**
             * Listen for our change to our trigger type selector
             */
			$( document ).on( 'change', '.select-edd-trigger', function () {
				badgeos_edd_step_change( $( this ) , times);
			} );

            /**
             * Trigger a change so we properly show/hide our EDD menus
             */
			$( '.select-trigger-type' ).change();

            /**
             * Inject our custom step details into the update step action
             */
			$( document ).on( 'update_step_data', function ( event, step_details, step ) {
                step_details.bosedd_triggers = $( '.select-edd-trigger', step ).val();
                step_details.edd_trigger_label = $( '.select-edd-trigger option', step ).filter( ':selected' ).text();

                step_details.edd_specific_download = $( '.specific-edd-download', step ).val();
                step_details.edd_download_type = $( '.download_type_option', step ).val();
                step_details.edd_download_price = $( '.bosedd-download-price', step ).val();
                step_details.edd_cart_price = $( '.bosedd-cart-price', step ).val();
			} );

		} );

		function badgeos_edd_step_change( $this , times) {

            var trigger_parent = $this.parent(),
                trigger_value = trigger_parent.find( '.select-edd-trigger' ).val();
            var	trigger_parent_value = trigger_parent.find( '.select-trigger-type' ).val();

            if(trigger_value == '') {
                $this.siblings('.bos-edd-step-field').val('').hide();
            }

            if ( trigger_parent_value != 'bosedd_triggers' ) {

                trigger_parent.find('.required-count')
                    .val(times);

            } else {
                if( trigger_value == 'new_purchase' ) {
                    trigger_parent.find('.edd-trigger-download-type, .edd-trigger-download, .edd-trigger-download-price, .edd-trigger-cart-price').val('').hide();
                } else if( trigger_value == 'purchase_specific_download' ) {
                    trigger_parent.find('.edd-trigger-download-type, .edd-trigger-download-price, .edd-trigger-cart-price').val('').hide();
                    trigger_parent.find('.edd-trigger-download').show();
                } else if( trigger_value == 'purchase_download_of_specific_type' ) {
                    trigger_parent.find('.edd-trigger-download, .edd-trigger-download-price, .edd-trigger-cart-price').val('').hide();
                    trigger_parent.find('.edd-trigger-download-type').show();
                } else if( trigger_value == 'purchase_download_price' ) {
                    trigger_parent.find('.edd-trigger-download, .edd-trigger-download-type, .edd-trigger-cart-price').val('').hide();
                    trigger_parent.find('.edd-trigger-download-price').show();
                } else if( trigger_value == 'purchase_cart_price' ) {
                    trigger_parent.find('.edd-trigger-download, .edd-trigger-download-type, .edd-trigger-download-price').val('').hide();
                    trigger_parent.find('.edd-trigger-cart-price').show();
                }

            }

		}
	</script>
<?php
}
add_action( 'admin_footer', 'badgeos_edd_step_js' );