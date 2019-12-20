<?php
/*
Plugin Name: Paysenz
Plugin URI:  http://unlocklive.com
Description: Allow mobile banking (Bkash, Rocket), Visa & Mastercard payments within your woocommerce stores and wordpress. Paysenz combines the open mobile banking api, open visa api to bring you the latest in Payments.
Version:     1.0.1
Author:      Unlocklive Team
Author URI:  https://paysenz.net
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: paysenz_wpg
*/
use paysenz\payment\gateway\Paysenz;
require_once('inc/paysenz-class.php');

defined('ABSPATH') or die('Only a foolish person try to access directly to see this white page. :-) ');

/**
 * Plugin language
 */
load_plugin_textdomain( 'paysenz_wpg', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );


if( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){
    /**
     * Paysenz gateway register
     */
    add_filter('woocommerce_payment_gateways', 'paysenz_payment_gateways');
    function paysenz_payment_gateways( $gateways ){
        $gateways[] = 'Paysenz_Gateway';
        return $gateways;
    }

    add_action('plugins_loaded', 'paysenz_gateway_plugin_activation');

    function paysenz_gateway_plugin_activation(){

        class Paysenz_Gateway extends WC_Payment_Gateway
        {

            public $order_status;
            public $cliend_id;
            public $client_secret;
            public $client_username;
            public $client_password;
            public $success_url;


            public function __construct()
            {
                $this->id = 'paysenz__payment_gateway';
                $this->title = $this->get_option('title', 'Paysenz');
                $this->description = $this->get_option('description', 'Paysenz');
                $this->method_title = esc_html__("Paysenz", "paysenz_wpg");
                $this->method_description = esc_html__("Paysenz Options", "paysenz_wpg");
                $this->icon = plugins_url('assets/images/paysenz-logo.png', __FILE__);
                $this->has_fields = true;

                $this->paysenz_gateway_options_fields();
                $this->init_settings();
                $this->order_status = $this->get_option('order_status');
                $this->cliend_id = $this->get_option('CLIENT_ID');
                $this->client_secret = $this->get_option('CLIENT_SECRET');
                $this->client_username = $this->get_option('CLIENT_USER_NAME');
                $this->client_password = $this->get_option('CLIENT_PASSWORD');
                $this->success_url = $this->get_option('SUCCESS_URL');

                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

                // You can also register a webhook here
                add_action( 'woocommerce_api_paysenz-payment-complete', array( $this, 'webhook' ) );

            }
            public function paysenz_gateway_options_fields(){
                $this->form_fields = array(
                    'enabled' 	=>	array(
                        'title'		=> esc_html__( 'Enable/Disable', "paysenz_wpg" ),
                        'type' 		=> 'checkbox',
                        'label'		=> esc_html__( 'Paysenz', "paysenz_wpg" ),
                        'default'	=> 'yes'
                    ),
                    'title' 	=> array(
                        'title' 	=> esc_html__( 'Title', "paysenz_wpg" ),
                        'type' 		=> 'text',
                        'default'	=> esc_html__( 'Paysenz', "paysenz_wpg" )
                    ),

                    'CLIENT_ID' 	=> array(
                        'title' 	=> esc_html__( 'Client ID', "paysenz_wpg" ),
                        'type' 		=> 'text',
                    ),
                    'CLIENT_SECRET' 	=> array(
                        'title' 	=> esc_html__( 'Client Secret', "paysenz_wpg" ),
                        'type' 		=> 'text',
                    ),
                    'CLIENT_USER_NAME' 	=> array(
                        'title' 	=> esc_html__( 'Client Username', "paysenz_wpg" ),
                        'type' 		=> 'text',
                    ),
                    'CLIENT_PASSWORD' 	=> array(
                        'title' 	=> esc_html__( 'Client Password', "paysenz_wpg" ),
                        'type' 		=> 'text',
                    ),
                    'SUCCESS_URL' 	=> array(
                        'title' 	=> esc_html__( 'Success URL', "paysenz_wpg" ),
                        'type' 		=> 'text',
                    ),
                );
            }

            /*
             * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
             */
            public function payment_scripts() {


            }


            public function process_payment( $order_id ) {

                $paymentSOption = array(
                    'CLIENT_ID'         => $this->get_option('CLIENT_ID'),
                    'CLIENT_SECRET'     => $this->get_option('CLIENT_SECRET'),
                    'CLIENT_USER_NAME'  => $this->get_option('CLIENT_USER_NAME'),
                    'CLIENT_PASSWORD'   => $this->get_option('CLIENT_PASSWORD'),
                    'SUCCESS_URL'       => $this->get_option('SUCCESS_URL')
                );

                $paysenz_urls      = Paysenz::paysenz_url();

                global  $woocommerce;
                $order              = new WC_Order( $order_id );

                $billing_data       = $order->data;
                $billing_info       = $order->data['billing'];

                $tran_id            = $order_id.'-'.time();
                $type               = 'WooCommerce Order';
                $model_name         = '';
                $buyer_address      = $billing_info['address_1'] ? $billing_info['address_1'] : $billing_info['city'];
                $buyer_postcode     = $billing_info['postcode'] ? $billing_info['postcode'] : $billing_info['state'];

                $make_array = array(
                    'buyer_name'                => $billing_info['first_name'].' '.$billing_info['last_name'],
                    'buyer_email'               => $billing_info['email'],
                    'buyer_address'             => $buyer_address,
                    'cus_city'                  => $billing_info['city'],
                    'cus_state'                 => $billing_info['state'],
                    'cus_postcode'              => $buyer_postcode,
                    'cus_country'               => $billing_info['country'],
                    'buyer_contact_number'      => $billing_info['phone'],
                    'client_id'                 => $paymentSOption['CLIENT_ID'],
                    'order_id_of_merchant'      => Paysenz::ORDER_PREFIX.$tran_id,
                    'amount'                    => $billing_data['total'],
                    'currency_of_transaction'   => 'BDT',
                    'callback_success_url'      => site_url().'/wc-api/paysenz-payment-complete',
                    'callback_fail_url'         => $paymentSOption['SUCCESS_URL'],
                    'callback_cancel_url'       => $paymentSOption['SUCCESS_URL'],
                    'callback_ipn_url'          => site_url().'/wc-api/paysenz-payment-complete',
                    'order_details'             => 'Payment for ApplicationID:'.$order_id,
                    'expected_response_type'    => 'JSON',
                    'custom_1'                  => $tran_id,
                    'custom_2'                  => $type,
                    'custom_3'                  => $model_name,
                    'custom_4'                  => $order_id,
                );

                $make_array_json      = Paysenz::paysenz_payment_get_realURL($make_array, $paymentSOption);

                $expected_response    = '';
                if($make_array_json->expected_response){
                    $expected_response = $make_array_json->expected_response;
                }else{

                    wc_add_notice(  'Connection error! Please Check Your Paysenz Account Information on Payment Settings from Admin Area.', 'error' );
                    return;
                }

                $status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;
                // Mark as on-hold (we're awaiting the bKash)

                $order->update_status( $status, esc_html__( 'Checkout with paysenz payment. ', "paysenz_wpg" ) );

                // Reduce stock levels
                //$order->reduce_order_stock();

                // Remove cart
                $woocommerce->cart->empty_cart();

                // Return thankyou redirect
                return array(
                    'result' => 'success',
                    'redirect' => $expected_response
                );

            }

            /*
             * In case you need a webhook, like PayPal IPN etc
             */
            public function webhook() {

                if (!empty($_POST['payment_status']) && sanitize_text_field($_POST['payment_status']) == 'Successful') {
                    $order = wc_get_order( sanitize_text_field($_POST['custom_4']) );

                    // The text for the note
                    $make_notes = 'Payment Status: '.sanitize_text_field($_POST['payment_status']).', Total Amount: '.sanitize_text_field($_POST['amount']).', Paysenz Txn ID: '.sanitize_text_field($_POST['psz_txnid']).', Merchant Txn ID: '.sanitize_text_field($_POST['merchant_txnid']).', Type: '.sanitize_text_field($_POST['payment_type']).', Currency: '.sanitize_text_field($_POST['merchant_currency']).'';

                    // Add the note
                    $order->add_order_note( $make_notes );
                    
                    // save
                    $order->save();

                    $order->payment_complete();

                    if($this->get_option('SUCCESS_URL')){
                        $redirect_url   = $this->get_option('SUCCESS_URL');
                    }else{
                        $redirect_url   = site_url();
                    }
                    wp_redirect($redirect_url);

                }else if (!empty($_GET['payment_status']) && sanitize_text_field($_GET['payment_status']) == 'Successful') {

                    $order = wc_get_order( sanitize_text_field($_GET['custom_4']) );

                    // The text for the note
                    $make_notes = 'Payment Status: '.sanitize_text_field($_POST['payment_status']).', Total Amount: '.sanitize_text_field($_POST['amount']).', Paysenz Txn ID: '.sanitize_text_field($_POST['psz_txnid']).', Merchant Txn ID: '.sanitize_text_field($_POST['merchant_txnid']).', Type: '.sanitize_text_field($_POST['payment_type']).', Currency: '.sanitize_text_field($_POST['merchant_currency']).'';

                    // Add the note
                    $order->add_order_note( $make_notes );
                    
                    // save
                    $order->save();

                    $order->payment_complete();
                    //$order->wc_reduce_stock_levels();

                    if($this->get_option('SUCCESS_URL')){
                        $redirect_url   = $this->get_option('SUCCESS_URL');
                    }else{
                        $redirect_url   = site_url();
                    }
                    wp_redirect($redirect_url);

                }

            }


        }
    }
}