<?php
abstract class model_methods_Abstract extends base {

    public $code;
    public $title;
    public $description;
    public $enabled;
    private $_check;
    private $_currentCharge;


    public function getEnabled()
    {
        return   defined('MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS') &&
        (MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS == 'True') ? true : false;
    }

    abstract public function javascript_validation();

    abstract public function selection();

    abstract public function pre_confirmation_check();

    abstract public function confirmation();

    abstract public function process_button();

    public function before_process()
    {
        global  $order,  $_POST;
        $config = array();

            $amount = $order->info['total'];
            $amountCents = (int) ($amount *100);
            $config['authorization'] = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY;
            $config['mode'] = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER;
            $products = array();
            $i = 1;

            foreach($order->products as $product) {

                $products[] = array (
                    'name'       =>    $product['name'],
                    'sku'        =>    $product['id'],
                    'price'      =>    $product['final_price'],
                    'quantity'   =>    $product['qty'],
                );
                $i++;
            }
            $config['postedParam'] = array (
                'email'           => $order->customer['email_address'] ,
                'value'           => $amountCents,
                'currency'        => $order->info['currency'] ,
                'products'        => $products,
                'shippingDetails' => array (
                    'addressLine1'  =>  $order->delivery['street_address'],
                    'addressLine2'  => $order->delivery['suburb'],
                    'postcode'      =>  $order->delivery['postcode'],
                    'country'       =>  $order->delivery['country']['iso_code_2'],
                    'city'          =>  $order->delivery['city'],
                    'phone'         =>  array('number' => $order->customer['telephone']),
                 )

            );

            if (MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_METHOD == 'Authorize and Capture') {
                $config = array_merge( $this->_captureConfig(),$config);
            } else {
                $config = array_merge( $this->_authorizeConfig(),$config);
            }

        return $config;
    }
    protected function _placeorder($config)
    {
        global $messageStack,$order;

        //building charge

        $respondCharge = $this->_createCharge($config);

        $this->_currentCharge = $respondCharge;

        if( $respondCharge->isValid()) {


            if (preg_match('/^1[0-9]+$/', $respondCharge->getResponseCode())) {
                $order->info['order_status'] = MODULE_PAYMENT_CHECKOUAPIPAYMENT_REVIEW_ORDER_STATUS_ID;
            }
            else{

                $messageStack->add_session('header', MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ERROR_TITLE, 'error');
                $messageStack->add_session('header', MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ERROR_GENERAL, 'error');
                $messageStack->add_session('header', $respondCharge->getResponseMessage(), 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $respondCharge->getErrorCode(), 'SSL'));

            }

        } else  {

            $messageStack->add_session('header', MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ERROR_TITLE, 'error');
            $messageStack->add_session('header', MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ERROR_GENERAL, 'error');
            $messageStack->add_session('header', $respondCharge->getExceptionState()->getErrorMessage(), 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $respondCharge->getErrorCode(), 'SSL'));
        }

    }
    protected function _createCharge($config)
    {
        $Api = CheckoutApi_Api::getApi(array('mode'=> MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER));

        return $Api->createCharge($config);
    }
    protected function _captureConfig()
    {
        $to_return['postedParam'] = array (
            'autoCapture' => CheckoutApi_Client_Constant::AUTOCAPUTURE_CAPTURE,
            'autoCapTime' => MODULE_PAYMENT_CHECKOUAPIPAYMENT_AUTOCAPTIME
        );

        return $to_return;
    }

    protected function _authorizeConfig()
    {
        $to_return['postedParam'] = array(
            'autoCapture' => CheckoutApi_Client_Constant::AUTOCAPUTURE_AUTH,
            'autoCapTime' => 0
        );
        return $to_return;
    }
    public function after_process()
    {
        global $insert_id, $customer_id, $stripe_result;
        if($this->_currentCharge) {
            $status_comment = array('Transaction ID: ' . $this->_currentCharge->getId(),
                'Transaction has been process using "' . MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TEXT_PUBLIC_TITLE .'" and paid with  card '. $this->_currentCharge->getCard()->getPaymentMethod(),
                'Response code:' . $this->_currentCharge->getResponseCode(),
                'Response Message: '. $this->_currentCharge->getResponseMessage());

            $sql_data_array = array('orders_id' => $insert_id,
                'orders_status_id' => MODULE_PAYMENT_CHECKOUAPIPAYMENT_REVIEW_ORDER_STATUS_ID,
                'date_added' => 'now()',
                'customer_notified' => '0',
                'comments' => implode("\n", $status_comment));


            $Api = CheckoutApi_Api::getApi(
                    array( 'mode'          => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER,
                           'authorization' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY)
            );

            $chargeUpdated = $Api->updateTrackId($this->_currentCharge,$insert_id);

            zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
        }
        $this->_currentCharge  = '';

    }

    public function get_error()
    {

    }
    public function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query =$db->Execute("select configuration_value from " . TABLE_CONFIGURATION .
                " where configuration_key = 'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }


    public function keys()
    {
        return array(
                'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS',
                'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_PUBLISHABLE_KEY',
                'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY',
                'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_METHOD',
                'MODULE_PAYMENT_CHECKOUAPIPAYMENT_REVIEW_ORDER_STATUS_ID',
                'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ZONE',
                'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER',
                'MODULE_PAYMENT_CHECKOUAPIPAYMENT_TYPE',
                'MODULE_PAYMENT_CHECKOUAPIPAYMENT_LOCALPAYMENT_ENABLE',
                'MODULE_PAYMENT_CHECKOUAPIPAYMENT_GATEWAY_TIMEOUT',
                'MODULE_PAYMENT_CHECKOUAPIPAYMENT_AUTOCAPTIME',
                'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SORT_ORDER'
        );
    }
    public function remove() {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }


    function update_status() {
        global $order;

        if ( ($this->getEnabled ()) && ((int)MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ZONE > 0) && ( isset($order) && is_object($order) ) ) {
            $check_flag = false;
            $check_query = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_STRIPE_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
            while (!$check ->EOF) {
                if ($check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    function install() {
        global $db, $messageStack;
        if (defined('MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS')) {
          $messageStack->add_session('Credit Card (Checkout.com) module already installed.', 'error');
          zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=Checkoutapipayment', 'NONSSL'));
          return 'failed';
        }

        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Credit Card (Checkout.com)', 'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS', 'True', 'Do you want to accept Credit Card (Checkout.com) payments?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Publishable API Key', 'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_PUBLISHABLE_KEY', '', 'The Checkout.com account publishable API key to use.', '6', '0', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Secret API Key', 'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY', '', 'The Checkout.com account secret API key to use.', '6', '0', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Type', 'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_METHOD', 'Authorize', 'The processing method to use for each transaction.', '6', '0', 'zen_cfg_select_option(array(\'Authorize\', \'Authorize and Capture\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Server', 'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER', 'Preprod', 'Perform transactions on the production server or on the testing server.', '6', '0', 'zen_cfg_select_option(array(\'Live\', \'Preprod\', \'Test\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Method Type', 'MODULE_PAYMENT_CHECKOUAPIPAYMENT_TYPE', 'True', 'Verify gateway server SSL certificate on connection?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '0', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Local Payment', 'MODULE_PAYMENT_CHECKOUAPIPAYMENT_LOCALPAYMENT_ENABLE', 'False', 'Enable localpayment using the js.', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Set Gateway Timeout', 'MODULE_PAYMENT_CHECKOUAPIPAYMENT_GATEWAY_TIMEOUT', '60', 'Set how long request timeout on server.', '6', '0', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Set auto capture time', 'MODULE_PAYMENT_CHECKOUAPIPAYMENT_AUTOCAPTIME', '0', 'When transaction is set to authorize and caputure , the gateway will use this time to caputure the transaction.', '6', '0', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Review Order Status', 'MODULE_PAYMENT_CHECKOUAPIPAYMENT_REVIEW_ORDER_STATUS_ID', '0', 'Set the status of orders flagged as being under review to this value', '6', '0', 'zen_get_order_status_name', 'zen_cfg_pull_down_order_statuses(', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
    }

    function format_raw($number, $currency_code = '', $currency_value = '') {
        global $currencies, $currency;

        if (empty($currency_code) || !$currencies->is_set($currency_code)) {
            $currency_code = $currency;
        }

        if (empty($currency_value) || !is_numeric($currency_value)) {
            $currency_value = $currencies->currencies[$currency_code]['value'];
        }

        return number_format(zen_round($number * $currency_value, $currencies->currencies[$currency_code]['decimal_places']), $currencies->currencies[$currency_code]['decimal_places'], '', '');
    }
}