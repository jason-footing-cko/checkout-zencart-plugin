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
        $amount = (int)$order->info['total'];
        $amountCents = $amount *100;
        $email = $order->customer['email_address'];
        $currency = $order->info['currency'];
        $publicKey = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_PUBLISHABLE_KEY;
        $localPayment = MODULE_PAYMENT_CHECKOUAPIPAYMENT_LOCALPAYMENT_ENABLE;
        if ($this->gateway_mode == 'Live'){
          $scriptSrc = "https://www.checkout.com/cdn/js/checkout.js";
        } else {
          $scriptSrc = "//sandbox.checkout.com/js/v1/checkout.js";
        }
        
        if($localPayment == 'True'){
          $localPaymentMode = 'mixed';
        } else {
          $localPaymentMode = 'card';
        }

        $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';
        $paymentToken =  $this->getPaymentToken();

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
                 currency: "{$order->info['currency']}",
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

        return $confirmation;
    }

    public function process_button()
    {
        global $order,$messageStack;
        $amount = $order->info['total'];
        $amountCents = (int)($amount *100);
        $email = $order->customer['email_address'];
        $currency = $order->info['currency'];
        $publicKey = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_PUBLISHABLE_KEY;
        $localPayment = MODULE_PAYMENT_CHECKOUAPIPAYMENT_LOCALPAYMENT_ENABLE;
        if ($this->gateway_mode == 'Live'){
          $scriptSrc = "https://www.checkout.com/cdn/js/checkout.js";
        } else {
          $scriptSrc = "//sandbox.checkout.com/js/v1/checkout.js";
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
                 currency: "{$order->info['currency']}",
                 paymentMode: "{$localPaymentMode}",
                 widgetContainerSelector: '.widget-container',
                 cardCharged: function(event){
                    fireEvent(document.getElementById('checkout_confirmation'),'submit');
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
                 }
          };


            window.addEventListener("load", function(event){
                document.getElementById('btn_submit').addEventListener('click',function(event){
                 $(this).attr('disabled','disabled');
                     event.preventDefault();
                     CheckoutIntegration.open();
                },false);

            }, false);

            function fireEvent(element, event) {
                if (document.createEvent) {
                    // dispatch for all browsers except IE before version 9
                    var evt = document.createEvent("HTMLEvents");
                    evt.initEvent(event, true, true ); // event type, bubbling, cancelable
                    return element.dispatchEvent(evt);
                } else {
                    // dispatch for IE before version 9
                    var evt = document.createEventObject();
                    return element.fireEvent('on' + event, evt)
                }
            }
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

        $config = parent::before_process();

        $this->_placeorder($config);
    }

    protected function _createCharge($config)
    {
        $Api = CheckoutApi_Api::getApi(array('mode'=> MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER));

        $config['paymentToken'] = $_POST['cko_paymentToken'];

        return $Api->verifyChargePaymentToken($config);
    }

    private function getPaymentToken()
    {
        global  $order,  $_POST,$messageStack;
        $config = array();
        $Api = CheckoutApi_Api::getApi(array('mode'=> MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER));
        $amount = $order->info['total'];
        $amountCents = (int)($amount *100);
        $config['authorization'] = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY;
        $config['mode'] = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER;

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

}