<?php
class model_methods_creditcardpci extends model_methods_Abstract
{
    var $code = 'checkoutapipayment';
    var $title = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TEXT_PUBLIC_TITLE;

    public function javascript_validation(){
            $js = '  if (payment_value == "' . $this->code . '") {' . "\n" .
          '    var cc_owner = document.checkout_payment.checkoutapipayment_cc_owner.value;' . "\n" .
          '    var cc_number = document.checkout_payment.checkoutapipayment_cc_number.value;' . "\n" .
          '    var cc_cvv = document.checkout_payment.checkoutapipayment_cc_cvv.value;' . "\n" .
          '    if (cc_owner == "" || cc_owner.length < ' . CC_OWNER_MIN_LENGTH . ') {' . "\n" .
          '      error_message = error_message + "' . MODULE_PAYMENT_CHECKOUTAPIPAYMENT_JS_CC_OWNER . '";' . "\n" .
          '      error = 1;' . "\n" .
          '    }' . "\n" .
          '    if (cc_number == "" || cc_number.length < ' . CC_NUMBER_MIN_LENGTH . ') {' . "\n" .
          '      error_message = error_message + "' . MODULE_PAYMENT_CHECKOUTAPIPAYMENT_JS_CC_NUMBER . '";' . "\n" .
          '      error = 1;' . "\n" .
          '    }' . "\n" .
          '         if (cc_cvv == "" || cc_cvv.length < "3") {' . "\n".
          '           error_message = error_message + "' . MODULE_PAYMENT_CHECKOUTAPIPAYMENT_JS_CC_CVV . '";' . "\n" .
          '           error = 1;' . "\n" .
          '         }' . "\n" .
          '  }' . "\n";

        return $js;
    }

    public function selection()
    {
        global $order;

        for ($i=1; $i<13; $i++) {
          $expires_month[] = array('id' => sprintf('%02d', $i), 'text' => strftime('%B - (%m)',mktime(0,0,0,$i,1,2000)));
        }

        $today = getdate();
        for ($i=$today['year']; $i < $today['year']+15; $i++) {
          $expires_year[] = array('id' => strftime('%y',mktime(0,0,0,1,1,$i)), 'text' => strftime('%Y',mktime(0,0,0,1,1,$i)));
        }

        $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';

        $selection = array('id' => $this->code,
                           'module' => $this->title,
                           'fields' => array(array('title' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_CREDITCARD_OWNER,
                                                   'field' => zen_draw_input_field('checkoutapipayment_cc_owner', $order->billing['firstname'] . ' ' . $order->billing['lastname'], 'id="'.$this->code.'-cc-owner"'. $onFocus . ' autocomplete="off"'),
                                                     'tag' => $this->code.'-cc-owner'),
                                             array('title' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_CREDITCARD_NUMBER,
                                                   'field' => zen_draw_input_field('checkoutapipayment_cc_number', $ccnum, 'id="'.$this->code.'-cc-number"' . $onFocus . ' autocomplete="off"'),
                                                     'tag' => $this->code.'-cc-number'),
                                             array('title' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_CREDITCARD_EXPIRY,
                                                   'field' => zen_draw_pull_down_menu('checkoutapipayment_cc_expires_month', $expires_month, strftime('%m'), 'id="'.$this->code.'-cc-expires-month"' . $onFocus) . '&nbsp;' . zen_draw_pull_down_menu('checkoutapipayment_cc_expires_year', $expires_year, '', 'id="'.$this->code.'-cc-expires-year"' . $onFocus),
                                                     'tag' => $this->code.'-cc-expires-month'),
                                             array('title' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_CREDITCARD_CVC,
                                                   'field' => zen_draw_input_field('checkoutapipayment_cc_cvv', '', 'size="4" maxlength="4"'. ' id="'.$this->code.'-cc-cvv"' . $onFocus . ' autocomplete="off"') . ' ' . '<a href="javascript:popupWindow(\'' . zen_href_link(FILENAME_POPUP_CVV_HELP) . '\')">' . MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TEXT_POPUP_CVV_LINK . '</a>',
                                                     'tag' => $this->code.'-cc-cvv')));

        return $selection;

    }

