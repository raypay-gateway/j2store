<?php
/**
 * RayPay payment plugin
 *
 * @developer     hanieh729
 * @publisher     RayPay
 * @package       J2Store
 * @subpackage    payment
 * @copyright (C) 2021 RayPay
 * @license       http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * https://raypay.ir
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;

require_once( JPATH_ADMINISTRATOR . '/components/com_j2store/library/plugins/payment.php' );

class plgJ2StorePayment_raypay extends J2StorePaymentPlugin {
    /**
     * @var $_element  string  Should always correspond with the plugin's filename,
     *                         forcing it to be unique
     */
    var $_element    = 'payment_raypay';

    function __construct( & $subject, $config ) {
        parent::__construct( $subject, $config );
        $this->loadLanguage( 'com_j2store', JPATH_ADMINISTRATOR );
    }

    function onJ2StoreCalculateFees( $order ) {
        $payment_method = $order->get_payment_method();

        if ( $payment_method == $this->_element )
        {
            $total             = $order->order_subtotal + $order->order_shipping + $order->order_shipping_tax;
            $surcharge         = 0;
            $surcharge_percent = $this->params->get( 'surcharge_percent', 0 );
            $surcharge_fixed   = $this->params->get( 'surcharge_fixed', 0 );
            if ( ( float ) $surcharge_percent > 0 || ( float ) $surcharge_fixed > 0 )
            {
                // percentage
                if ( ( float ) $surcharge_percent > 0 )
                {
                    $surcharge += ( $total * ( float ) $surcharge_percent ) / 100;
                }

                if ( ( float ) $surcharge_fixed > 0 )
                {
                    $surcharge += ( float ) $surcharge_fixed;
                }

                $name         = $this->params->get( 'surcharge_name', JText::_( 'J2STORE_CART_SURCHARGE' ) );
                $tax_class_id = $this->params->get( 'surcharge_tax_class_id', '' );
                $taxable      = FALSE;
                if ( $tax_class_id && $tax_class_id > 0 )
                {
                    $taxable = TRUE;
                }
                if ( $surcharge > 0 )
                {
                    $order->add_fee( $name, round( $surcharge, 2 ), $taxable, $tax_class_id );
                }
            }
        }
    }

    /**
     * Prepares variables and
     * Renders the form for collecting payment info
     *
     * @return unknown_type
     */
    function _renderForm( $data )
    {
        $user = JFactory::getUser();
        $vars = new JObject();
        $vars->raypay = 'پرداخت با رای پی';
        $html = $this->_getLayout('form', $vars);
        return $html;
    }

    /**
     * Processes the payment form
     * and returns HTML to be displayed to the user
     * generally with a success/failed message
     *
     * @param $data     array       form post data
     * @return bool|void
     */
    function _prePayment( $data ) {
        $app                       = JFactory::getApplication();
        $this->http = HttpFactory::getHttp();
        $vars                      = new JObject();
        $vars->order_id            = $data['order_id'];
        $vars->orderpayment_id     = $data['orderpayment_id'];
        $vars->orderpayment_amount = $data['orderpayment_amount'];
        $vars->orderpayment_type   = $this->_element;
        $vars->button_text         = $this->params->get( 'button_text', 'J2STORE_PLACE_ORDER' );
        $vars->display_name        = 'پرداخت با رای پی';
        $vars->user_id             = $this->params->get( 'user_id', '' );
        $vars->marketing_id             = $this->params->get( 'marketing_id', '' );
        $vars->sandbox             = $this->params->get( 'sandbox', '' );

        // Customer information
        $orderinfo = F0FTable::getInstance( 'Orderinfo', 'J2StoreTable' )
                             ->getClone();
        $orderinfo->load( [ 'order_id' => $data['order_id'] ] );

        $name        = $orderinfo->billing_first_name . ' ' . $orderinfo->billing_last_name;
        $all_billing = $orderinfo->all_billing;
        $all_billing = json_decode( $all_billing );
        $mail        = $all_billing->email->value;
        $phone       = $orderinfo->billing_phone_2;

        if ( empty( $phone ) )
        {
            $phone = !empty( $orderinfo->billing_phone_1 ) ? $orderinfo->billing_phone_1 : '';
        }

        // Load order
        F0FTable::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_j2store/tables' );
        $orderpayment = F0FTable::getInstance( 'Order', 'J2StoreTable' )
                                ->getClone();
        $orderpayment->load( $data['orderpayment_id'] );

        if ( $vars->user_id == NULL || $vars->user_id == '' || $vars->marketing_id == NULL || $vars->marketing_id == '' )
        {
            $msg         = 'لطفا شناسه کاربری و شناسه کسب و کار را برای افزونه رای پی تنظیم نمایید .';
            $vars->error = $msg;
            $orderpayment->add_history( $msg );
            $orderpayment->store();

            return $this->_getLayout( 'prepayment', $vars );
        }
        else
        {
            $user_id = $vars->user_id;
            $marketing_id = $vars->marketing_id;
            $sandbox = !($vars->sandbox == 'no');
            //$sandbox = !($this->params->get('sandbox', '') == 'no');

            $amount   = round( $vars->orderpayment_amount, 0 );
            $desc     = ' خرید از فروشگاه چی 2 استور با شماره سفارش  ' . $vars->order_id;
            $callback = JRoute::_( JURI::root() . "index.php?option=com_j2store&view=checkout" ) . '&orderpayment_type=' . $vars->orderpayment_type  . '&order_id=' . $data['orderpayment_id'] . '&task=confirmPayment';
            $invoice_id             = round(microtime(true) * 1000);

            if ( empty( $amount ) )
            {
                $msg         = 'مبلغ پرداخت صحیح نمی باشد.';
                $vars->error = $msg;
                $orderpayment->add_history( $msg );
                $orderpayment->store();

                return $this->_getLayout( 'prepayment', $vars );
            }

            $data = array(
                'amount'       => strval($amount),
                'invoiceID'    => strval($invoice_id),
                'userID'       => $user_id,
                'redirectUrl'  => $callback,
                'factorNumber' => strval($data['orderpayment_id']),
                'marketingID' => $marketing_id,
                'email'        => $mail,
                'mobile'       => $phone,
                'fullName'     => $name,
                'comment'      => $desc,
                'enableSandBox'      => $sandbox
            );

            $url  = 'https://api.raypay.ir/raypay/api/v1/Payment/pay';
			$options = array('Content-Type: application/json');
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HTTPHEADER,$options );
			$result = curl_exec($ch);
			$result = json_decode($result );
			$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
//            $result = $this->http->post($url, json_encode($data, true), $options);
//            $result = json_decode($result->body);
//            $http_status = $result->StatusCode;

            if ( $http_status != 200 || empty($result) || empty($result->Data) )
            {
                $msg         = sprintf('خطا هنگام ایجاد تراکنش. کد خطا: %s - پیام خطا: %s', $http_status, $result->Message);
                $vars->error = $msg;
                $vars->data = json_encode($data);
                $orderpayment->add_history( $msg );
                $orderpayment->store();

                return $this->_getLayout( 'prepayment', $vars );
            }

            // Save invoice id
            $orderpayment->transaction_id = $invoice_id;
            $orderpayment->store();


            $token = $result->Data;
            $link='https://my.raypay.ir/ipg?token=' . $token;
            $vars->link = $link;
            return $this->_getLayout( 'prepayment', $vars );
        }
    }

    function _postPayment( $data ) {
        $this->http = HttpFactory::getHttp();
        $vars     = new JObject();
        $app      = JFactory::getApplication();
        $jinput   = $app->input;
      ////////////////////////////////  $invoiceId = $jinput->get->get('?invoiceID', '', 'STRING');
        $orderId = $jinput->get->get('order_id', '', 'STRING');

        F0FTable::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_j2store/tables' );
        $orderpayment = F0FTable::getInstance( 'Order', 'J2StoreTable' )
                                ->getClone();

        if ( empty( $orderId ) )
        {
            $app->enqueueMessage( 'خطا هنگام بازگشت از درگاه پرداخت', 'Error' );
            $vars->message = 'خطا هنگام بازگشت از درگاه پرداخت';
            return $this->return_result( $vars );
        }

        if ( ! $orderpayment->load( $orderId ) )
        {
            $app->enqueueMessage( 'سفارش پیدا نشد.', 'Error' );
            $vars->message = 'سفارش پیدا نشد.';
            return $this->return_result( $vars );
        }

        if ( $orderpayment->get( 'transaction_status' ) == 'Processed' || $orderpayment->get( 'transaction_status' ) == 'Confirmed' )
        {
            $app->enqueueMessage( 'تراکنش قبلا پردازش شده است.', 'Message' );
            $vars->message = 'تراکنش قبلا پردازش شده است.';
            return $this->return_result( $vars );
        }

        // Save transaction details based on posted data.
        $payment_details           = new JObject();
        $payment_details->order_id = $orderId;

        $orderpayment->transaction_details = json_encode( $payment_details );
        $orderpayment->store();

        $url = 'https://api.raypay.ir/raypay/api/v1/Payment/verify';
		$options = array('Content-Type: application/json');

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($_POST));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HTTPHEADER,$options );
		$result = curl_exec($ch);
		$result = json_decode($result );
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
        //$options = array('Content-Type' => 'application/json');
        //$result = $this->http->post($url, json_encode($data, true), $options);
        //$result = json_decode($result->body);
        //$http_status = $result->StatusCode;

        if ( $http_status != 200 )
        {
            $msg = sprintf('خطا هنگام بررسی تراکنش. کد خطا: %s - پیام خطا: %s', $http_status, $result->Message);
            $app->enqueueMessage( $msg, 'Error' );
            $orderpayment->add_history( $msg );
            // Set transaction status to 'Failed'
            $orderpayment->update_status(3);
            $orderpayment->store();

            $vars->message = $msg;
            return $this->return_result( $vars );
        }

        $state           = $result->Data->Status;
        $verify_order_id = $result->Data->FactorNumber;
        $verify_invoice_id = $result->Data->InvoiceID;
        $verify_amount   = $result->Data->Amount;

        if ($state === 1)
        {
            $verify_status = 'پرداخت موفق';
        }
        else
        {
            $verify_status = 'پرداخت ناموفق';
        }

        // Update transaction details
        $orderpayment->transaction_details = json_encode( $result );

        if ( empty($verify_order_id) || empty($verify_amount) || $state !== 1 )
        {

            $msg  = 'پرداخت ناموفق بوده است. شناسه ارجاع بانکی رای پی : ' . $verify_invoice_id;
            $orderpayment->add_history($msg);
            $app->enqueueMessage( $verify_status, 'Error' );
            // Set transaction status to 'Failed'
            $orderpayment->update_status(3);
            $orderpayment->store();

            $vars->message = $msg;
            return $this->return_result( $vars );
        }
        else
        { // Payment is successful.
            $msg  = 'پرداخت شما با موفقیت انجام شد.';
            $vars->message = $msg;
            // Set transaction status to 'PROCESSED'
            $orderpayment->transaction_status = 'PROCESSED';
            $app->enqueueMessage( $verify_status, 'message' );
            $orderpayment->add_history( $msg );

            if ( $orderpayment->store() )
            {
                $orderpayment->payment_complete();
                $orderpayment->empty_cart();
            }
        }
        return $this->return_result( $vars );
    }



    protected function return_result($vars) {
        return $this->_getLayout( 'postpayment', $vars );
    }
}
