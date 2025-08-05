<?php
/**
 * 2007-2018 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2018 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
include_once(_PS_MODULE_DIR_.'PayFlex/PayFlexService.php');

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '1');

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * PayFlexMain
 * 
 * This is the main class for the PayFlex module.
 * Prestashop will only load the "Payflex" class since that is the name of the module.
 * We do that later instead (at the bottom of this file) so that we can have different classes for different versions of Prestashop.
 */
class PayFlexMain extends PaymentModule
{
    protected $config_form = false;
    public $bootstrap;
    public $confirmUninstall;

    public $limited_countries  = ['ZA'];
    public $limited_currencies = ['ZAR'];

    public function __construct()
    {
        $this->name = 'PayFlex';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'PayFlex';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('PayFlex');
        $this->description = $this->l('PayFlex - Flex the way you pay.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the PayFlex module?');

        // $this->limited_countries = array('UK');
        // $this->limited_currencies = array('NZ');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        // if (in_array($iso_code, $this->limited_countries) == false)
        // {
        //     $this->_errors[] = $this->l('This module is not available in your country');
        //     return false;
        // }

        Configuration::updateValue('PAYFLEX_LIVE_MODE', false);

        include dirname(__FILE__) . '/sql/install.php';

        return parent::install() &&
        $this->registerHook('header') &&
        $this->registerHook('backOfficeHeader') &&
        $this->registerHook('payment') &&
        $this->registerHook('paymentOptions') &&
        $this->registerHook('paymentReturn') &&
        $this->registerHook('actionOrderSlipAdd') &&
        $this->registerHook('displayOrderConfirmation') &&
        $this->registerHook('displayOrderDetail') &&
        $this->registerHook('displayPayment') &&
        $this->registerHook('displayProductButtons') &&
        $this->registerHook('displayProductAdditionalInfo');
    }
    public function processOrders($id_shop = 0){
        
    }
    public function uninstall()
    {
        Configuration::deleteByName('PAYFLEX_LIVE_MODE');
        Configuration::deleteByName('PAYFLEX_PRODUCTION');
        Configuration::deleteByName('PAYFLEX_WIDGET_ENABLE');
        Configuration::deleteByName('PAYFLEX_CLIENTID');
        Configuration::deleteByName('PAYFLEX_SECRET');
        Configuration::deleteByName('PAYFLEX_MERCHANT_NAME');
        include dirname(__FILE__) . '/sql/uninstall.php';
        return parent::uninstall();
    }
    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool) Tools::isSubmit('submitPayFlexModule')) == true) {
            $this->postProcess();
        }
        $store_url = $this->context->link->getBaseLink();
        
        $this->context->smarty->assign(
            array(
                'module_dir'=> $this->_path,
                'payflex_cron' => $store_url . 'modules/PayFlex/PayFlex-cron.php?token=' . Tools::substr(Tools::encrypt('payflex/cron'), 0, 10) . '&id_shop=' . $this->context->shop->id,
        )); 
        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
        return $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPayFlexModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
        . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable'),
                        'name' => 'PAYFLEX_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use PayFlex as a payment option on your site'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Production mode'),
                        'name' => 'PAYFLEX_PRODUCTION',
                        'is_bool' => true,
                        'desc' => $this->l('Use PayFlex Production or Sandbox services'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Production'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Sandbox'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Widget Enable'),
                        'name' => 'PAYFLEX_WIDGET_ENABLE',
                        'is_bool' => true,
                        'desc' => $this->l('Use Widget on product page'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    // array(
                    //     'col' => 3,
                    //     'type' => 'text',
                    //     'prefix' => '<i class="icon icon-envelope"></i>',
                    //     'desc' => $this->l('Enter a valid email address'),
                    //     'name' => 'PAYFLEX_ACCOUNT_EMAIL',
                    //     'label' => $this->l('Email'),
                    // ),
                    array(
                        'type' => 'text',
                        'name' => 'PAYFLEX_CLIENTID',
                        'label' => $this->l('ClientID'),
                        'desc' => $this->l('The ClientID as issued from PayFlex'),
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'PAYFLEX_SECRET',
                        'label' => $this->l('Secret'),
                        'desc' => $this->l('The Secret key as issued from PayFlex'),
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'PAYFLEX_MERCHANT_NAME',
                        'label' => $this->l('Merchant Name'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }
    
    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'PAYFLEX_LIVE_MODE'     => Configuration::get('PAYFLEX_LIVE_MODE', false),
            'PAYFLEX_PRODUCTION'    => Configuration::get('PAYFLEX_PRODUCTION', false),
            'PAYFLEX_CLIENTID'      => Configuration::get('PAYFLEX_CLIENTID', null),
            'PAYFLEX_SECRET'        => Configuration::get('PAYFLEX_SECRET', null),
            'PAYFLEX_WIDGET_ENABLE' => Configuration::get('PAYFLEX_WIDGET_ENABLE', false),
            'PAYFLEX_MERCHANT_NAME' => Configuration::get('PAYFLEX_MERCHANT_NAME', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }
    
    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    public function hookActionOrderSlipAdd()
    {
        /* Place your code here. */
        // return 'PayFlex:hookActionOrderSlipAdd';
    }

    public function hookDisplayOrderDetail()
    {
        /* Place your code here. */
        // return 'PayFlex:hookDisplayOrderDetail';
    }

    /* Called when needing to build a list of the available payment solutions,
     during the order process. Ideal location to enable the choice of a payment module
     that you have developed. */
    // public function hookDisplayPayment()
    // {  
    //     /* Place your code here. */
    //     // return 'PayFlex:hookDisplayPayment';
    // }

    public function hookDisplayProductButtons()
    {
        /* Place your code here. */
        // return 'PayFlex:hookDisplayProductButtons';
    }

}


/**
 * This is the first class that will be loaded by Prestashop.
 * There is a slight difference between the Prestashop 1.6 and 1.7 method names, so the differences are added here.
 */

// Payflex 1.6 check
if(version_compare(_PS_VERSION_, '1.7', '<'))
{
    class Payflex extends PayFlexMain
    {
        public function __construct()
        {
            parent::__construct();
        }

        /**
         * Prestashop 1.6 uses this hook to display the payment options
         * during the checkout process.
         */
        public function hookPayment($params)
        {   
        
            if(!Configuration::get('PAYFLEX_LIVE_MODE'))
                return false;


            // Only show the payment option if the currency is supported.
            $currency_id = $params['cart']->id_currency;
            $currency    = new Currency((int)$currency_id);

            if (in_array($currency->iso_code, $this->limited_currencies) == false)
                return false;

            $this->smarty->assign('module_dir', $this->_path);

            return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
        
        }

        /** 
         * This hook is used to display the order confirmation page.
         * Prestashop 1.6 uses this hook to display the payment confirmation page. 1.7 uses the hookDisplayOrderConfirmation.
         */
        public function hookPaymentReturn($params)
        {
            if ($this->active == false)
                return;

            $order = $params['objOrder'];

            if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR'))
                $this->smarty->assign('status', 'ok');

            $this->smarty->assign(array(
                'id_order'  => $order->id,
                'reference' => $order->reference,
                'params'    => $params,
                'total'     => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
            ));

            return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');

            // return 'PayFlex:hookPaymentReturn';
        }
    }
}

if(version_compare(_PS_VERSION_, '1.7', '>='))
{
    class Payflex extends PayFlexMain
    {
        public function __construct()
        {
            parent::__construct();
        }

        /**
         * Prestashop 1.7 uses this hook to display the payment options
         * during the checkout process.
         */
        public function hookPaymentOptions($params)
        {
            $live = Configuration::get('PAYFLEX_LIVE_MODE', true);
            if ($live != false) {

                /** @disregard P1009
                 * This is the new way to create a payment option in PrestaShop 1.7.
                 * It allows you to set the module name, logo, action URL, and additional information.
                 * In Prestashop 1.6, this will throw an error, however 1.6 calls the hookPayment() instead of hookPaymentOptions()
                 * so it will not be used.
                 */
                $newOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
                $newOption->setModuleName($this->name)
                    // ->setCallToActionText('Pay with PayFlex')
                    ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/checkout.png'))
                    ->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true));
                    // ->setAdditionalInformation($this->fetch('module:PayFlex/views/templates/front/payment_infos.tpl'));

                return [$newOption];
            }
        }

        /**
         * This hook is used to display the order confirmation page.
         * Prestashop 1.7 uses this hook to display the payment confirmation page.
         * We don't really do anything here at the moment, so it's just commented out, but it's here for reference.
         */
        // public function hookDisplayOrderConfirmation()
        // {
            /* Place your code here. */
            // return 'PayFlex:hookDisplayOrderConfirmation';
        // }

        /**
         * The widget is only supported in PrestaShop 1.7 and above.
         * This displays the PayFlex widget on the product page.
         * 
         * @param array $params
         *
         * @return string
         */
        public function hookDisplayProductAdditionalInfo($params)
        {
            if (Configuration::get('PAYFLEX_WIDGET_ENABLE')) {
                $prod          = Configuration::get('PAYFLEX_PRODUCTION', false);
                $clientId      = Configuration::get('PAYFLEX_CLIENTID', null);
                $secret        = Configuration::get('PAYFLEX_SECRET', null);
                $env           = $prod ? 'production' : 'develop';
                $ppService     = new PayFlexService($env, $clientId, $secret);
                $configuration = $ppService->getMerchantConfiguration();
                $minimumAmount = $configuration->minimumAmount;
                $maximumAmount = $configuration->maximumAmount;
                $price         = $params['product']['price_amount'];
                return '<script type="text/javascript" src="https://widgets.payflex.co.za/' . Configuration::get('PAYFLEX_MERCHANT_NAME') . '/payflex-widget-2.0.1.js?type=calculator&min='.$minimumAmount.'&max='.$maximumAmount.'&amount=' . $price . '"></script>';
            }
        }
    }
}