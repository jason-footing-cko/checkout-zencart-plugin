<?php
class model_methods_creditcard extends model_methods_Abstract
{
    var $code = 'checkoutapipayment';
    var $title = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TEXT_PUBLIC_TITLE;
    var $gateway_mode = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER;
    

    public function javascript_validation()
    {
        return false;
    }

    public function selection()
    {
        global $order;
        $Api = CheckoutApi_Api::getApi(
                array( 'mode'          => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER,
                       'authorization' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY)
        );
        $amount = $order->info['total'];
        $amountCents = $Api->valueToDecimal($amount, $order->info['currency']);
        $email = $order->customer['email_address'];
        $currency = $order->info['currency'];
        $publicKey = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_PUBLISHABLE_KEY;
        $localPayment = MODULE_PAYMENT_CHECKOUAPIPAYMENT_LOCALPAYMENT_ENABLE;
        if ($this->gateway_mode == 'Live'){
          $scriptSrc = "https://www.checkout.com/cdn/js/checkout.js";
        } else {
          $scriptSrc = "https://sandbox.checkout.com/js/v1/checkout.js";
        }
        
        if($localPayment == 'True'){
          $localPaymentMode = 'mixed';
        } else {
          $localPaymentMode = 'card';
        }

        $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';
        $paymentToken =  $this->getPaymentToken($orderid = null);

        $content = 
        <<<EOD
        <div class="widget-container"></div>
        <script src="{$scriptSrc}" async ></script>
        <input type="hidden" name="cko-paymentToken" id="cko-paymentToken" value="{$paymentToken}" />
        <script type="text/javascript">

            window.CKOConfig = {
                publicKey: "{$publicKey}",
                 renderMode: 2,
                 paymentToken:'{$paymentToken}',
                 value: "{$amountCents}",
                 paymentMode: "{$localPaymentMode}",
                 currency: "{$currency}",
                 widgetContainerSelector: '.widget-container'
           }

        </script>
EOD;
        $selection = array('id' => $this->code,
                            'module' => $this->title,
                            'fields' =>  array( array('field' => $content)));

        return $selection;

    }

    public function pre_confirmation_check()
    {

    }


    public function confirmation()
    {

        return false;
    }

