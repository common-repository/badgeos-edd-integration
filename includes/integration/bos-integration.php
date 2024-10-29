<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class BadgeOS_EDD_Integration
 */
class BadgeOS_EDD_Integration {

    /**
	 * BadgeOS EDD Triggers
	 *
	 * @var array
	 */
	public $triggers = array();

	/**
	 * Actions to forward for splitting an action up
	 *
	 * @var array
	 */
	public $actions = array();


    /**
     * BadgeOS_EDD_Integration constructor.
     */
    public function __construct() {

        /**
         * EDD Action Hooks
         */
		$this->triggers = array(
            'new_purchase' => __( 'New Purchase', 'bosedd' ),
            'purchase_specific_download' => __( 'Purchase Specific Download', 'bosedd' ),
            'purchase_download_of_specific_type' => __( 'Purchase Download of Specific Type', 'bosedd' ),
            'purchase_download_price' => __( 'When User Purchase Specific Price Download', 'bosedd' ),
            'purchase_cart_price' => __( 'When User Shops for Price', 'bosedd' ),
		);

		/**
         * Actions that we need split up
         */
		$this->actions = array(
		    //$payment_id, $payment, $customer
			'edd_complete_purchase' =>  array(
			    'actions' => array(
			        'new_purchase',
                    'purchase_cart_price'
                )
            ),
			//$download['id'], $payment_id, $download_type, $download, $cart_index
			'edd_complete_download_purchase' =>  array(
			    'actions' => array(
			        'purchase_specific_download',
                    'purchase_download_of_specific_type',
                    'purchase_download_price'
                )
            ),
        );
        
        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 11 );

    }

    /**
     * include files if plugin meets requirements
     */
	public function plugins_loaded() {

		if ( $this->meets_requirements() ) {

            if( file_exists( BOSEDD_INCLUDES_DIR . 'integration/rules-engine.php' ) ) {
                require_once ( BOSEDD_INCLUDES_DIR . 'integration/rules-engine.php' );
            }

            if( file_exists( BOSEDD_INCLUDES_DIR . 'integration/steps-ui.php' ) ) {
                require_once ( BOSEDD_INCLUDES_DIR . 'integration/steps-ui.php' );
            }

			$this->action_forwarding();
		}
    }
    
    /**
     * Check if BadgeOS is available
     *
     * @return bool
     */
	public static function meets_requirements() {

		if ( !class_exists( 'BadgeOS' ) || !function_exists( 'badgeos_get_user_earned_achievement_types' ) ) {

			return false;
		} elseif ( !class_exists( 'Easy_Digital_Downloads' ) ) {

			return false;
		}

		return true;
	}

    /**
     * Forward WP actions into a new set of actions
     */
	public function action_forwarding() {
		foreach ( $this->actions as $action => $args ) {
			$priority = 10;
			$accepted_args = 20;

			if ( is_array( $args ) ) {
				if ( isset( $args[ 'priority' ] ) ) {
					$priority = $args[ 'priority' ];
				}

				if ( isset( $args[ 'accepted_args' ] ) ) {
					$accepted_args = $args[ 'accepted_args' ];
				}
			}

			add_action( $action, array( $this, 'action_forward' ), $priority, $accepted_args );
		}
	}

    /**
     * Forward a specific WP action into a new set of actions
     *
     * @return mixed|null
     */
	public function action_forward() {
		$action = current_filter();
		$args = func_get_args();
		$action_args = array();

		if ( isset( $this->actions[ $action ] ) ) {
			if ( is_array( $this->actions[ $action ] )
				 && isset( $this->actions[ $action ][ 'actions' ] ) && is_array( $this->actions[ $action ][ 'actions' ] )
				 && !empty( $this->actions[ $action ][ 'actions' ] ) ) {
				foreach ( $this->actions[ $action ][ 'actions' ] as $new_action ) {
			
					$action_args = $args;

					array_unshift( $action_args, $new_action );

					call_user_func_array( 'do_action', $action_args );
				}

				return null;
			} elseif ( is_string( $this->actions[ $action ] ) ) {
				$action =  $this->actions[ $action ];
			}
		}
		array_unshift( $args, $action );

		return call_user_func_array( 'do_action', $args );
	}
}

/**
 * Initiate plugin main class
 */
$GLOBALS['badgeos_edd'] = new BadgeOS_EDD_Integration();