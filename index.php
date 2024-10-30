<?php

/**
 * Plugin Name: IDPay
 * Plugin URI: https://idpay.my
 * Description: Enable payment by salary deduction. Currently IDPay service is only available for customer working in Malaysia government and public sector.
 * Version: 1.10.0
 * Author: IDSB Digital Sdn. Bhd.
 * Author URI: https://idsb.my
 * WC requires at least: 2.6.0
 * WC tested up to: 5.4
 **/

if (!defined('ABSPATH'))
    exit;

add_action('plugins_loaded', 'idpay_init', 0);

function idpay_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

    /**
     * Gateway class
     */
    class WC_IDpay extends WC_Payment_Gateway {

        var $notify_url;
        var $schedule_interval;

        public function __construct() {
            // Go wild in here
            $this->id = 'idpay';
            $this->method_title = __('IDPay', 'IDPay');
            $this->icon = 'https://idpay.my/images/logo/logo.png';
            $this->has_fields = false;
            $this->method_description = __('Enable payment by salary deduction. Currently IDPay service is only available for customer that working as government servants in Malaysia.', 'IDPay');

            if (version_compare(WOOCOMMERCE_VERSION, '2.4.0', '>=')) {
                $this->notify_url = str_replace('http', 'http', add_query_arg('wc-api', 'wc_idpay', home_url('/')));
            } else {
                $this->notify_url = str_replace('http', 'http', WC()->api_request_url('wc_idpay'));
            }

            $this->init_form_fields();
            $this->init_settings();

            $this->title = @$this->settings['title'];
            $this->description = @$this->settings['description'];
            $this->merchant_id = @$this->settings['merchant_id'];
            $this->secret_key = @$this->settings['secret_key'];
            $this->schedule_interval = @$this->settings['schedule_interval'];

            //update for woocommerce >2.0
            add_action('woocommerce_api_wc_idpay', array($this, 'idpay_response'));

            if ( is_admin() ) {
                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
                }
            }
        }

        function init_form_fields() {
            
            $min     = 2;
            $max     = 60;
            $tenures = array();
            $tenure  = $min;
            $tenor   = $min - 1;

            while( true ) {
                $tenor++;
                $tenures = array_merge( $tenures, array( $tenor => $tenor . ' Months'));

                if( $tenor == $max )
                    break;
            }

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable / Disable', 'IDPay'),
                    'type' => 'checkbox',
                    'label' => __('Enable IDPay payment gateway', 'IDPay'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title', 'IDPay'),
                    'type' => 'text',
                    'description' => __('Payment title the customer will see during the checkout process.', 'IDPay'),
                    'desc_tip' => true,
                    'default' => __('IDPay', 'IDPay')
                ),
                'description' => array(
                    'title' => __('Description', 'IDPay'),
                    'type' => 'textarea',
                    'description' => __('Payment description the customer will see during the checkout process.', 'IDPay'),
                    'desc_tip' => true,
                    'default' => __('Pay by salary deduction through IDPay Payment Gateway. Currently IDPay service is only available for customer that working as government servants in Malaysia.', 'IDPay'),
                    'css'      => 'max-width:400px;'
                ),
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'IDPay'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __('This is the merchant ID that you can obtain from Merchant Profile > Shopping Cart Integration page in IDPay.')
                ),
                'secret_key' => array(
                    'title' => __('Secret Key', 'IDPay'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __('This is the secret key that you can obtain from Merchant Profile > Shopping Cart Integration page in IDPay.')
                ),
                'schedule_interval' => array(
                    'title' => __('Schedule Time Interval', 'IDPay'),
                    'type' => 'select',
                    'options' => array(
                        'hourly' => __('Hourly', 'IDPay'),
                        'twicedaily' => __('Twice a day', 'IDPay'),
                        'daily' => __('Once a day', 'IDPay'),
                    ),
                    'description' => __('This controls how many times system check the payment status from IDPay.', 'IDPay'),
                    'desc_tip' => true,
                    'default' => 'hourly'
                ),
                'available_tenures' => array(
                    'title' => __('Allowable Tenures', 'IDPay'),
                    'description' => __('Allowable Tenures for customer during checkout process. Leave blank to show all available tenures in IDPay. Tenure availability depends on enabled tenure in IDPay.', 'IDPay'),
                    'type' => 'multiselect',
                    'default' => '',
                    'desc_tip' => true,
                    'options' => $tenures,
                ),
                'api_response' => array(
                    'title' => __('API Response URL', 'IDPay'),
                    'type' => 'text',
                    'description' => __("This is an API Response URL to use as a web hook."),
                    'desc_tip' => true,
                    'std' => __($this->notify_url, 'IDPay'),
                    'default' => __($this->notify_url, 'IDPay'),
                    'custom_attributes' => array('readonly' => 'readonly'),
                ),
            );
        }

        /**
         * Process the payment and return the result
         * */
        function process_payment($order_id) {
            $order = new WC_Order($order_id);
            $price = $order->order_total;
            $available_tenures = '';

            if (isset($this->settings['available_tenures']) && is_array($this->settings['available_tenures'])) {
                $available_tenures = implode(',', $this->settings['available_tenures']);
            }

            $redirect_url = $this->notify_url . '&api=1';
            $post_fields = array(
                'product_type' => 'Payment for order ' . $order_id,
                'product_price' => $price,
                'product_qty' => 1,
                'allowing_tenures' => $available_tenures,
                'callback_url' => $redirect_url,
                'order_id' => $order_id,
            );

            if (!isset($_SESSION)) {
                session_start();
            }

            $_SESSION['idpay-order-id'] = $order_id;
            $checkOutLinkEndPoint = "https://idpay.my/api/v1/get/checkout/link";
            $response = $this->curl_call($checkOutLinkEndPoint, $post_fields);
            if ($response->status == 'ok' && $order) {
                return array('result' => 'success', 'redirect' => ($response->data->payment_link));
            } else {
                wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
            }
        }

        /**
         *  There are no payment fields for IDpay, but we want to show the description if set.
         * */
        function payment_fields() {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }

        /**
         * IDPAY server callback
         * */
        function idpay_response() {
            if ($_GET['api'] == 1) {
                global $woocommerce;

                if (!isset($_SESSION)) {
                    session_start();
                }

                $order_id = $_SESSION['idpay-order-id'];
                $order = new WC_Order($order_id);

                if ($order) {
                    $order->add_order_note('Request for salary deduction has been submitted to IDPay. Waiting for eligibility check.');
                    $order->update_status('on-hold');
                    $woocommerce->cart->empty_cart();
                }
                wp_redirect($order->get_checkout_order_received_url());
            }
            //API response
            else {
                $order_id = intval($_POST['order_id']);
                if (!$order_id) {
                    $order_id = 0;
                }
                $status_type = sanitize_text_field($_POST['status']);
                $transaction_number = sanitize_text_field($_POST['transaction_number']);
                if (!empty($order_id)) {
                    global $woocommerce;
                    $order = new WC_Order($order_id);
                    switch ($status_type) {
                        case 'active':
                            $order->add_order_note('Customer application has been approved by merchant');
                            $order->update_status('processing');
                            $order->payment_complete();
                            break;
                        case 'ready_for_salary_deduction':
                            if (get_post_meta($order_id, '_idpay_status', true) != $status_type) {
                                $order->add_order_note('Waiting for merchant to approve customer application');
                                update_post_meta($order_id, '_idpay_status', $status_type, true);
                            }
                            break;
                        case 'eligible':
                            if (get_post_meta($order_id, '_idpay_status', true) != $status_type) {
                                $order->add_order_note('Customer is eligible for this transaction. Please provide AG/BPA form to customer and deliver the signed form to IDPay office to process');
                                update_post_meta($order_id, '_idpay_status', $status_type, true);
                            }
                            break;
                        case 'not_eligible':
                            $order->add_order_note('Customer is not eligible for this transaction');
                            $order->update_status('cancelled');
                            break;
                        case 'cancel':
                            $order->add_order_note('Customer application has been cancelled by merchant');
                            $order->update_status('cancelled');
                            break;
                        case 'submitted':
                            if (!get_post_meta($order_id, '_idpay_transaction', true)) {
                                $order->add_order_note('IDPay transaction number is '. $transaction_number);
                                update_post_meta($order_id, '_idpay_transaction', $transaction_number, true);
                                update_post_meta($order_id, '_idpay_status', $status_type, true);
                            }
                            break;
                    }
                }
            }
            exit();
        }

        /**
         * IDPAY send request to server
         * */
        function curl_call($url, $post_fields) {
            $args = array(
                'body' => $post_fields,
                'timeout' => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(
                    'Authorization' => "Basic " . base64_encode($this->settings['merchant_id'] . ":" . $this->settings['secret_key']),
                ),
            );
            $response = wp_remote_post($url, $args);
            if(!is_wp_error( $response ) && isset($response['body'])) {
                $response = json_decode($response['body']);
                return $response;
            } else {
                return false;
            }
        }

    }

    /**
     * Add the Gateway to WooCommerce
     * */
    add_filter('woocommerce_payment_gateways', 'add_idpay_to_woocommerce');
    function add_idpay_to_woocommerce($methods) {
        $methods[] = 'WC_IDPay';
        return $methods;
    }

    // Create a scheduled event (if it does not exist already)
    function activate_idpay_cronjob() {
        $plugin = new WC_IDpay();
        if (!wp_next_scheduled('idpay_cronjob')) {
            wp_schedule_event(time(), $plugin->schedule_interval, 'idpay_cronjob');
        }
    }

    // Make sure it's called whenever WordPress loads
    add_action('wp', 'activate_idpay_cronjob');

    // Unschedule event upon plugin deactivation
    function deactivate_idpay_cronjob() {
        // Find out when the last event was scheduled
        $timestamp = wp_next_scheduled('idpay_cronjob');
        // Unschedule previous event if any
        wp_unschedule_event($timestamp, 'idpay_cronjob');
    }

    register_deactivation_hook(__FILE__, 'deactivate_idpay_cronjob');

    // Here's the function we'd like to call with our cron job
    function idpay_check_response() {
        # If the parent WC_Payment_Gateway class doesn't exist it means WooCommerce is not installed on the site, so do nothing
        if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
            return;
        }

        global $woocommerce;
        $plugin = new WC_IDpay();

        $customer_orders = wc_get_orders(array(
            'limit' => -1,
            'status' => 'on-hold'
        ));

        // Iterating through each Order with on-hold status
        foreach ($customer_orders as $order) {
            $order_id = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
            $post_fields = array(
                'order_id' => $order_id,
            );
            $statusCheckEndPoint = "https://idpay.my/api/v1/transaction/order/status";
            $response = $plugin->curl_call($statusCheckEndPoint, $post_fields);
            if (isset($response->status)) {
                $order = new WC_Order($order->id);
                $status_type = $response->status;
                $transaction_number = $response->transaction_number;
                switch ($status_type) {
                    case 'active':
                        $order->add_order_note('Customer application has been approved by merchant');
                        $order->update_status('processing');
                        $order->payment_complete();
                        break;
                    case 'ready_for_salary_deduction':
                        if (get_post_meta($order_id, '_idpay_status', true) != $status_type) {
                            $order->add_order_note('Waiting for merchant to approve customer application');
                            update_post_meta($order_id, '_idpay_status', $status_type, true);
                        }
                        break;
                    case 'eligible':
                        if (get_post_meta($order_id, '_idpay_status', true) != $status_type) {
                            $order->add_order_note('Customer is eligible for this transaction. Please provide AG/BPA form to customer and deliver the signed form to IDPay office to process');
                            update_post_meta($order_id, '_idpay_status', $status_type, true);
                        }
                        break;
                    case 'not_eligible':
                        $order->add_order_note('Customer is not eligible for this transaction');
                        $order->update_status('cancelled');
                        break;
                    case 'cancel':
                        $order->add_order_note('Customer application has been cancelled by merchant');
                        $order->update_status('cancelled');
                        break;
                    case 'submitted':
                        if (!get_post_meta($order_id, '_idpay_transaction', true)) {
                            $order->add_order_note('IDPay transaction number is '. $transaction_number);
                            add_post_meta($order_id, '_idpay_transaction', $transaction_number, true);
                            update_post_meta($order_id, '_idpay_status', $status_type, true);
                        }
                        break;
                }
            }
        }
    }

    // Hook that function onto our scheduled event
    add_action('idpay_cronjob', 'idpay_check_response');

    // Use WC builtin select2 plugin for multiselect and select
    add_action( 'admin_head', function() {
        if( is_admin() && isset($_GET['section']) && $_GET['section'] == 'idpay' ) {
        ?>
            <script>
                jQuery(function($){
                    $('#woocommerce_idpay_schedule_interval').select2();
                    $('#woocommerce_idpay_available_tenures').select2();
                });
            </script>
        <?php }
    });
}

?>