    public function process_button()
    {
        global $order,$messageStack;
        $Api = CheckoutApi_Api::getApi(
            array( 'mode'          => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER,
                   'authorization' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY)
        );
        $amount = $order->info['total'];
        $amountCents = $Api->valueToDecimal($amount, $order->info['currency']);
        $email = $order->customer['email_address'];
        $currency = $order->info['currency'];
        $publicKey = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_PUBLISHABLE_KEY;
        $localPayment = MODULE_PAYMENT_CHECKOUAPIPAYMENT_LOCALPAYMENT_ENABLE;
        $themeColor = MODULE_PAYMENT_CHECKOUAPIPAYMENT_GATEWAY_THEME_COLOR;
        $buttonColor = MODULE_PAYMENT_CHECKOUAPIPAYMENT_GATEWAY_BUTTON_COLOR;
        $logoUrl = MODULE_PAYMENT_CHECKOUAPIPAYMENT_GATEWAY_LOGO_URL;
        $iconColor = MODULE_PAYMENT_CHECKOUAPIPAYMENT_GATEWAY_ICON_COLOR;
        $currencyFormat = MODULE_PAYMENT_CHECKOUAPIPAYMENT_GATEWAY_CURRENCY_FORMAT;
        if($currencyFormat == 'Code'){
          $format = 'true';
        }
        else {
          $format = 'false';
        }
        
        if ($this->gateway_mode == 'Live'){
          $scriptSrc = "https://www.checkout.com/cdn/js/checkout.js";
        } else {
          $scriptSrc = "https://sandbox.checkout.com/js/v1/checkout.js";
        }
        if($localPayment == 'True'){
          $localPaymentMode = 'mixed';
        } else {
          $localPaymentMode = 'card';
        }
        
        $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';
        $paymentToken =  $_POST['cko-paymentToken'];

        if(!$paymentToken) {


            $messageStack->add_session ( 'checkout_payment' , 'Please try again there was an issue your payment details' ,
                'error' );
            if ( !isset( $_GET[ 'error' ] ) ) {
                zen_redirect ( zen_href_link ( FILENAME_CHECKOUT_PAYMENT , 'error=true' , 'SSL' , true , false ) );
            }
        }
                $content =
            <<<EOD
	        <div class="widget-container" style="display:none"></div>
        <script src="{$scriptSrc}" async ></script>
        <input type="hidden" name="cko-paymentToken" id="cko-paymentToken" value="{$paymentToken}" />
        <input type="hidden" name="redirectUrl" id="cko-cc-redirectUrl" value=""/>
        <script type="text/javascript">
            var reload = false;
            window.CKOConfig = {
                publicKey: "{$publicKey}",
                renderMode: 2,
                customerEmail: '{$order->customer['email_address']}' ,
                customerName: '{$order->customer['firstname']} {$order->customer['lastname']}',
                namespace: 'CheckoutIntegration',
                paymentToken:'{$paymentToken}',
                value: "{$amountCents}",
                currency: "{$currency}",
                paymentMode: "{$localPaymentMode}",
                forceMobileRedirect: true,
                useCurrencyCode: {$format},
                widgetContainerSelector: '.widget-container',
                styling: {
                   themeColor: "{$themeColor}",
                   buttonColor: "{$buttonColor}",
                   logoUrl: "{$logoUrl}",
                   iconColor: "{$iconColor}",
                },
                 
                cardCharged: function(event){
                    document.getElementById('checkout_confirmation').submit();
                },
                lightboxDeactivated: function (event) {
                  $('#btn_submit').removeAttr("disabled");
                  if (reload) {
                        window.location.reload();
                  }
                },
                paymentTokenExpired: function (event) {
                  reload = true;
                },
                invalidLightboxConfig: function (event) {
                  reload = true;
                 },
                ready : function (event){
                  if(CheckoutIntegration.isMobile()){
                    document.getElementById('cko-cc-redirectUrl').value = CheckoutIntegration.getRedirectionUrl();
                  }
                  window.addEventListener("load", function(event){
                    document.getElementById('btn_submit').addEventListener('click',function(event){
                     $(this).attr('disabled','disabled');
                         event.preventDefault();
                          if (!CheckoutIntegration.isMobile()) {
                              CheckoutIntegration.open();
                          } else {  
                              document.getElementById('checkout_confirmation').submit();
                          }
                    },false);

                  }, false);
                }, 
               
                 
          };




        </script>
EOD;

        $process_button_string = '<input type="hidden" name="cko_paymentToken" value = "'.$paymentToken.'">';


        $process_button_string.= '<input type="hidden" name="'. zen_session_name() .'" value = "'.zen_session_id().'">';
        echo $process_button_string;
        echo $content;

        return $process_button_string;
    }


    public function before_process()
    {
        global $_POST, $order;
        
        if($_POST['redirectUrl']) { 
            return true;
        }
        else {

        $config = parent::before_process();
        $this->_placeorder($config);
        
        }
    }

    protected function _createCharge($config)
    {
      global $order;

        $Api = CheckoutApi_Api::getApi(array('mode'=> MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER));

        $config['paymentToken'] = $_POST['cko_paymentToken'];

        return $Api->verifyChargePaymentToken($config);
    }
    
    public function after_process()
    {
        global $insert_id, $customer_id, $stripe_result;

        if($_POST['redirectUrl']) {
            $paymentToken =  $this->getPaymentToken($insert_id);
            $cko_cc_redirectUrl = $_POST['redirectUrl'];
            $cko_cc_redirectUrl = $this->replace_between($cko_cc_redirectUrl, 'paymentToken=', '&', $paymentToken);
            zen_redirect($cko_cc_redirectUrl . '&trackId='. $insert_id);
        }

        if($this->_currentCharge) {
            $Api = CheckoutApi_Api::getApi(
                    array( 'mode'          => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER,
                           'authorization' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY)
            );
            
            $status_comment = array('Transaction ID: ' . $this->_currentCharge->getId(),
                'Transaction has been process using "' . MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TEXT_PUBLIC_TITLE .'" and paid with  card '. $this->_currentCharge->getCard()->getPaymentMethod(),
                'Response code:' . $this->_currentCharge->getResponseCode(),
                'Response Message: '. $this->_currentCharge->getResponseMessage());

            $sql_data_array = array('orders_id' => $insert_id,
                'orders_status_id' => MODULE_PAYMENT_CHECKOUAPIPAYMENT_REVIEW_ORDER_STATUS_ID,
                'date_added' => 'now()',
                'customer_notified' => '0',
                'comments' => implode("\n", $status_comment));




            $chargeUpdated = $Api->updateTrackId($this->_currentCharge,$insert_id);

            zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
        }
        $this->_currentCharge  = '';

    }