    public function pre_confirmation_check()
    {
        global $messageStack;

        include(DIR_WS_CLASSES . 'cc_validation.php');

        $cc_validation = new cc_validation();
        $result = $cc_validation->validate($_POST['checkoutapipayment_cc_number'], $_POST['checkoutapipayment_cc_expires_month'], $_POST['checkoutapipayment_cc_expires_year']);
        $error = '';
        switch ($result) {
          case -1:
            $error = sprintf(TEXT_CCVAL_ERROR_UNKNOWN_CARD, substr($cc_validation->cc_number, 0, 4));
            break;
          case -2:
          case -3:
          case -4:
            $error = TEXT_CCVAL_ERROR_INVALID_DATE;
            break;
          case false:
            $error = TEXT_CCVAL_ERROR_INVALID_NUMBER;
            break;
        }

        if ( ($result == false) || ($result < 1) ) {
            $messageStack->add_session('checkout_payment', $error . '<!-- ['.$this->code.'] -->', 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }

        $this->cc_card_type = $cc_validation->cc_type;
        $this->cc_card_number = $cc_validation->cc_number;
        $this->cc_expiry_month = $cc_validation->cc_expiry_month;
        $this->cc_expiry_year = $cc_validation->cc_expiry_year;

    }


    public function confirmation()
    {


        $confirmation = array('title' => $this->title . ': ' . $this->cc_card_type,
                          'fields' => array(array('title' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_CREDITCARD_OWNER,
                                                  'field' => $_POST['checkoutapipayment_cc_owner']),
                                            array('title' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_CREDITCARD_NUMBER,
                                                  'field' => str_repeat('X', (strlen($this->cc_card_number) - 4)) . substr($this->cc_card_number, -4)),
                                            array('title' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_CREDITCARD_EXPIRY,
                                                  'field' => strftime('%B, %Y', mktime(0,0,0, (int)$this->cc_expiry_month, 1, $this->cc_expiry_year)))));


        return $confirmation;

    }



    public function  process_button()
    {

        $process_button_string = '<input type="hidden" name="cc_owner" value = "'.$_POST['checkoutapipayment_cc_owner'].'">';
        $process_button_string.= '<input type="hidden" name="cc_expires_month" value = "'.$_POST['checkoutapipayment_cc_expires_month'].'">';
        $process_button_string.= '<input type="hidden" name="cc_expires_year" value = "'.$_POST['checkoutapipayment_cc_expires_year'].'">';
        $process_button_string.= '<input type="hidden" name="cc_number" value = "'.$_POST['checkoutapipayment_cc_number'].'">';
        $process_button_string.= '<input type="hidden" name="cc_cvv" value = "'.$_POST['checkoutapipayment_cc_cvv'].'">';

        $process_button_string.= '<input type="hidden" name="'. zen_session_name() .'" value = "'.zen_session_id().'">';

        echo $process_button_string;

        return $process_button_string;
    }

    public function before_process()
    {
        global $order;

        $config = parent::before_process();
        $config['postedParam']['card']['name'] = $_POST['cc_owner'];
        $config['postedParam']['card']['number'] = $_POST['cc_number'];
        $config['postedParam']['card']['expiryMonth'] = $_POST['cc_expires_month'];
        $config['postedParam']['card']['expiryYear'] = $_POST['cc_expires_year'];
        $config['postedParam']['card']['cvv'] = $_POST['cc_cvv'];
        $config['postedParam']['card']['billingDetails']['addressLine1'] = $order->billing['street_address'];
        $config['postedParam']['card']['billingDetails']['addressLine2'] = $order->billing['suburb'];
        $config['postedParam']['card']['billingDetails']['postcode'] = $order->billing['postcode'];
        $config['postedParam']['card']['billingDetails']['country'] = $order->billing['country']['iso_code_2'];
        $config['postedParam']['card']['billingDetails']['city'] = $order->billing['city'];
        $config['postedParam']['card']['billingDetails']['phone'] = array ('number' => $order->customer['telephone']);

        $this->_placeorder($config);
    }


}