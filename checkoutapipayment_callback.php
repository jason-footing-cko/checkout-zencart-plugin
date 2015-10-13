<?php

require('includes/application_top.php');
include 'includes/modules/payment/checkoutapipayment/autoload.php';

global $db;

if (defined('MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS') && MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS == 'True') {
  if(isset($_POST['cko-payment-token'])){
    $paymentToken = $_POST['cko-payment-token'];
    $config['authorization'] = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY;
    $config['paymentToken'] = $paymentToken;
    $Api = CheckoutApi_Api::getApi(array( 'mode' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER));
    $objectCharge = $Api->verifyChargePaymentToken($config);
    $order_id = $objectCharge->getTrackId();
    $orderId = $_POST['cko-track-id'];
		if($orderId) {
			require(DIR_WS_CLASSES . 'order.php');
			$order = new order($orderId);
               if($objectCharge->isValid()) {
                    if ($objectCharge->getStatus() == 'Authorised' || $objectCharge->getStatus() == 'Flagged') {  
                        if($order->info['orders_status'] !=2) {
						  $sql = "UPDATE " . TABLE_ORDERS  . "
		                  SET orders_status = " . (int)2 . "
		                  WHERE orders_id = '" . (int)$orderId . "'";
							$db->Execute($sql);
                            $_SESSION['cart']->reset(true);
                            unset($_SESSION['sendto']);
                            unset($_SESSION['billto']);
                            unset($_SESSION['shipping']);
                            unset($_SESSION['payment']);
                            unset($_SESSION['comments']);
                            unset($_SESSION['cot_gv']);
                            zen_redirect(zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
					   }
                    }
               }
        }
  }
}

