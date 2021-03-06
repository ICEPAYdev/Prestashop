<?php

/**
 * @package       ICEPAY Payment Module for Prestashop
 * @copyright     (c) 2016-2018 ICEPAY. All rights reserved.
 * @license       BSD 2 License, see https://github.com/ICEPAYdev/Prestashop/blob/master/LICENSE.md
 */

class IcepayPostbackModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();

        $this->byIcepayStatus = array(
            Icepay_StatusCode::OPEN => Configuration::get('PS_OS_ICEPAY_OPEN'),
            Icepay_StatusCode::AUTHORIZED => Configuration::get('PS_OS_ICEPAY_AUTH'),
            Icepay_StatusCode::SUCCESS => Configuration::get('PS_OS_PAYMENT'),
            Icepay_StatusCode::ERROR => Configuration::get('PS_OS_ERROR')
        );

        $this->byPrestaStatus = array(
            Configuration::get('PS_OS_ICEPAY_OPEN') => Icepay_StatusCode::OPEN,
            Configuration::get('PS_OS_ICEPAY_AUTH') => Icepay_StatusCode::AUTHORIZED,
            Configuration::get('PS_OS_PAYMENT') => Icepay_StatusCode::SUCCESS,
            Configuration::get('PS_OS_ERROR') => Icepay_StatusCode::ERROR
        );
    }

    public function initContent()
    {
        //skip parent::initContent()
    }

    public function display()
    {
        //Display empty page
    }


    public function postProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            exit('Postback URL installed correctly');
        }

        $icepay = new Icepay_Postback();

        try {
            $icepay->setMerchantID(Configuration::get('ICEPAY_MERCHANTID'))->setSecretCode(Configuration::get('ICEPAY_SECRETCODE'));

            if ($icepay->validate()) {
                $order = new Order($icepay->getOrderID());
                $currentOrderState = $order->getCurrentOrderState();

                if (!Validate::isLoadedObject($order) || !Validate::isLoadedObject($currentOrderState)) {
                    $this->showBadRequestError();
                }

                $msg = new Message();
                $msg->message = strip_tags($msg->message, '<br>');
                $msg->message = "{$icepay->getTransactionString()} | Old state: {$this->byPrestaStatus[$currentOrderState->id]}; ";
                $msg->id_order = $order->id;
                $msg->private = 1;
                $msg->add();

                if ($icepay->canUpdateStatus($this->byPrestaStatus[$order->current_state]) /*|| $order->current_state == cepay_StatusCode::OPEN*/) {
                    if ($icepay->getStatus() == Icepay_StatusCode::SUCCESS) {
                        $order->addOrderPayment(floatval($icepay->getPostback()->amount / 100), $icepay->getPostback()->paymentMethod, $icepay->getPostback()->paymentID);
                        $order->setInvoice(true);
                    }
                    $order->setCurrentState($this->byIcepayStatus[$icepay->getStatus()]);
                }
            } else {
                $this->showBadRequestError('Failed to validate request');
            }
        } catch (Exception $e) {
            $this->showBadRequestError();
        }
    }

    private function showBadRequestError($message = null)
    {
        header('HTTP/1.1 400 Bad Request', true, 400);
        header('Status: 400 Bad Request');
        exit($message);
    }
}
