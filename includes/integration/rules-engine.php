<?php
/**
 * Custom Rules
 *
 * @package BadgeOS EDD
 * @author WooNinjas
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://wooninjas.com
 */

/**
 * Load up our EDD triggers so we can add actions to them
 */
function badgeos_edd_load_triggers() {

    /**
     * Grab our EDD triggers
     */
    $edd_triggers = $GLOBALS[ 'badgeos_edd' ]->triggers;

    if ( !empty( $edd_triggers ) ) {
        foreach ( $edd_triggers as $trigger => $trigger_label ) {

            if ( is_array( $trigger_label ) ) {
                $triggers = $trigger_label;

                foreach ( $triggers as $trigger_hook => $trigger_name ) {
                    add_action( $trigger_hook, 'badgeos_edd_trigger_event', 0, 20 );
                }
            } else {
                add_action( $trigger, 'badgeos_edd_trigger_event', 0, 20 );
            }
        }
    }
}

add_action( 'init', 'badgeos_edd_load_triggers', 0 );

/**
 * Handle each of our EDD triggers
 */
function badgeos_edd_trigger_event() {

    /**
     * Setup all our important variables
     */
    global $blog_id, $wpdb;

    /**
     * Grab the current trigger
     */
    $this_trigger = current_filter();

    /**
     * Setup args
     */

    $args = func_get_args();

    $payment_id = 0;
    if( in_array($this_trigger, array(
        'purchase_specific_download',
        'purchase_download_of_specific_type',
        'purchase_download_price'
    )) ) {

        $payment_id = $args[1];

    } elseif(in_array($this_trigger, array(
        'new_purchase',
        'purchase_cart_price'
    )) ) {
        $payment_id = $args[0];
    }

    if(empty($payment_id)) return;

    $payment_meta = edd_get_payment_meta( $payment_id );

    $userID = 0;
    if(!empty($payment_meta)) {
        if( isset($payment_meta['user_info']) && isset($payment_meta['user_info']['id']) ) {
            $userID = $payment_meta['user_info']['id'];
        }
    }

    if ( empty( $userID ) ) {
        return;
    }

    $user_data = get_user_by( 'id', $userID );

    if ( empty( $user_data ) ) {
        return;
    }

    /**
     * Now determine if any badges are earned based on this trigger event
     */

    $triggered_achievements = $wpdb->get_results( $wpdb->prepare( "SELECT pm.post_id, p.post_type FROM $wpdb->postmeta as pm inner join $wpdb->posts as p on( pm.post_id = p.ID ) WHERE p.post_status = 'publish' and pm.meta_key = '_badgeos_edd_trigger' AND pm.meta_value = %s", $this_trigger) );

    if( count( $triggered_achievements ) > 0 ) {
        /**
         * Update hook count for this user
         */
        $new_count = badgeos_update_user_trigger_count( $userID, $this_trigger, $blog_id );

        /**
         * Mark the count in the log entry
         */
        badgeos_post_log_entry( null, $userID, null, sprintf( __( '%1$s triggered %2$s (%3$dx)', 'bos-awp' ), $user_data->user_login, $this_trigger, $new_count ) );
    }

    foreach ( $triggered_achievements as $achievement ) {

        $parents = badgeos_get_achievements( array( 'parent_of' => $achievement->post_id ) );
        if( count( $parents ) > 0 ) {
            if( $parents[0]->post_status == 'publish' ) {
                badgeos_maybe_award_achievement_to_user( $achievement->post_id, $userID, $this_trigger, $blog_id, $args );
            }
        }

        //Rank
        $rank = $achievement;
        $parent_id = badgeos_get_parent_id( $rank->post_id );

        if( absint($parent_id) > 0) {
            $new_count = badgeos_ranks_update_user_trigger_count( $rank->post_id, $parent_id,$userID, $this_trigger, $blog_id, $args );
            badgeos_maybe_award_rank( $rank->post_id,$parent_id,$userID, $this_trigger, $blog_id, $args );
        }

        //Point
        $point = $achievement;
        $parent_id = badgeos_get_parent_id( $point->post_id );
        if( absint($parent_id) > 0) {
            if($point->post_type == 'point_award') {
                $new_count = badgeos_points_update_user_trigger_count($point->post_id, $parent_id, $userID, $this_trigger, $blog_id, 'Award', $args);
                badgeos_maybe_award_points_to_user($point->post_id, $parent_id, $userID, $this_trigger, $blog_id, $args);
            } else if($point->post_type == 'point_deduct') {
                $new_count = badgeos_points_update_user_trigger_count($point->post_id, $parent_id, $userID, $this_trigger, $blog_id, 'Deduct', $args);
                badgeos_maybe_deduct_points_to_user($point->post_id, $parent_id, $userID, $this_trigger, $blog_id, $args);
            }
        }

    }
}

