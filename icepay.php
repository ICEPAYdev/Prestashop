<?php

/**
 * @package       ICEPAY Payment Module for Prestashop
 * @copyright     (c) 2016-2018 ICEPAY. All rights reserved.
 * @license       BSD 2 License, see https://github.com/ICEPAYdev/Prestashop/blob/master/LICENSE.md
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    die('No direct script access');
}

require_once(dirname(__FILE__).'/classes/IcepayPaymentMethod.php');
require_once(dirname(__FILE__).'/restapi/src/Icepay/API/Autoloader.php');


class Icepay extends PaymentModule
{
    protected $_errors = array();

    //	protected $validationUrl;

    public function __construct()
    {
        $this->name                   = 'icepay';
        $this->tab                    = 'payments_gateways';
        $this->version                = '2.3.1';
        $this->author                 = 'ICEPAY B.V.';
        $this->need_instance          = 1;
        $this->bootstrap              = true;
        $this->controllers            = array('payment', 'validation');
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        parent::__construct();

        $this->displayName      = $this->l('ICEPAY Payment Module');
        $this->description      = $this->l('ICEPAY Payment Module for PrestaShop');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        $this->dbPmInfo         = _DB_PREFIX_ . 'icepay_pminfo';
        $this->dbRawData        = _DB_PREFIX_ . 'icepay_rawdata';

        $this->thankYouPageUrl = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . '?fc=module&module=icepay&controller=paymentreturn';
        $this->errorPageUrl = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . '?fc=module&module=icepay&controller=paymentreturn';
        $this->validationUrl = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . '?fc=module&module=icepay&controller=postback';

        $this->setModuleSettings();
        $this->checkModuleRequirements();
    }




    public function install()
    {
        // Call install parent method
        if (!parent::install()) {
            return false;
        }

        // Register hooks
        if (!$this->registerHook('paymentOptions') ||
            !$this->registerHook('paymentReturn')) {
            return false;
        }

        //Install required missing states
        if (!$this->installIcepayOpenOrderState()) {
            return false;
        }

        if (!$this->installIcepayAuthOrderState()) {
            return false;
        }

        // Install admin tab
        if (!$this->installTab('AdminPayment', 'AdminIcepay', 'ICEPAY')) {
            return false;
        }

        //Create table for ICEPAY payment method configuration
        Db::getInstance()->execute("CREATE TABLE {$this->dbPmInfo} (
			id_icepay_pminfo INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
			id_shop INT NOT NULL,
			active INT DEFAULT 0,
			displayname VARCHAR(100), 
			readablename VARCHAR(100),
			pm_code VARCHAR(25),
			position TINYINT(1)
		)");

        //Create raw data storage
        Db::getInstance()->execute("CREATE TABLE {$this->dbRawData} (    
			id_shop INT NOT NULL,
			raw_pm_data MEDIUMTEXT
		)");

        return true;
    }


    public function uninstall()
    {
        // Call uninstall parent method
        if (!parent::uninstall()) {
            return false;
        }

        // Uninstall admin tab
        if (!$this->uninstallTab('AdminIcepay')) {
            return false;
        }

        //Unregister hooks
        $this->unregisterHook('paymentOptions');
        $this->unregisterHook('paymentReturn');

        //Drop tables
        Db::getInstance()->execute("DROP TABLE if exists {$this->dbPmInfo}");
        Db::getInstance()->execute("DROP TABLE if exists {$this->dbRawData}");

        $this->deleteModuleSettings();

        return true;
    }

    public function getHookController($hook_name)
    {
        // Include the controller file
        require_once(dirname(__FILE__).'/controllers/hook/'. $hook_name.'.php');

        // Build dynamically the controller name
        $controller_name = $this->name.$hook_name.'Controller';

        // Instantiate controller
        $controller = new $controller_name($this, __FILE__, $this->_path);

        // Return the controller
        return $controller;
    }


    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $activeShopID = (int)Context::getContext()->shop->id;
        $cart = $this->context->cart;
        $currency = Currency::getCurrency($cart->id_currency);
        $storedPaymentMethod = Db::getInstance()->executeS("SELECT raw_pm_data FROM `{$this->dbRawData}` WHERE `id_shop` = $activeShopID");

        if (empty($storedPaymentMethod)) {
            return;
        }

        $filter = new Icepay_Webservice_Filtering();
        $filter->loadFromArray(unserialize($storedPaymentMethod[0]['raw_pm_data']));
        $filter->filterByCurrency($currency['iso_code'])->filterByCountry($this->context->country->iso_code)->filterByAmount($cart->getOrderTotal(true, Cart::BOTH) * 100);

        //prepare values for WHERE IN ()
        $sqlFilter = array();
        foreach ($filter->getFilteredPaymentmethods() as $paymentMethod) {
            $sqlFilter[] = $paymentMethod->PaymentMethodCode;
        }
        $sqlFilter =  "'".implode("', '", $sqlFilter)."'";

        //get enabled payment methods
        $enabledPaymentMethods = Db::getInstance()->executeS("SELECT `active`, `position`, `displayname`, `pm_code` FROM `{$this->dbPmInfo}` WHERE `id_shop` = {$activeShopID} AND `active` = 1 AND `pm_code` IN ({$sqlFilter}) ORDER BY position ASC");

        if ($enabledPaymentMethods) {
            $rawData = Db::getInstance()->executeS("SELECT raw_pm_data FROM `{$this->dbRawData}` WHERE `id_shop` = {$activeShopID}");
            $mt = new Icepay_Webservice_Paymentmethod();
            $pmData = $mt->loadFromArray(unserialize($rawData[0]['raw_pm_data'])); //TODO: no data found

            $paymentMethods = array();
            foreach ($enabledPaymentMethods as $enabledPaymentMethod) {
                $newOption = new PaymentOption();
                $newOption->setModuleName($this->name)
                    ->setCallToActionText($this->l($enabledPaymentMethod['displayname']))
                    ->setAction($this->context->link->getModuleLink($this->name, 'payment', array('pmCode' => $enabledPaymentMethod['pm_code']), true))//validation
                    ->setLogo($this->_path . '/views/img/paymentmethods/' . strtolower($enabledPaymentMethod['pm_code']) . '.png');
                //				->setAdditionalInformation($this->fetch('module:icepay/views/templates/hook/icepay_intro.tpl'));

                $pMethod = $pmData->selectPaymentMethodByCode($enabledPaymentMethod['pm_code']);
                $issuerList = $pMethod->getIssuers();

                if ($issuerList) {
                    $newOption->setForm($this->getIssuerForm($enabledPaymentMethod['pm_code'], $issuerList));
                }

                $paymentMethods[] = $newOption;
            }

            return $paymentMethods;
        }
    }

    public function hookPaymentReturn($params)
    {
        $controller = $this->getHookController('paymentReturn');
        return $controller->run($params);
    }

    protected function getIssuerForm($paymentMethodCode, $issuerList)
    {
        $this->context->smarty->assign([
            'action' => $this->context->link->getModuleLink($this->name, 'payment', array(), true),
            'paymentMethodCode' => $paymentMethodCode,
            'issuerList' => $issuerList
        ]);
        return $this->context->smarty->fetch('module:icepay/views/templates/front/issuerList.tpl');
    }


    public function getContent()
    {
        if (!Tools::getValue('ajax')) {
            $controller = $this->getHookController('getContent');
            return $controller->run();
        }
    }

    public function checkCurrency($cart)
    {
        // Get cart currency and enabled currencies for this module
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        // Check if cart currency is one of the enabled currencies
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        // Return false otherwise
        return false;
    }

    private function installTab($parent, $class_name, $name)
    {
        // Create new admin tab
        $tab = new Tab();
        $tab->id_parent = (int)Tab::getIdFromClassName($parent);
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $name;
        }
        $tab->class_name = $class_name;
        $tab->module = $this->name;
        $tab->active = 1;
        return $tab->add();
    }

    private function uninstallTab($class_name)
    {
        // Retrieve Tab ID
        $id_tab = (int)Tab::getIdFromClassName($class_name);

        // Load tab
        $tab = new Tab((int)$id_tab);

        // Delete it
        return $tab->delete();
    }


    private function installIcepayOpenOrderState()
    {
        if (Configuration::get('PS_OS_ICEPAY_OPEN') < 1) {
            $order_state              = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages(false) as $language) {
                if (Tools::strtolower($language['iso_code']) == 'nl') {
                    $order_state->name[(int)$language['id_lang']] = 'In afwachting van betaling';
                } else {
                    $order_state->name[(int)$language['id_lang']] = 'Awaiting payment';
                }
            }
            $order_state->invoice     = false;
            $order_state->send_email  = false;
            $order_state->module_name = $this->name;
            $order_state->color       = "RoyalBlue";
            $order_state->unremovable = true;
            $order_state->hidden      = false;
            $order_state->logable     = false;
            $order_state->delivery    = false;
            $order_state->shipped     = false;
            $order_state->paid        = false;
            $order_state->deleted     = false;
            //$order_state->template    = "order_changed";

            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue("PS_OS_ICEPAY_OPEN", $order_state->id);

                // We copy the module logo in order state logo directory
                if (file_exists(dirname(dirname(dirname(__file__))) . '/img/os/10.gif')) { //TODO
                    copy(dirname(dirname(dirname(__file__))) . '/img/os/10.gif', dirname(dirname(dirname(__file__))) . '/img/os/' . $order_state->id . '.gif');
                }
            } else {
                return false;
            }
        }
        return true;
    }

    private function installIcepayAuthOrderState()
    {
        if (Configuration::get('PS_OS_ICEPAY_AUTH') < 1) {
            $order_state              = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages(false) as $language) {
                //				if (Tools::strtolower($language['iso_code']) == 'nl')
                //					$order_state->name[(int)$language['id_lang']] = '';
                //				else
                $order_state->name[(int)$language['id_lang']] = 'Payment Authorized';
            }
            $order_state->invoice     = false;
            $order_state->send_email  = false;
            $order_state->module_name = $this->name;
            $order_state->color       = "RoyalBlue";
            $order_state->unremovable = true;
            $order_state->hidden      = false;
            $order_state->logable     = false;
            $order_state->delivery    = false;
            $order_state->shipped     = false;
            $order_state->paid        = false;
            $order_state->deleted     = false;
            //$order_state->template    = "order_changed";

            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue("PS_OS_ICEPAY_AUTH", $order_state->id);

                // We copy the module logo in order state logo directory
                if (file_exists(dirname(dirname(dirname(__file__))) . '/img/os/10.gif')) {
                    copy(dirname(dirname(dirname(__file__))) . '/img/os/10.gif', dirname(dirname(dirname(__file__))) . '/img/os/' . $order_state->id . '.gif');
                }
                //				copy(dirname(__FILE__).'/logo.gif', dirname(__FILE__).'/../../img/os/'.$order_state->id.'.gif'); //TODO
//				copy(dirname(__FILE__).'/logo.gif', dirname(__FILE__).'/../../img/tmp/order_state_mini_'.$order_state->id.'.gif');
            } else {
                return false;
            }
        }
        return true;
    }

    private function checkModuleRequirements()
    {
        $this->_errors = array(); //TODO

        if (!Icepay_Parameter_Validation::merchantID($this->merchantID) || !Icepay_Parameter_Validation::secretCode($this->secretCode)) {
            $this->_errors['merchantERR'] = $this->l('To configure payment methods we need to know the mandatory fields in the configuration above');
        }
    }

    private function setModuleSettings()
    {
        $this->merchantID   = Configuration::get('ICEPAY_MERCHANTID');
        $this->testPrefix   = Configuration::get('ICEPAY_TESTPREFIX');
        $this->secretCode   = Configuration::get('ICEPAY_SECRETCODE');
        $this->cDescription = Configuration::get('ICEPAY_DESCRIPTION');
    }

    private function deleteModuleSettings()
    {
        Configuration::deleteByName('ICEPAY_MERCHANTID');
        Configuration::deleteByName('ICEPAY_TESTPREFIX');
        Configuration::deleteByName('ICEPAY_SECRETCODE');
        Configuration::deleteByName('ICEPAY_DESCRIPTION');
    }
}
