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
    
    /**
     * plgVMPaymentPaysto constructor.
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
     *
     *
     * @return mixed
     */
    function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Paysto Table');
    }

    
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

    function getTax($vatTax, $method) {
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
     *
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
        
        $pos = self::BEGIN_POS_IN_CHECK;
        $order_check = array();
        $tax_shipping = self::STATUS_TAX_OFF;

        foreach ($order['items'] as $product) {
            $prices = $product->prices;
            $order_check["LMI_SHOPPINGCART.ITEMS[$pos].NAME"]  = $product->product_name;
            $order_check["LMI_SHOPPINGCART.ITEMS[$pos].QTY"]   = $product->quantity;
            $order_check["LMI_SHOPPINGCART.ITEMS[$pos].PRICE"] = round($product->product_final_price, 2);
            $order_check["LMI_SHOPPINGCART.ITEMS[$pos].TAX"]   = $this->getTax($prices['VatTax'], $method);
            $tax_shipping = $this->getTax($prices['VatTax'], $method);
            $pos++;
        }

        if (!empty($order['details']['BT']->order_shipment)) {
            $price_shipment = $order['details']['BT']->order_shipment + $order['details']['BT']->order_shipment_tax;
            $order_check["LMI_SHOPPINGCART.ITEMS[$pos].NAME"] = strip_tags($cart->cartData['shipmentName']);
            $order_check["LMI_SHOPPINGCART.ITEMS[$pos].QTY"] = 1;
            $order_check["LMI_SHOPPINGCART.ITEMS[$pos].PRICE"] = round($price_shipment, 2);
            $order_check["LMI_SHOPPINGCART.ITEMS[$pos].TAX"] = $tax_shipping;
        }

        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $method->payment_currency);
        $amount = round($totalInPaymentCurrency['value'], 2);
        $new_status = $method->status_pending;
        $currency_code_3 = shopFunctions::getCurrencyByID ($method->payment_currency, 'currency_code_3');

        $fields = array(
            'LMI_PAYMENT_AMOUNT' => $amount,
            'LMI_PAYMENT_DESC' => "Оплата счета # " . $order['details']['BT']->virtuemart_order_id,
            'LMI_PAYMENT_NO' => $order['details']['BT']->virtuemart_order_id,
            'LMI_MERCHANT_ID' => $method->merchant_id,
            'LMI_CURRENCY' => $currency_code_3,
            'LMI_PAYER_EMAIL' => $order['details']['BT']->email,
            'LMI_PAYMENT_NOTIFICATION_URL' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&action=paysto_result'),
            'LMI_SUCCESS_URL' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&action=paysto_success'),
            'LMI_FAILURE_URL' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id),
            'sign' => md5($amount . $order['details']['BT']->virtuemart_order_id . $method->secret_key),
        );

        $order_check = array_merge($order_check, $fields);
        //$this->pre($order_check);exit;
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

    function pre($data) {
        echo '<pre>',print_r($data,1),'</pre>';
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

    function plgVmOnPaymentResponseReceived(&$html)
    {
        $get = JRequest::get();

        if ($get['action'] == 'paysto_success') {
            if (!class_exists('VirtueMartCart'))
                require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
            $cart = VirtueMartCart::getCart();
            $cart->emptyCart();

            return true;
        } else if ($get['action'] == 'paysto_result') {
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
        $arr = [$x_login, $x_fp_sequence, $x_fp_timestamp, $x_amount, $x_currency_code];
        $str = implode('^', $arr);
        return hash_hmac('md5', $str, $this->config->get('paysto_secret_key'));
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
        return md5($this->config->get('paysto_secret_key') . $x_login . $x_trans_id . $x_amount);
    }

    
    private function getBasketDetails(){
        $user = JFactory::getUser();
        $cart = VirtueMartCart::getCart(false);
        $items = $cart->products;
        $prices = $cart->cartPrices;

        $params = array();
        if ($items) {
            $i = 0;
            //  ITEMS
            foreach($items as $key => $item){
                $i++;
                $prefix = 'CRITERION.POS_'.sprintf('%02d', $i);

                $params[$prefix.'.POSITION']        = $i;
                $params[$prefix.'.QUANTITY']    = (int)$item->quantity;
                if (empty($item->product_unit)){ $item->product_unit = 'Stk.'; }
                $params[$prefix.'.UNIT']                = $item->product_unit;
                #price in cents
                $params[$prefix.'.AMOUNT_UNIT_GROSS'] = ($prices[$key]['basePriceWithTax'] * 100);
                $params[$prefix.'.AMOUNT_GROSS'] = ($prices[$key]['subtotal_with_tax'] * 100);
                $item->product_name = preg_replace('/%/','Proz.', $item->product_name);
                $item->product_name = preg_replace('/("|\'|!|$|=)/',' ', $item->product_name);
                $params[$prefix.'.TEXT']            = strlen($item->product_name) > 100 ? substr($item->product_name, 0, 90) . '...' : $item->product_name;
                $params[$prefix.'.ARTICLE_NUMBER']  = $item->product_sku;
                $params[$prefix.'.PERCENT_VAT']     = sprintf('%1.2f', $prices[$key]['VatTax'][$item->product_tax_id]['1']);
                $params[$prefix.'.ARTICLE_TYPE'] = 'goods';
            }

            //  SHIPPING
            require(VMPATH_ADMIN . DS . 'models' . DS . 'shipmentmethod.php');
            $vmms = new VirtueMartModelShipmentmethod();
            $shipmentInfo = $vmms->getShipments();

            foreach($shipmentInfo as $skey => $svalue){
                if($svalue->virtuemart_shipmentmethod_id == $cart->virtuemart_shipmentmethod_id){
                    $shipmentData = array();
                    foreach (explode("|", $svalue->shipment_params) as $line) {
                        list($key, $value) = explode('=', $line, 2);
                        $shipmentData[$key] = str_replace('"','',$value);
                    }
                    $shipmentTaxId = $shipmentData['tax_id'];
                    $shipmentTax = sprintf('%1.2f',$cart->cartData['VatTax'][$shipmentTaxId]['calc_value']);
                }
            }
            $i++;
            $prefix = 'CRITERION.POS_'.sprintf('%02d', $i);

            $params[$prefix.'.POSITION']        = $i;
            $params[$prefix.'.QUANTITY']    = '1';
            $params[$prefix.'.UNIT']                = 'Stk.';
            $params[$prefix.'.AMOUNT_UNIT_GROSS'] = ($prices['salesPriceShipment'] * 100);
            $params[$prefix.'.AMOUNT_GROSS']    = ($prices['salesPriceShipment'] * 100);
            $params[$prefix.'.TEXT']            = 'Shipping';
            $params[$prefix.'.ARTICLE_NUMBER']  = 'Shipping';
            $params[$prefix.'.PERCENT_VAT'] = $shipmentTax;
            $params[$prefix.'.ARTICLE_TYPE']    = 'shipment';

            //  COUPON
            if(isset($prices['couponValue']) && ($prices['couponValue'] != '')){
                $i++;
                $prefix = 'CRITERION.POS_'.sprintf('%02d', $i);

                $params[$prefix.'.POSITION']        = $i;
                $params[$prefix.'.QUANTITY']    = '1';
                $params[$prefix.'.UNIT']                = 'Stk.';
                $params[$prefix.'.AMOUNT_UNIT_GROSS'] = ($prices['couponValue'] * 100);
                $params[$prefix.'.AMOUNT_GROSS']    = ($prices['couponValue'] * 100);
                $params[$prefix.'.TEXT']            = 'Coupon';
                $params[$prefix.'.ARTICLE_NUMBER']  = 'Coupon';
                $params[$prefix.'.PERCENT_VAT'] = $prices['couponTax'];
                $params[$prefix.'.ARTICLE_TYPE']    = 'voucher';
            }
        }
        return $params;
    }
}