/**
 * Check if user deserves a EDD trigger step
 *
 * @param $return
 * @param $user_id
 * @param $achievement_id
 * @param string $this_trigger
 * @param int $site_id
 * @param array $args
 * @return bool
 */
function badgeos_edd_user_deserves_edd_step( $return, $user_id, $achievement_id, $this_trigger = '', $site_id = 1, $args = array() ) {

    /**
     * If we're not dealing with a step, bail here
     */
    $post_type = get_post_type( $achievement_id );

    $bos_step_post_type = bos_edd_get_post_type('step');

    if ( $bos_step_post_type !=  $post_type ) {

        //TODO: Investigate why below 3 types inserted in achievements table, when same trigger is assigned to achievements and points
        if( in_array( $post_type, array( 'point_deduct', 'point_award', 'point_type' ) ) ) {
            $return = false;
        }

        return $return;
    }

    /**
     * Grab our step requirements
     */
    $requirements = badgeos_get_step_requirements( $achievement_id );

    /**
     * If the step is triggered by EDD actions...
     */
    if ( 'bosedd_triggers' == $requirements[ 'trigger_type' ] ) {

        /**
         * Do not pass go until we say you can
         */
        $return = false;

        /**
         * Unsupported trigger
         */
        if ( ! isset( $GLOBALS[ 'badgeos_edd' ]->triggers[ $this_trigger ] ) ) {
            return $return;
        }

        $edd_triggered = is_edd_trigger($achievement_id, $requirements, $args);

        /**
         * EDD requirements met
         */
        if ( $edd_triggered ) {

            $parent_achievement = badgeos_get_parent_of_achievement( $achievement_id );
            $parent_id = $parent_achievement->ID;

            $user_crossed_max_allowed_earnings = badgeos_achievement_user_exceeded_max_earnings( $user_id, $parent_id );
            if ( ! $user_crossed_max_allowed_earnings ) {
                $minimum_activity_count = absint( get_post_meta( $achievement_id, '_badgeos_count', true ) );
                if( ! isset( $minimum_activity_count ) || empty( $minimum_activity_count ) )
                    $minimum_activity_count = 1;

                $count_step_trigger = $requirements["badgeos_edd_trigger"];
                $activities = badgeos_get_user_trigger_count( $user_id, $count_step_trigger );
                $relevant_count = absint( $activities );

                $achievements = badgeos_get_user_achievements(
                    array(
                        'user_id' => absint( $user_id ),
                        'achievement_id' => $achievement_id
                    )
                );

                $total_achievments = count( $achievements );
                $used_points = intval( $minimum_activity_count ) * intval( $total_achievments );
                $remainder = intval( $relevant_count ) - $used_points;

                if ( absint( $remainder ) >= $minimum_activity_count ) {
                    $return = true;
                }

            } else {

                $return = 0;
            }

        }
    }

    return $return;
}

add_filter( 'user_deserves_achievement', 'badgeos_edd_user_deserves_edd_step', 15, 6 );

function bos_edd_badgeos_is_achievement_cb($return, $post) {

    $bos_step_post_type = bos_edd_get_post_type('step');

    if( get_post_type($post) == $bos_step_post_type ) {
        $return = true;
    }

    return $return;
}

add_filter('badgeos_is_achievement', 'bos_edd_badgeos_is_achievement_cb', 16, 2);

/**
 * Check if user does not have the same rank step already, and is eligible for the step
 *
 * @param $return_val
 * @param $step_id
 * @param $rank_id
 * @param $user_id
 * @param $this_trigger
 * @param $site_id
 * @param $args
 *
 * @return bool
 */
function badgeos_edd_user_deserves_rank_step($return_val, $step_id, $rank_id, $user_id, $this_trigger, $site_id, $args) {

    /**
     * If we're not dealing with a rank_requirement, bail here
     */

    $bos_rank_requirement_post_type = bos_edd_get_post_type('rank_requirement');

    if ( $bos_rank_requirement_post_type != get_post_type( $step_id ) ) {
        return $return_val;
    }

    /**
     * Grab our step requirements
     */
    $requirements = badgeos_get_rank_req_step_requirements( $step_id );

    /**
     * If the step is triggered by EDD actions...
     */
    if ( 'bosedd_triggers' == $requirements[ 'trigger_type' ] ) {

        /**
         * Do not pass go until we say you can
         */
        $return_val = false;

        /**
         * Unsupported trigger
         */
        if ( ! isset( $GLOBALS[ 'badgeos_edd' ]->triggers[ $this_trigger ] ) ) {
            return $return_val;
        }

        $edd_triggered = is_edd_trigger($step_id, $requirements, $args);

        /**
         * EDD requirements met
         */

        $return_val = $edd_triggered;

    }

    return $return_val;
}

