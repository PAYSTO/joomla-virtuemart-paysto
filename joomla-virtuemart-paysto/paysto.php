<?php

defined('_JEXEC') or die;

if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}


class plgVMPaymentPaysto extends vmPSPlugin
{

    const STATUS_TAX_OFF = 'N';
    const MAX_POS_IN_CHECK = 100;
    const BEGIN_POS_IN_CHECK = 0;


    public $order_prefix;


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
        $orderId = $this->getOrderIdWithPrefix($order['details']['BT']->virtuemart_order_id,
            $order['details']['BT']->virtuemart_paymentmethod_id);

        foreach ($order['items'] as $product) {
            $lineArr = [];
            $prices = $product->prices;
            $lineArr[] = '№' . $pos;
            $lineArr[] = substr($product->product_sku, 0, 30);
            $lineArr[] = substr($product->product_name, 0, 254);
            $lineArr[] = $product->product_quantity;
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
        $amount = number_format(round($totalInPaymentCurrency['value'], 2), 2, '.', '');
        $new_status = $method->status_pending;
        $currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');

        $fields = array(
            'x_description' => $method->description . ' ' . $orderId,
            'x_login' => $method->merchant_id,
            'x_amount' => $amount,
            'x_email' => $order['details']['BT']->email,
            'x_currency_code' => $currency_code_3,
            'x_fp_sequence' => $orderId,
            'x_fp_timestamp' => $now,
            'x_fp_hash' => $this->get_x_fp_hash($method->merchant_id, $orderId, $now,
                $amount, $currency_code_3),
            'x_invoice_num' => $orderId,
            'x_relay_response' => "TRUE",
            'x_relay_url' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&action=paysto_result'),
            'x_cust_id' => $order['details']['BT']->virtuemart_paymentmethod_id, // Hack get and right payment method ID
        );

        $order_check = array_merge($order_check, $fields);

        $form = '<form method="POST" action="https://paysto.com/ru/pay/AuthorizeNet" name="vm_paysto_form">';

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
     * Get order ID with prefix
     *
     * @param $orderIdVM
     * @param $paymentMethodId
     * @return string|null
     */
    public function getOrderIdWithPrefix($orderIdVM, $paymentMethodId)
    {
        if (!($method = $this->getVmPluginMethod($paymentMethodId))) {
            return NULL;
        }
        return $method->order_prefix . $orderIdVM;
    }


    /**
     * Get order ID without prefix
     *
     * @param $string
     * @param $paymentMethodId
     * @return mixed|null
     */
    public function getOrderIdWithoutPrefix($string, $paymentMethodId)
    {
        if (!($method = $this->getVmPluginMethod($paymentMethodId))) {
            return NULL;
        }
        if (!$method->order_prefix) {
            $method->order_prefix = $this->order_prefix;
        }
        return str_replace($method->order_prefix, '', $string);
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
        if ($_GET['action'] == 'paysto_result') {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $virtuemart_order_id = $this->getOrderIdWithoutPrefix($_POST["x_invoice_num"], $_POST['x_cust_id']);
                if (!class_exists('VirtueMartModelOrders')) {
                    require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
                }
                $modelOrder = new VirtueMartModelOrders();
                $order = $modelOrder->getOrder($virtuemart_order_id);
                if (!isset($order['details']['BT']->virtuemart_order_id)) {
                    die;
                }
                $method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);
                $acceptServersList = explode(' ', $method->serversList);

                $HTTP_X_FORWARDED_FOR = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '127.0.0.1';
                $HTTP_CF_CONNECTING_IP = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : '127.0.0.1';
                $HTTP_X_REAL_IP = isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : '127.0.0.1';
                $REMOTE_ADDR = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
                $GEOIP_ADDR = isset($_SERVER['GEOIP_ADDR']) ? $_SERVER['GEOIP_ADDR'] : '127.0.0.1';

                if ($method->useOnlyList &&
                    ((!in_array($HTTP_X_FORWARDED_FOR, $this->PaystoServers)) &&
                        (!in_array($HTTP_CF_CONNECTING_IP, $this->PaystoServers)) &&
                        (!in_array($HTTP_X_REAL_IP, $this->PaystoServers)) &&
                        (!in_array($REMOTE_ADDR, $this->PaystoServers)) &&
                        (!in_array($GEOIP_ADDR, $this->PaystoServers)))) {
                    if ($order['details']['BT']->order_status == $method->status_success) {
                        if (!class_exists('VirtueMartCart')) {
                            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
                        }
                        $cart = VirtueMartCart::getCart();
                        $cart->emptyCart();
                        return true;
                    } else {
                        $this->plgVmOnPaymentFailResponseReceived();
                    }
                }

                $hash = $this->get_x_MD5_Hash($method->merchant_id, $_POST['x_trans_id'], $_POST['x_amount']);

                if ($_POST["x_MD5_Hash"] == $hash && $_POST['x_response_code'] == 1) {
                    if ($order['details']['BT']->order_status == $method->status_pending) {
                        $order['order_status'] = $method->status_success;
                        $order['customer_notified'] = 1;
                        $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
                    }
                }
            }
            die;
        }
    }


    /**
     * Success payment
     *
     * @param $html
     * @return bool
     */
    public function plgVmOnPaymentSuccessResponseReceived()
    {
        if (!class_exists('VirtueMartCart'))
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();
        return true;
    }


    /**
     * Fail payment
     *
     * @param $html
     * @return bool
     */
    public function plgVmOnPaymentFailResponseReceived()
    {
        $mainframe = JFactory::getApplication();
        $mainframe->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart', false));
    }


    /**
     * Return client IP
     *
     * @return mixed
     */
    protected function getClientIP()
    {
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


    /*******************************************************************************************************************
     * Don used funcltion in this plugin
     * I think its hooks
     ******************************************************************************************************************/

    function pre($data)
    {
        echo '<pre>', print_r($data, 1), '</pre>';
    }

    function toFloat($sum)
    {
        $sum = floatval($sum);
        if (strpos($sum, ".")) {
            $sum = round($sum, 2);
        } else {
            $sum = $sum . ".0";
        }
        return $sum;
    }

    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id)
    {
        if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
            return NULL;
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return NULL;
        }
        VmConfig::loadJLang('com_virtuemart');

        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
        if ($paymentTable->email_currency) {
            $html .= $this->getHtmlRowBE('STANDARD_EMAIL_CURRENCY', $paymentTable->email_currency);
        }
        $html .= '</table>' . "\n";
        return $html;
    }

    function checkConditions($cart, $method, $cart_prices)
    {
        $this->convert_condition_amount($method);
        $amount = $this->getCartAmount($cart_prices);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount OR ($method->min_amount <= $amount AND ($method->max_amount == 0)));
        if (!$amount_cond) {
            return FALSE;
        }
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries)) {
            return TRUE;
        }

        return FALSE;
    }

    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        return $this->OnSelectCheck($cart);
    }

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
        return;
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }


    public function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart)
    {
        return null;
    }

    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {

        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {

        return $this->setOnTablePluginParams($name, $id, $table);
    }

    function plgVmOnPaymentNotification()
    {
        return null;
    }


    /**
     * Logger function for debug
     *
     * @param  [type] $var  [description]
     * @param  string $text [description]
     * @return [type]       [description]
     */
    public function logger($var, $text = '')
    {
        // Название файла
        $loggerFile = __DIR__ . '/logger.log';
        if (is_object($var) || is_array($var)) {
            $var = (string)print_r($var, true);
        } else {
            $var = (string)$var;
        }
        $string = date("Y-m-d H:i:s") . " - " . $text . ' - ' . $var . "\n";
        file_put_contents($loggerFile, $string, FILE_APPEND);
        return;
    }

}