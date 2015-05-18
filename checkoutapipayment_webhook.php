<?php
require('includes/application_top.php');
if (defined('MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS') && MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS == 'True') {

	function _process ()
	{
		$config['chargeId'] = $_GET['chargeId'];
		$config['authorization'] = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY;
		$Api = CheckoutApi_Api::getApi(array('mode'=>MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER));
		$respondBody    =    $Api->getCharge($config);
		$json = $respondBody->getRawOutput();
		return $json;
	}

	function order_statuses() {
		global $db;


		$statuses = $db->Execute("select orders_status_id, orders_status_name
                              from " . TABLE_ORDERS_STATUS . "
                              where language_id = '" . (int)$_SESSION['languages_id'] . "'
                              order by orders_status_id");

		while (!$statuses->EOF) {
			$statuses_array[$statuses->fields['orders_status_id']] = array('id' => $statuses->fields['orders_status_id'],
			                          'text' => $statuses->fields['orders_status_name'] . ' [' . $statuses->fields['orders_status_id'] . ']');
			$statuses->MoveNext();
		}

		return $statuses_array;
	}

	require(DIR_WS_CLASSES . 'payment.php');

	$checkoutapipayment_module = 'checkoutapipayment';

	$payment_modules = new payment( $checkoutapipayment_module );
	if(isset($_GET['chargeId'])){
		$stringCharge = _process();
	}else {
		$stringCharge = file_get_contents("php://input");
	}


	if($stringCharge) {
		$Api    =    CheckoutApi_Api::getApi(array('mode'=>MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER));
		$objectCharge = $Api->chargeToObj($stringCharge);

		$orderId = $objectCharge->getTrackId();
		if($orderId) {
			require(DIR_WS_CLASSES . 'order.php');
			$order = new order($orderId);
			$orderStatuses = order_statuses();
			if($order) {
				if($objectCharge->getCaptured() ) {
					if($order->info['orders_status'] !=2) {
						echo "Order has #$orderId was  set complete";

						$sql = "UPDATE " . TABLE_ORDERS  . "
		                  SET orders_status = " . (int)2 . "
		                  WHERE orders_id = '" . (int)$orderId . "'";
								$db->Execute($sql);

						$sql_data_array = array('orders_id' => (int)$orderId,
						                        'orders_status_id' => 2,
						                        'date_added' => 'now()',
						                        'comments' => ' Update Checkout.com from Webhook. Status: '
							                        . $orderStatuses[2] ,
						                        'customer_notified' => 0
						);

						zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

					}else {
						echo  "Order has #$orderId was already set complete";
					}

				} elseif($objectCharge->getRefunded()) {

					$sql = "UPDATE " . TABLE_ORDERS  . "
		                  SET orders_status = " . (int)1 . "
		                  WHERE orders_id = '" . (int)$orderId . "'";
					$db->Execute($sql);
					$sql_data_array = array('orders_id' => (int)$orderId,
					                        'orders_status_id' => 1,
					                        'date_added' => 'now()',
					                        'comments' => ' Update Checkout.com from Webhook. Status:   ('
						                        . $orderStatuses[2] .')',
					                        'customer_notified' => 0
					);

					zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
					echo "Order has #$orderId was  set cancel (pending)";
				} elseif(!$objectCharge->getAuthorised()) {
					$sql = "UPDATE " . TABLE_ORDERS  . "
		                  SET orders_status = " . (int)1 . "
		                  WHERE orders_id = '" . (int)$orderId . "'";
					$db->Execute($sql);

					$sql_data_array = array('orders_id' => (int)$orderId,
					                        'orders_status_id' => 1,
					                        'date_added' => 'now()',
					                        'comments' => ' Update Checkout.com from Webhook. Status:   ('
						                        . $orderStatuses[2] .')',
					                        'customer_notified' => 0
					);

					zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
					echo "Order has #$orderId was already set cancel (pending)";
				}


			}
		}
	}


}