add_filter('badgeos_user_deserves_rank_step', 'badgeos_edd_user_deserves_rank_step', 10, 7);

/**
 *
 * Check if user does not have the same rank already, and is eligible for the rank
 *
 * @param $completed
 * @param $step_id
 * @param $rank_id
 * @param $user_id
 * @param $this_trigger
 * @param $site_id
 * @param $args
 *
 * @return bool
 */
function badgeos_edd_user_deserves_rank_award($completed, $step_id, $rank_id, $user_id, $this_trigger, $site_id, $args) {

    /**
     * If we're not dealing with a rank_requirement, bail here
     */
    $bos_rank_requirement_post_type = bos_edd_get_post_type('rank_requirement');
    if ( $bos_rank_requirement_post_type != get_post_type( $step_id ) ) {
        return $completed;
    }

    /**
     * Get the requirement rank
     */
    $rank = badgeos_get_rank_requirement_rank( $step_id );

    /**
     * Get all requirements of this rank
     */
    $requirements = badgeos_get_rank_requirements( $rank_id );

    $completed = true;

    foreach( $requirements as $requirement ) {

        /**
         * Check if rank requirement has been earned
         */
        if( ! badgeos_get_user_ranks( array(
            'user_id' => $user_id,
            'rank_id' => $requirement->ID,
            'since' => strtotime( $rank->post_date ),
            'no_steps' => false
        ) ) ) {
            $completed = false;
            break;
        }
    }

    return $completed;
}

add_filter( 'badgeos_user_deserves_rank_award', 'badgeos_edd_user_deserves_rank_award', 15, 7 );

/**
 *
 * Check if user is eligible for the points award
 *
 * @param $return_val
 * @param $step_id
 * @param $credit_parent_id
 * @param $user_id
 * @param $this_trigger
 * @param $site_id
 * @param $args
 *
 * @return bool
 */
function badgeos_edd_user_deserves_credit_award_cb ($return_val, $step_id, $credit_parent_id, $user_id, $this_trigger, $site_id, $args) {
    /**
     * If we're not dealing with correct requirement type, bail here
     */
    $bos_point_award_post_type = bos_edd_get_post_type('point_award');
    if ( $bos_point_award_post_type != get_post_type( $step_id ) ) {
        return $return_val;
    }

    /**
     * Grab our step requirements
     */
    $requirements = badgeos_get_award_step_requirements( $step_id );

    /**
     * If the step is triggered by EDD actions...
     */
    if ( 'bosedd_triggers' == $requirements[ 'trigger_type' ] ) {

        /**
         * Do not pass go until we say you can
         */
        $return_val = false;

        /**
         * Unsupported trigger
         */
        if ( ! isset( $GLOBALS[ 'badgeos_edd' ]->triggers[ $this_trigger ] ) ) {
            return $return_val;
        }

        $edd_triggered = is_edd_trigger($step_id, $requirements, $args);

        /**
         * EDD requirements met
         */

        $return_val = $edd_triggered;

    }

    return $return_val;
}

add_filter( 'badgeos_user_deserves_credit_award', 'badgeos_edd_user_deserves_credit_award_cb', 10, 7 );

/**
 * Check if user is eligible for the points deduction
 *
 * @param $return_val
 * @param $step_id
 * @param $credit_parent_id
 * @param $user_id
 * @param $this_trigger
 * @param $site_id
 * @param $args
 *
 * @return bool
 */
function badgeos_edd_user_deserves_credit_deduct_cb ($return_val, $step_id, $credit_parent_id, $user_id, $this_trigger, $site_id, $args) {

    /**
     * If we're not dealing with correct requirement type, bail here
     */
    $bos_point_deduct_post_type = bos_edd_get_post_type('point_deduct');
    if ( $bos_point_deduct_post_type != get_post_type( $step_id ) ) {
        return $return_val;
    }

    /**
     * Grab our step requirements
     */
    $requirements = badgeos_get_deduct_step_requirements( $step_id );

    /**
     * If the step is triggered by EDD actions...
     */
    if ( 'bosedd_triggers' == $requirements[ 'trigger_type' ] ) {

        /**
         * Do not pass go until we say you can
         */
        $return_val = false;

        /**
         * Unsupported trigger
         */
        if ( ! isset( $GLOBALS[ 'badgeos_edd' ]->triggers[ $this_trigger ] ) ) {
            return $return_val;
        }

        $edd_triggered = is_edd_trigger($step_id, $requirements, $args);

        /**
         * EDD requirements met
         */

        $return_val = $edd_triggered;

    }

    return $return_val;

}

