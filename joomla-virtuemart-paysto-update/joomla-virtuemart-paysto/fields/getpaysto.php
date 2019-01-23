<?php
defined('JPATH_BASE') or die();

jimport('joomla.form.formfield');
class JFormFieldGetPaysto extends JFormField {

    var $type = 'getPaymsto';

    protected function getInput() {
        JHtml::_('behavior.colorpicker');

        vmJsApi::addJScript( '/plugins/vmpayment/paysto/assets/js/admin.js');

        $url = "https://www.paysto.ru";
        $html = '<h1><a target="_blank" href="'. $url .'">Paysto</a></h1>';

        return $html;
    }
}