    private function getPaymentToken($orderid = null)
    {
        global  $order,  $_POST,$messageStack;
        $config = array();
        $Api = CheckoutApi_Api::getApi(
            array( 'mode'          => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER,
                   'authorization' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY)
        );
        $amount = $order->info['total'];
        $amountCents = $Api->valueToDecimal($amount, $order->info['currency']);
        $config['authorization'] = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY;
        $config['mode'] = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER;
        $minAmount3D =  $Api->valueToDecimal(MODULE_PAYMENT_CHECKOUAPIPAYMENT_GATEWAY_MIN_AMOUNT_3D,$order->info['currency']);
        $chargeMode = MODULE_PAYMENT_CHECKOUAPIPAYMENT_GATEWAY_IS_3D;
        $chargeModeValue = 1;
        if($chargeMode == 'Yes') {
            if($amountCents > $minAmount3D){
              $chargeModeValue = 2;
            }
        }
        $products = array();
        $i = 1;
        foreach($order->products as $product) {

            $products[] = array (
                'name'       =>    $product['name'],
                'sku'        =>    $product['id'],
                'price'      =>    $product['final_price'],
                'quantity'   =>     $product['qty'],
            );
            $i++;
        }

        $billingAddress = array (
                'addressLine1'    => $order->billing['street_address'],
                'addressLine2'    => $order->billing['suburb'],
                'postcode'        => $order->billing['postcode'],
                'country'         => $order->billing['country']['iso_code_2'],
                'city'            => $order->billing['city'],
                'state'           => $order->billing['state'],
                'phone'           => array('number' => $order->customer['telephone'])
        );

        $config['postedParam'] = array (
            'email'           =>    $order->customer['email_address'] ,
            'name'            =>    "{$order->customer['firstname']} {$order->customer['lastname']}",
            'chargeMode'      =>    1, //todo replace by $chargeModeValue
            'trackId'         =>    $orderid,
            'value'           =>    $amountCents,
            'currency'        =>    $order->info['currency'] ,
            'products'        =>    $products,
            'shippingDetails' =>
                array (
                      'addressLine1'    =>    $order->delivery['street_address'],
                      'addressLine2'    =>    $order->delivery['suburb'],
                      'postcode'        =>    $order->delivery['postcode'],
                      'country'         =>    $order->delivery['country']['iso_code_2'],
                      'city'            =>    $order->delivery['city'],
                      'phone'           =>    array('number' => $order->customer['telephone']),
                    )
            );

        if (MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_METHOD == 'Authorize and Capture') {
            $config = array_merge_recursive ( $this->_captureConfig(),$config);
        } else {
            $config = array_merge_recursive ( $this->_authorizeConfig(),$config);
        }

        $paymentTokenCharge = $Api->getPaymentToken($config);
        $paymentToken    =   '';

        if($paymentTokenCharge->isValid()){
            $paymentToken = $paymentTokenCharge->getId();
        }

        if(!$paymentToken) {
            $error_message = $paymentTokenCharge->getExceptionState()->getErrorMessage().
                ' ( '.$paymentTokenCharge->getEventId().')';

            $messageStack->add_session('checkout_payment', $error_message . '<!-- ['.$this->code.'] -->', 'error');
            if(!isset($_GET['error'])) {
                zen_redirect ( zen_href_link ( FILENAME_CHECKOUT_PAYMENT , 'error=true' , 'SSL' , true , false ) );
            }
        }

        return $paymentToken;
    }
    
    public function replace_between($str, $needle_start, $needle_end, $replacement) 
    {
      $pos = strpos($str, $needle_start);
      $start = $pos === false ? 0 : $pos + strlen($needle_start);

      $pos = strpos($str, $needle_end, $start);
      $end = $start === false ? strlen($str) : $pos;

      return substr_replace($str,$replacement,  $start, $end - $start);
  }

}