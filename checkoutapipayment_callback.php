<?php

require('includes/application_top.php');
include 'includes/modules/payment/checkoutapipayment/autoload.php';

global $db;

if (defined('MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS') && MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS == 'True') {
  if (isset($_REQUEST['cko-payment-token'])) {
    $paymentToken = $_REQUEST['cko-payment-token'];
    $config['authorization'] = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY;
    $config['paymentToken'] = $paymentToken;
    $Api = CheckoutApi_Api::getApi(array('mode' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER));
    $objectCharge = $Api->verifyChargePaymentToken($config);
    if (preg_match('/^1[0-9]+$/', $objectCharge->getResponseCode())) {
      $orderId = $objectCharge->getTrackId();
      $status_comment = array(
          'Transaction ID: ' . $objectCharge->getId(),
          'Transaction has been process using "Credit Card (Checkout.com)" and paid with  card ' . $objectCharge->getCard()->getPaymentMethod(),
          'Response code:' . $objectCharge->getResponseCode(),
          'Response Message: ' . $objectCharge->getResponseMessage());

      if ($orderId) {
        require(DIR_WS_CLASSES . 'order.php');
        $order = new order($orderId);
        if ($objectCharge->isValid()) {
          if ($objectCharge->getStatus() == 'Authorised' || $objectCharge->getStatus() == 'Flagged') {
            if ($order->info['orders_status'] != 2) {

              $sql = "UPDATE " . TABLE_ORDERS . "
		                  SET orders_status = " . (int) 2 . "
		                  WHERE orders_id = '" . (int) $orderId . "'";
              $db->Execute($sql);

              $sql_data_array = array('orders_id' => $orderId,
                  'orders_status_id' => (int) 2,
                  'date_added' => 'now()',
                  'customer_notified' => '0',
                  'comments' => implode("\n", $status_comment));

              zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

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
    else {
      zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
    }
  }
}

