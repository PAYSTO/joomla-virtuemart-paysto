<?php
use \Joomla\Input\Input;

defined('_JEXEC') or die;

if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}


class plgVMPaymentPaysto extends vmPSPlugin
{

    const STATUS_TAX_OFF = 'N';
    const MAX_POS_IN_CHECK = 100;
    const BEGIN_POS_IN_CHECK = 0;


    /**
     * plgVMPaymentPaysto constructor.
     *
     * @param $subject
     * @param $config
     *
     */
    function __construct(&$subject, $config)
    {

        parent::__construct($subject, $config);
        $this->_loggable = TRUE;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $varsToPush = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }


    /**
     * Create transaction table
     *
     * @return mixed
     */
    function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Paysto Table');
    }


    /**
     * Transaction table created
     *
     * @return array
     */
    function getTableSQLFields()
    {
        $SQLfields = array(
            'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency' => 'char(3)',
            'email_currency' => 'char(3)',
            'cost_per_transaction' => 'decimal(10,2)',
            'cost_percent_total' => 'decimal(10,2)',
            'tax_id' => 'smallint(1)'
        );

        return $SQLfields;
    }


    /**
     * Get taxes
     *
     * @param $vatTax
     * @param $method
     * @return string
     */
    function getTax($vatTax, $method)
    {
        if (!empty($vatTax) && $method) {
            $method = json_decode($method->list_tax);

            foreach ($vatTax as $tax) {
                $result_tax = $tax[0];
            }

            if ($method->vat && !empty($result_tax)) {
                foreach ($method->vat as $key => $val) {
                    if ($val == $result_tax) {
                        return $method->tax[$key];
                    }
                }
            }
        }

        return self::STATUS_TAX_OFF;
    }


    /**
     * Generate payment form
     *
     * @param VirtueMartCart $cart
     * @param $order
     * @return bool|null
     */
    function plgVmConfirmedOrder(VirtueMartCart $cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return NULL;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }

        // Set null value for products list
        $order_check["x_line_item"] = '';
        //Set pos for 0
        $pos = self::BEGIN_POS_IN_CHECK;
        $order_check = array();
        $tax_shipping = self::STATUS_TAX_OFF;
        // Current timestamp
        $now = time();

        foreach ($order['items'] as $product) {
            $lineArr = [];
            $prices = $product->prices;
            $lineArr[] = '№' . $pos;
            $lineArr[] = substr($product->product_sku, 0, 30);
            $lineArr[] = substr($product->product_name, 0, 254);
            $lineArr[] = substr($product->quantity, 0, 254);
            $lineArr[] = number_format($product->product_final_price, 2, '.', '');
            $lineArr[] = $this->getTax($prices['VatTax'], $method);
            $order_check['x_line_item'] .= implode('<|>', $lineArr) . "0<|>\n";
            $pos++;
        }

        if (!empty($order['details']['BT']->order_shipment)) {

            $lineArr = [];
            $price_shipment = $order['details']['BT']->order_shipment + $order['details']['BT']->order_shipment_tax;
            $lineArr[] = '№' . $pos;
            $lineArr[] = 'Delivery';
            $lineArr[] = substr(strip_tags($cart->cartData['shipmentName']), 0, 254);
            $lineArr[] = '1';
            $lineArr[] = number_format($price_shipment, 2, '.', '');
            $lineArr[] = $tax_shipping;
            $order_check['x_line_item'] .= implode('<|>', $lineArr) . "0<|>\n";
        }

        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $method->payment_currency);
        $amount = round($totalInPaymentCurrency['value'], 2);
        $new_status = $method->status_pending;
        $currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');

        $fields = array(
            'x_description' => $method->description.' '.$order['details']['BT']->virtuemart_order_id,
            'x_login' => $method->merchant_id,
            'x_amount' => $amount,
            'x_email' => $order['details']['BT']->email,
            'x_currency_code' => $currency_code_3,
            'x_fp_sequence' => $order['details']['BT']->virtuemart_order_id,
            'x_fp_timestamp' => $now,
            'x_fp_hash' => $this->get_x_fp_hash($method->merchant_id, $order['details']['BT']->virtuemart_order_id, $now,
                $amount, $currency_code_3),
            'x_invoice_num' => $order['details']['BT']->virtuemart_order_id,
            'x_relay_response' => "TRUE",
            'x_relay_url' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&action=paysto_result'),
        );

        $order_check = array_merge($order_check, $fields);

        $form = '<form method="POST" action="https://paysto.ru/Payment/Init" name="vm_paysto_form">';

        foreach ($order_check as $key => $value) {
            $form .= '<input type="hidden" name="' . $key . '" value="' . $value . '">';
        }

        $form .= '</form>';

        $form .= ' <script type="text/javascript">';
        $form .= ' document.vm_paysto_form.submit();';
        $form .= ' </script>';

        $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $form, $method->payment_name, $new_status);

        return true;
    }


    /**
     * Get logo images list
     *
     * @param $logo_list
     * @return string
     */
    protected function displayLogos($logo_list)
    {
        $img = "";
        if (!(empty($logo_list))) {
            $url = JURI::root() . 'plugins/vmpayment/paysto/images/';
            if (!is_array($logo_list)) {
                $logo_list = (array)$logo_list;
            }
            foreach ($logo_list as $logo) {
                $alt_text = substr($logo, 0, strpos($logo, '.'));
                $img .= '<span class="vmCartPaymentLogo" ><img style="width: 150px;" align="middle" src="' . $url . $logo . '"  alt="' . $alt_text . '" /></span> ';
            }
        }
        return $img;
    }


    /**
     * Process payment
     *
     * @param $html
     * @return bool
     */
    function plgVmOnPaymentResponseReceived(&$html)
    {
        $get = Input::get();

        if ($get['action'] == 'paysto_result') {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                if (isset($_POST["LMI_PREREQUEST"]) && ($_POST["LMI_PREREQUEST"] == "1" || $_POST["LMI_PREREQUEST"] == "2")) {
                    echo "YES";
                    die;
                } else {
                    if (!class_exists('VirtueMartModelOrders'))
                        require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');

                    $virtuemart_order_id = $_POST["LMI_PAYMENT_NO"];

                    $modelOrder = new VirtueMartModelOrders();
                    $order = $modelOrder->getOrder($virtuemart_order_id);

                    if (!isset($order['details']['BT']->virtuemart_order_id)) {
                        die;
                    }

                    $method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);

                    $hash = base64_encode(pack("H*", hash('sha256', $_POST["LMI_MERCHANT_ID"] . ";" . $_POST["LMI_PAYMENT_NO"] . ";" . $_POST["LMI_SYS_PAYMENT_ID"] . ";" . $_POST["LMI_SYS_PAYMENT_DATE"] . ";" . $_POST["LMI_PAYMENT_AMOUNT"] . ";" . $_POST["LMI_CURRENCY"] . ";" . $_POST["LMI_PAID_AMOUNT"] . ";" . $_POST["LMI_PAID_CURRENCY"] . ";" . $_POST["LMI_PAYMENT_SYSTEM"] . ";" . $_POST["LMI_SIM_MODE"] . ";" . $method->secret_key)));

                    if ($_POST["LMI_HASH"] == $hash && $_POST["sign"] == md5($_POST["LMI_PAYMENT_AMOUNT"] . $_POST['LMI_PAYMENT_NO'] . $method->secret_key)) {
                        if ($order['details']['BT']->order_status == $method->status_pending) {
                            $order['order_status'] = $method->status_success;
                            $order['customer_notified'] = 1;
                            $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
                        }
                    }
                }
            }
            die;
        }
    }


    /**
     *
     *
     * @param $html
     * @return bool
     */
    public function plgVmOnPaymentSuccessResponseReceived(&$html) {
        $get = Input::get();
        if ($get['action'] == 'paysto_success') {
            if (!class_exists('VirtueMartCart'))
                require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
            $cart = VirtueMartCart::getCart();
            $cart->emptyCart();

            return true;
        }

    }

    /**
     * Return client IP
     *
     * @return mixed
     */
    protected function getClientIP(){
        $jinput = JFactory::getApplication()->input;
        return $jinput->server->get('REMOTE_ADDR', '', '');
    }


    /**
     * Return hash md5 HMAC
     *
     * @param $x_login
     * @param $x_fp_sequence
     * @param $x_fp_timestamp
     * @param $x_amount
     * @param $x_currency_code
     * @return false|string
     */
    private function get_x_fp_hash($x_login, $x_fp_sequence, $x_fp_timestamp, $x_amount, $x_currency_code)
    {
        if (!($method = $this->getVmPluginMethod('paysto'))) {
            return NULL;
        }
        $arr = [$x_login, $x_fp_sequence, $x_fp_timestamp, $x_amount, $x_currency_code];
        $str = implode('^', $arr);
        return hash_hmac('md5', $str, $method->secret_key);
    }


    /**
     * Return sign with MD5 algoritm
     *
     * @param $x_login
     * @param $x_trans_id
     * @param $x_amount
     * @return string
     */
    private function get_x_MD5_Hash($x_login, $x_trans_id, $x_amount)
    {
        if (!($method = $this->getVmPluginMethod('paysto'))) {
            return NULL;
        }
        return md5($method->secret_key . $x_login . $x_trans_id . $x_amount);
    }

}