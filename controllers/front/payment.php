<?php

/**
 * @package       ICEPAY Payment Module for Prestashop
 * @copyright     (c) 2016-2018 ICEPAY. All rights reserved.
 * @license       BSD 2 License, see https://github.com/ICEPAYdev/Prestashop/blob/master/LICENSE.md
 */

class IcepayPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    

    public function postProcess()
    {
        $cart = $this->context->cart;

//        if (Tools::getIsset('pmCode') || Tools::getIsset('pmIssuer')) {
//            $this->errors[] = $this->trans('Error', array(), 'Modules.Icepay.Error');
//        }

        // Check if cart exists and all fields are set
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        } //TODO:Error

        // Check if module is enabled
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == $this->module->name) {
                $authorized = true;
            }
        }
        if (!$authorized) {
            die('This payment method is not available.');
        }

        // Check if customer exists
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Set datas
        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $extra_vars = array(
            '{total_to_pay}' => Tools::displayPrice($total),
        );

        $lang = Language::getLanguage($cart->id_lang);
        ;

        // Validate order
        $this->module->validateOrder((int)$cart->id, Configuration::get('PS_OS_ICEPAY_OPEN'), $total, $this->module->displayName, null, $extra_vars, (int)$currency->id, true, $customer->secure_key);
        $order = new Order($this->module->currentOrder);


        try {
            $paymentObj = new Icepay_PaymentObject();
            $paymentObj->setAmount($cart->getOrderTotal(true, Cart::BOTH) * 100)
                ->setCountry($this->context->country->iso_code)
                ->setLanguage(strtoupper($lang["iso_code"]))
                ->setIssuer(pSQL(Tools::getValue('pmIssuer')))
                ->setPaymentMethod(pSQL(Tools::getValue('pmCode')))
                ->setCurrency($currency->iso_code)
                ->setOrderID($order->id)
                ->setReference($order->reference);

            if (Configuration::get('ICEPAY_TESTPREFIX')) {
                $paymentObj->setDescription('TEST_' . $this->module->cDescription);
            } else {
                $paymentObj->setDescription($this->module->cDescription);
            }

            $webservice = new Icepay_Webservice_Pay();
            $webservice
                ->setMerchantID(Configuration::get('ICEPAY_MERCHANTID'))
                ->setSecretCode(Configuration::get('ICEPAY_SECRETCODE'))
                ->setSuccessURL($this->context->link->getModuleLink($this->module->name, 'paymentreturn', array('id_cart' => (int)$cart->id, 'id_module' => (int)$this->module->id, 'id_order' => $this->module->currentOrder, 'key' => $customer->secure_key), true))
                ->setErrorURL($this->context->link->getModuleLink($this->module->name, 'paymentreturn', array('id_cart' => (int)$cart->id, 'id_module' => (int)$this->module->id, 'id_order' => $this->module->currentOrder, 'key' => $customer->secure_key), true));
            $webservice->setupClient();


            $webservice->addToExtendedCheckoutList(array('AFTERPAY'));

            if ($webservice->isExtendedCheckoutRequiredByPaymentMethod(Tools::getValue('pmCode'))) {
                throw new Exception("Extended checkout is not supported");
            }

            $transactionObj = ($webservice->isExtendedCheckoutRequiredByPaymentMethod(Tools::getValue('pmCode'))) ? $webservice->extendedCheckout($paymentObj) : $webservice->checkOut($paymentObj);
            Tools::redirectLink($transactionObj->getPaymentScreenURL());
        } catch (Exception $e) {
            if (isset($order)) {
                $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
            }

            $this->errors[] = $this->l($e->getMessage());
            $this->redirectWithNotifications($this->context->link->getPageLink('order', true, null, array('step' => '3')));
        }
    }
}
