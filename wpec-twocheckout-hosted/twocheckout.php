<?php
/*
Plugin Name: 2Checkout Payment Gateway for WP e-Commerce
Plugin URI: https://github.com/craigchristenson/2checkout-wp-e-commerce
Description: Integrate the 2Checkout Payment Gateway into WordPress and WP e-Commerce.
Version: 1.0
Author: Craig Christenson
Author URI:  https://github.com/craigchristenson
*/

defined( 'WPINC' ) || die;

class WPSC_TwoCheckout {

    private static $instance;

    private function __construct() {}

    public static function get_instance() {

        if  ( ! isset( self::$instance ) && ! ( self::$instance instanceof WPSC_TwoCheckout ) ) {
            define( 'TC_WPSC_PLUGIN_DIR', dirname( __FILE__ ) );
            self::$instance = new WPSC_TwoCheckout;
            add_action( 'wpsc_init', array( self::$instance, 'init' ), 2 );
            add_action('init', 'twocheckout_hosted_callback');
            add_action('init', 'twocheckout_hosted_results');
            add_filter( 'wpsc_merchants_modules', array( self::$instance, 'register_gateway' ), 100 );
        }

        return self::$instance;
    }

    public function init() {
        include_once TC_WPSC_PLUGIN_DIR . '/wpsc_twocheckout_hosted_merchant.php';
    }

    public function register_gateway( $gateways ) {

        $num = max( array_keys( $gateways ) ) + 1;

        $gateways[ $num ] = array(
            'name'                   => '2Checkout',
            'api_version'            => 2.0,
            'has_recurring_billing'  => true,
            'display_name'           => __( 'Credit Card' ),
            'image'                  => 'https://www.2checkout.com/upload/images/paymentlogoshorizontal.png',
            'wp_admin_cannot_cancel' => true,
            'requirements' => array(
                'php_version' => 5.0
            ),
            'class_name'      => 'wpsc_merchant_twocheckout_hosted',
            'form'            => 'wpsc_twocheckout_settings_form',
            'submit_function' => 'wpsc_save_twocheckout_settings',
            'internalname'    => 'wpsc_twocheckout_hosted',
            'supported_currencies' => array(
                'currency_list' =>  array('ARS', 'AUD', 'BRL', 'GBP', 'CAD', 'DKK', 'EUR', 'HKD', 'INR', 'ILS', 'JPY', 'LTL', 'MYR', 'MXN', 'NZD', 'NOK', 'PHP', 'RON', 'RUB', 'SGD', 'ZAR', 'SEK', 'CHF', 'TRY', 'AED', 'USD'),
                'option_name' => 'wpsc_twocheckout_curcode'
            )
        );

        return $gateways;
    }

}

add_action( 'wpsc_pre_init', 'WPSC_TwoCheckout::get_instance' );