add_filter( 'badgeos_user_deserves_credit_deduct', 'badgeos_edd_user_deserves_credit_deduct_cb', 10, 7 );

/**
 * Check if a valid EDD trigger found for the given requirements
 *
 * @param $requirements
 * @param $args
 * @return bool
 */
function is_edd_trigger($step_id,$requirements, $args) {
    $return = false;
    $is_bos_edd_trigger = false;
    $requirements = badgeos_get_step_requirements( $step_id );

    //Trigger params
    $badgeos_edd_trigger = $requirements['badgeos_edd_trigger'];
    $bosedd_download_for_review_id = $requirements['bosedd_download_for_review_id'];
    $bosedd_specific_download_id = absint($requirements['bosedd_specific_download']);
    $bosedd_download_type = $requirements['bosedd_download_type'];

    $bosedd_download_price = $requirements['bosedd_download_price'];
    $bosedd_commission_price = $requirements['bosedd_commission_price'];
    $bosedd_cart_price = $requirements['bosedd_cart_price'];

    //Action hook params
    $payment_id = 0;
    if( in_array($badgeos_edd_trigger, array(
        'purchase_specific_download',
        'purchase_download_of_specific_type',
        'purchase_download_price'
    )) ) {

        $download_id = $args[0];
        $payment_id = $args[1];
        $download_type = $args[2];
        $download = $args[3];
        $cart_index = $args[4];

    } elseif(in_array($badgeos_edd_trigger, array(
        'new_purchase',
        'purchase_cart_price'
    )) ) {
        $payment_id = $args[0];
        $payment = $args[1];
        $customer = $args[2];
    }


    if( empty($bosedd_download_type) ) {
        $bosedd_download_type = 'default';
    }


    if($badgeos_edd_trigger == 'new_purchase') {
        $return = true;
    } elseif( $badgeos_edd_trigger == 'purchase_specific_download' && !empty($bosedd_specific_download_id) && !empty($download_id) ) {
        if( $download_id == $bosedd_specific_download_id ) {
            $return = true;
        }
    } elseif( $badgeos_edd_trigger == 'purchase_download_of_specific_type' && !empty($bosedd_download_type) && !empty($download_type) ) {

        if( $download_type == $bosedd_download_type ) {
            $return = true;
        }
    } elseif( $badgeos_edd_trigger == 'purchase_download_price' && !empty($bosedd_download_price) && !empty($payment_id) ) {
        // Basic payment meta
        $payment_meta = edd_get_payment_meta( $payment_id );

        // Cart details
        $cart_items = edd_get_payment_meta_cart_details( $payment_id );

        if(!empty($payment_meta)) {
            if( isset($payment_meta['cart_details']) && !empty($payment_meta['cart_details']) ) {
                foreach ($payment_meta['cart_details'] as $cart_detail) {
                    if( $cart_detail['item_price'] == $bosedd_download_price ) {
                        $return = true;
                        break;
                    }
                }
            }
        }

    } elseif( $badgeos_edd_trigger == 'purchase_cart_price' && !empty($bosedd_cart_price) && !empty($payment_id) ) {
        // Basic payment meta
        $payment_meta = edd_get_payment_meta( $payment_id );

        // Cart details
        $cart_items = edd_get_payment_meta_cart_details( $payment_id );

        $cart_total=0;
        if(!empty($payment_meta)) {
            if( isset($payment_meta['cart_details']) && !empty($payment_meta['cart_details']) ) {
                foreach ($payment_meta['cart_details'] as $cart_detail) {
                    $cart_total += $cart_detail['subtotal'];
                }
            }
        }

        if($bosedd_cart_price == $cart_total) {
            $return = true;
        }
    }

    return $return;
}


function bos_edd_get_post_type($post_type) {

    $bos_post_type_settings = array(
        'step' => 'achievement_step_post_type',
        'rank_requirement' => 'ranks_step_post_type',
        'point_award' => 'points_award_post_type',
        'point_deduct' => 'points_deduct_post_type'
    );

    $bos_settings = get_option( 'badgeos_settings', array() );

    $bos_post_type = $bos_settings[ $bos_post_type_settings[$post_type] ];

    if( isset($bos_post_type) && !empty($bos_post_type) ) {
        $post_type = $bos_post_type;
    }

    return $post_type;
}