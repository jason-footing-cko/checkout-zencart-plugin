<?php
class Model extends base
{
     public $code;
     public $title;
     public $description;
     public $enabled;
     private $_instance;

     public function __construct()
     {
        global $order, $messageStack;

        $this->code ='checkoutapipayment';
        $instance = $this->getInstance();
        $this->sort_order = defined('MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SORT_ORDER') ? MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SORT_ORDER : 0;
        $this->enabled =  $instance->getEnabled();



        if (IS_ADMIN_FLAG === true) {

            $this->title = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TEXT_TITLE;
        }
        else {
            $this->title = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TEXT_PUBLIC_TITLE;
        }

        $this->description = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TEXT_DESCRIPTION;
        $this->description .= MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TEXT_REQUIREMENTS;


        if ((int)MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ORDER_STATUS_ID > (int)DEFAULT_ORDERS_STATUS_ID) {
            $this->order_status = (int)MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ORDER_STATUS_ID;
        }

        
        if (is_object($order)) $this->update_status();
     }

     public function getInstance()
     {
        if(!$this->_instance) {

            switch(MODULE_PAYMENT_CHECKOUAPIPAYMENT_TYPE) {
                case 'True':
                    $this->_instance = new model_methods_creditcardpci();
                break;
                default :
                    $this->_instance =  new model_methods_creditcard();

                    break;
            }
        }

         return $this->_instance;

     }

    public function update_status()
    {
        $this->getInstance()->update_status();
    }

    public function javascript_validation()
    {
        return $this->getInstance()->javascript_validation();
    }

    public function selection()
    {
        return array_merge( array('id'     => $this->code,
                                    'module' => $this->title),$this->getInstance()->selection()
                       );
    }

    public function pre_confirmation_check()
    {
        $this->getInstance()->pre_confirmation_check();
    }

    public function confirmation()
    {
        return  $this->getInstance()->confirmation();
    }

    public function process_button()
    {
        $this->getInstance()->process_button();
    }

    public function before_process()
    {
        $this->getInstance()->before_process();
    }

    public function after_process()
    {
        $this->getInstance()->after_process();
    }

    public function get_error()
    {

    }

    public function check()
    {
        return  $this->getInstance()->check();
    }

    public function install()
    {
        $this->getInstance()->install();
    }

    public function remove()
    {
        return  $this->getInstance()->remove();
    }

    public function keys()
    {
         return  $this->getInstance()->keys();
    }
}

