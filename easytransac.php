<?php

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

/*
 * EasyTransac's official Prestashop payment method.
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_'))
    exit;

class EasyTransac extends PaymentModule
{
    /**
     * Development variable.
     */
    private $debugLogEnabled = false;

    /**
     * Module init.
     */
    public function __construct()
    {
        $this->name = 'easytransac';
        $this->tab = 'payments_gateways';
        $this->version = '3.2.1';
        $this->author = 'EasyTransac';
        $this->is_eu_compatible = 1;
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = $this->l('Credit card payment via EasyTransac');
        $this->description = $this->l('Website payment service');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('EASYTRANSAC_API_KEY'))
            $this->warning = $this->l('No API key provided');
    }

    /**
     * EasyTransac log init with conditional configuration.
     * @return void
     */
    public function loginit()
    {
        EasyTransac\Core\Logger::getInstance()->setActive(Configuration::get('EASYTRANSAC_DEBUG'));
        EasyTransac\Core\Logger::getInstance()->setFilePath(_PS_ROOT_DIR_ . '/modules/easytransac/logs/');
    }

    /**
     * Module install.
     * @return boolean
     */
    public function install()
    {
        if (!parent::install() || 
            !$this->registerHook('paymentOptions') || 
            !$this->registerHook('paymentReturn') || 
            !$this->registerHook('actionFrontControllerSetMedia') ||
            !$this->registerHook('actionOrderSlipAdd')
        ){
            return false;
        }
        include_once(_PS_MODULE_DIR_ . $this->name . '/easytransac_install.php');
        // unlink(_PS_CACHE_DIR_ . 'class_index.php');
        $easytransac_install = new EasyTransacInstall();
        $easytransac_install->updateConfiguration();
        $easytransac_install->createTables();
        $this->create_easytransac_order_state();
        return true;
    }

    /**
     * Module uninstall.
     * @return boolean
     */
    public function uninstall()
    {
        if (!parent::uninstall())
            return false;
        include_once(_PS_MODULE_DIR_ . $this->name . '/easytransac_install.php');
        $easytransac_install = new EasyTransacInstall();
        $easytransac_install->deleteConfiguration();
        $easytransac_install->deleteTables();
        return true;
    }

    /**
     * Settings page.
     * @return string
     */
    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $api_key = strval(Tools::getValue('EASYTRANSAC_API_KEY'));
            if (!empty($api_key)) {
                Configuration::updateValue('EASYTRANSAC_API_KEY', $api_key);
            }

            $enable_3dsecure = strval(Tools::getValue('EASYTRANSAC_3DSECURE'));
            if (empty($enable_3dsecure)) {
                Configuration::updateValue('EASYTRANSAC_3DSECURE', 0);
            } else {
                Configuration::updateValue('EASYTRANSAC_3DSECURE', 1);
            }

            $enable_debug = strval(Tools::getValue('EASYTRANSAC_DEBUG'));
            if (empty($enable_debug)) {
                Configuration::updateValue('EASYTRANSAC_DEBUG', 0);
                $this->loginit();
                EasyTransac\Core\Logger::getInstance()->delete();
            } else {
                Configuration::updateValue('EASYTRANSAC_DEBUG', 1);
            }

            $enable_oneclick = strval(Tools::getValue('EASYTRANSAC_ONECLICK'));
            if (empty($enable_oneclick)) {
                Configuration::updateValue('EASYTRANSAC_ONECLICK', 0);
            } else {
                Configuration::updateValue('EASYTRANSAC_ONECLICK', 1);
            }

            $enable_icon= strval(Tools::getValue('EASYTRANSAC_ICONDISPLAY'));
            if (empty($enable_icon)) {
                Configuration::updateValue('EASYTRANSAC_ICONDISPLAY', 0);
            } else {
                Configuration::updateValue('EASYTRANSAC_ICONDISPLAY', 1);
            }

            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }

        return $output . $this->displayForm();
    }

    /**
     * Get the latest version number of Prestashop 1.7 EasyTransac module.
     */
    public static function getLatestVersion(){
        $uri = 'https://easytransac.com/files/prestashop1.7_module_version.txt';
        $streamContext = stream_context_create(
            array('http'=>
                array(
                    'timeout' => 4,  //120 seconds
                )
            )
        );
        
        //Pass this stream context to file_get_contents via the third parameter.
        try {
            return file_get_contents($uri, false, $streamContext);
        } catch (\Throwable $th) {
            return false;
        }
    }

    /**
     * Settings form.
     * @return string
     */
    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $requirements_message = '';

        // Requirements.
        $openssl_version_supported = OPENSSL_VERSION_NUMBER >= 0x10001000;
        $curl_activated = function_exists('curl_version');

        if ($openssl_version_supported) {
            $info =  sprintf('%s "%s" >= 1.0.1', $this->l('[OK] OpenSSL version'), OPENSSL_VERSION_TEXT);
            $requirements_message = sprintf('<div class="alert-success" style="padding:5px;">%s</div>',
            $info);
        } else {
            $info =  sprintf('%s "%s" < 1.0.1', $this->l('[ERROR] OpenSSL version not supported'), OPENSSL_VERSION_TEXT);
            $requirements_message = '<div class="alert-danger" style="padding:5px;">' . $info . '" < 1.0.1</div>';
        }

        if ($curl_activated) {
            $requirements_message .= sprintf('<div class="alert-success" style="padding:5px;">%s</div>',
                                             $this->l('[OK] cURL is installed'));
        } else {
            $requirements_message .= sprintf('<div class="alert-danger" style="padding:5px;">%s</div>',
                                             $this->l('[ERROR] PHP cURL extension missing'));
        }

        $latestVersion = self::getLatestVersion();
        if (!empty($latestVersion)) {
            $isActualVersion = $latestVersion === $this->version;
            if($isActualVersion){
                $requirements_message .= sprintf('<div class="alert-success" style="padding:5px;">%s</div>',
                                                 $this->l('[OK] Latest module version installed'));
            } else {
                $requirements_message .= sprintf('<div class="alert-danger" style="padding:5px;">%s : %s</div>',
                                                 $this->l('[ERROR] New module is available on www.easytransac.com'), $latestVersion);
            }
        }

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'radio',
                    'label' => $this->l('3DSecure transactions only'),
                    'desc' => $this->l('3DSecure is a secure payment protocol. Its aim is to reduce fraud for merchants and secure customer payments. The customer will be redirected to his bank\'s site that will ask for additional information.'),
                    'name' => 'EASYTRANSAC_3DSECURE',
                    'size' => 20,
                    'is_bool' => true,
                    'values' => array(// $values contains the data itself.
                        array(
                            'id' => 'active_on', // The content of the 'id' attribute of the <input> tag, and of the 'for' attribute for the <label> tag.
                            'value' => 1, // The content of the 'value' attribute of the <input> tag.
                            'label' => $this->l('Enabled')      // The <label> for this radio button.
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    ),
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('One Click Payment'),
                    'name' => 'EASYTRANSAC_ONECLICK',
                    'size' => 20,
                    'is_bool' => true,
                    'values' => array(// $values contains the data itself.
                        array(
                            'id' => 'active2_on', // The content of the 'id' attribute of the <input> tag, and of the 'for' attribute for the <label> tag.
                            'value' => 1, // The content of the 'value' attribute of the <input> tag.
                            'label' => $this->l('Enabled')   // The <label> for this radio button.
                        ),
                        array(
                            'id' => 'active2_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    ),
                ),
                array(
                    'type' => 'radio',
                    'label' => 'Debug Mode',
                    'name' => 'EASYTRANSAC_DEBUG',
                    'size' => 20,
                    'is_bool' => true,
                    'values' => array(// $values contains the data itself.
                        array(
                            'id' => 'active2_on', // The content of the 'id' attribute of the <input> tag, and of the 'for' attribute for the <label> tag.
                            'value' => 1, // The content of the 'value' attribute of the <input> tag.
                            'label' => $this->l('Enabled')   // The <label> for this radio button.
                        ),
                        array(
                            'id' => 'active2_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    ),
                ),
                array(
                    'type' => 'radio',
                    'label' => 'Icon display',
                    'name' => 'EASYTRANSAC_ICONDISPLAY',
                    'size' => 20,
                    'is_bool' => true,
                    'values' => array(// $values contains the data itself.
                        array(
                            'id' => 'active2_on', // The content of the 'id' attribute of the <input> tag, and of the 'for' attribute for the <label> tag.
                            'value' => 1, // The content of the 'value' attribute of the <input> tag.
                            'label' => $this->l('Enabled')   // The <label> for this radio button.
                        ),
                        array(
                            'id' => 'active2_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    ),
                ),
                array(
                    'type' => 'free',
                    'label' => $this->l('Configuration'),
                    'desc' => $this->l('Create an application configuration and copy paste your API key in the next input.'),
                    'name' => 'EASYTRANSAC_HELP',
                    'size' => 20,
                ),
                array(
                    'type' => 'free',
                    'label' => $this->l('Requirements'),
                    'desc' => $requirements_message,
                    'name' => 'EASYTRANSAC_REQUIREMENTS_HELP',
                    'size' => 20,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('EasyTransac Api Key'),
                    'name' => 'EASYTRANSAC_API_KEY',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'free',
                    'label' => $this->l('Notification URL'),
                    'desc' => $this->l('Notification URL to copy paste in your EasyTransac appplication settings'),
                    'name' => 'EASYTRANSAC_NOTIFICATION_URL',
                    'size' => 20,
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true; // false -> remove toolbar
        $helper->toolbar_scroll = true;   // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                        '&token=' . Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['EASYTRANSAC_ONECLICK'] = Configuration::get('EASYTRANSAC_ONECLICK');
        $helper->fields_value['EASYTRANSAC_DEBUG'] = Configuration::get('EASYTRANSAC_DEBUG');
        $helper->fields_value['EASYTRANSAC_ICONDISPLAY'] = Configuration::get('EASYTRANSAC_ICONDISPLAY');
        $helper->fields_value['EASYTRANSAC_API_KEY'] = Configuration::get('EASYTRANSAC_API_KEY');
        $helper->fields_value['EASYTRANSAC_3DSECURE'] = Configuration::get('EASYTRANSAC_3DSECURE');
        $helper->fields_value['EASYTRANSAC_NOTIFICATION_URL'] = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'module/easytransac/notification';
        $helper->fields_value['EASYTRANSAC_HELP'] = $this->l('Visit') . ' <a target="_blank" href="https://www.easytransac.com">www.easytransac.com</a> ' . $this->l('in order to create an account and configure your application.');
        $helper->fields_value['EASYTRANSAC_REQUIREMENTS_HELP'] = Configuration::get('EASYTRANSAC_REQUIREMENTS_HELP');

        return $helper->generateForm($fields_form);
    }

    /**
     * Payment method choice.
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        $this->loginit();
        $newOption = new PaymentOption();
        // $paymentForm = $this->fetch('module:easytransac/views/templates/hook/checkout_payment.tpl');
        $newOption->setCallToActionText($this->l('Pay by EasyTransac'))
                  ->setAction($this->context->link->getModuleLink($this->name, 'payment'));

        if(Configuration::get('EASYTRANSAC_ICONDISPLAY')){
            $newOption->setLogo(_MODULE_DIR_ . 'easytransac/views/img/icon.png');
        }
        
        EasyTransac\Core\Logger::getInstance()->write($this->context->customer);
        if (Configuration::get('EASYTRANSAC_ONECLICK') && $this->context->customer->getClient_id() != null)
        {
            $oneClickPaymentForm = $this->context->smarty->fetch('module:easytransac/views/templates/hook/oneclick_payment.tpl');
            $newOption->setAdditionalInformation($oneClickPaymentForm);
        }
        
        return [$newOption];
    }

    /**
     * Helper function to fetch a template.
     */
    public function fetchTemplate($name)
    {
        if (version_compare(_PS_VERSION_, '1.4', '<'))
            $this->context->smarty->currentTemplate = $name;
        elseif (version_compare(_PS_VERSION_, '1.5', '<')) {
            $views = 'views/templates/';
            if (@filemtime(dirname(__FILE__) . '/' . $name))
                return $this->display(__FILE__, $name);
            elseif (@filemtime(dirname(__FILE__) . '/' . $views . 'hook/' . $name))
                return $this->display(__FILE__, $views . 'hook/' . $name);
            elseif (@filemtime(dirname(__FILE__) . '/' . $views . 'front/' . $name))
                return $this->display(__FILE__, $views . 'front/' . $name);
            elseif (@filemtime(dirname(__FILE__) . '/' . $views . 'admin/' . $name))
                return $this->display(__FILE__, $views . 'admin/' . $name);
        }
        return $this->display(__FILE__, $name);
    }

    /**
     * Return from EasyTransac and validation.
     */
    public function hookPaymentReturn()
    {
        if (!$this->active)
            return null;

        $this->create_easytransac_order_state();
        $et_pending = (int)Configuration::get('EASYTRANSAC_ID_ORDER_STATE');

        $existing_order = !empty($_GET['id_order']) ? new Order($_GET['id_order']) : null;

        if (empty($existing_order->id) || empty($existing_order->current_state))
            $existing_order->current_state = $et_pending;

        // 2: payment accepted, 6: canceled, 7: refunded, 8: payment error, 12: remote payment accepted
        $this->context->smarty->assign(array(
            'isPending' => (int)$existing_order->current_state === $et_pending,
            'isCanceled' => (int)$existing_order->current_state === 6 || (int)$existing_order->current_state === 8,
            'isAccepted' => (int)$existing_order->current_state === 2,
        ));
        $this->debugLog('TPL', 'Calling confirmation tpl');
        return $this->fetch('module:easytransac/views/templates/hook/confirmation.tpl');
    }

    /**
     * Make an order out of a cart.
     */
    public function validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method = 'Unknown', $message = null, $transaction = array(), $currency_special = null, $dont_touch_amount = false, $secure_key = false, Shop $shop = null)
    {
        if ($this->active) {
            if (version_compare(_PS_VERSION_, '1.5', '<'))
                parent::validateOrder((int)$id_cart, (int)$id_order_state, (float)$amount_paid, $payment_method, $message, $transaction, $currency_special, $dont_touch_amount, $secure_key);
            else
                parent::validateOrder((int)$id_cart, (int)$id_order_state, (float)$amount_paid, $payment_method, $message, $transaction, $currency_special, $dont_touch_amount, $secure_key, $shop);
        }
    }

    /**
     * Plugin version info.
     * @return string
     */
    public function get_server_info_string()
    {
        $curl_info_string = function_exists('curl_version') ? 'enabled' : 'not found';
        $openssl_info_string = OPENSSL_VERSION_NUMBER >= 0x10001000 ? 'TLSv1.2' : 'OpenSSL version deprecated';
        $https_info_string = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'S' : '';
        return sprintf('Prestashop %s [cURL %s, OpenSSL %s, HTTP%s]', $this->version, $curl_info_string, $openssl_info_string, $https_info_string);
    }

    /**
     * Creates EasyTransac order state if not already registered.
     * Prupose : plugin update from older versions.
     */
    public function create_easytransac_order_state()
    {
        if (!(Configuration::get('EASYTRANSAC_ID_ORDER_STATE') > 0)) {
            // for sites upgrading from older version
            $OrderState = new OrderState();
            $OrderState->id = 'EASYTRANSAC_STATE_ID';
            $OrderState->name = array_fill(0, 10, "EasyTransac payment pending");
            $OrderState->send_email = 0;
            $OrderState->invoice = 0;
            $OrderState->color = "#ff9900";
            $OrderState->unremovable = false;
            $OrderState->logable = 0;
            $OrderState->add();
            Configuration::updateValue('EASYTRANSAC_ID_ORDER_STATE', $OrderState->id);
        }
    }

    /**
    * Order transaction refund.
    */
    public function hookActionOrderSlipAdd($params){

        $this->loginit();
		EasyTransac\Core\Logger::getInstance()->write('Start Refund');
        $api_key = Configuration::get('EASYTRANSAC_API_KEY');
        EasyTransac\Core\Services::getInstance()->provideAPIKey($api_key);

        EasyTransac\Core\Logger::getInstance()->write(__FUNCTION__.' '. is_object($params['order']));
        EasyTransac\Core\Logger::getInstance()->write('is partialRefund'.' '.  Tools::isSubmit('partialRefund'));
        EasyTransac\Core\Logger::getInstance()->write('is partialRefundProduct '. 
                        Tools::isSubmit('partialRefundProduct'));
        EasyTransac\Core\Logger::getInstance()->write('is partialRefund value '. 
                        json_encode(Tools::getValue('partialRefundProduct')));

        $products = $params['order']->getProducts(true);
        
        foreach ($products as $key => $item) {
            EasyTransac\Core\Logger::getInstance()->write('product amount '.  $item->amount);
            EasyTransac\Core\Logger::getInstance()->write('product map '.  json_encode($item));
        }

        $items = $params['order']->getOrderSlipsCollection()->getResults();
        $total_refund_amount = 0;

        foreach ($items as $key => $item) {
            EasyTransac\Core\Logger::getInstance()->write('refund amount for item '.  $item->amount);
            $total_refund_amount += $item->amount;
        }

        if($total_refund_amount > 0){
            $logMsg = json_encode(print_r($params['order'], true));
            EasyTransac\Core\Logger::getInstance()->write($logMsg);
            EasyTransac\Core\Logger::getInstance()->write('ID cart : '.$params['order']->id_cart);
            if( ! ($transactionId = $this->getTransactionId($params['order']->id_cart))){
                $errorMsg = 'EasyTransac exception: not Tid for cart id: '.$params['order']->id_cart;
                error_log($errorMsg);
                EasyTransac\Core\Logger::getInstance()->write($errorMsg);
                throw new Exception ($errorMsg);
                return;
            }
        }

        EasyTransac\Core\Logger::getInstance()->write('Transaction id found', $transactionId);

        // $notif_url 	= Tools::getShopDomainSsl(true, true).__PS_BASE_URI__
        //             .'module/easytransac/notification';

        # Fixing PHP's floating point issue.
        $total_to_refund = 100 * $total_refund_amount;
        $total_to_refund = intval(strval($total_to_refund));

        try {
            $refund = (new \EasyTransac\Entities\Refund)
                        ->setTid($transactionId)
                        ->setAmount($total_to_refund);
            $request = (new EasyTransac\Requests\PaymentRefund);
            $response = $request->execute($refund);

            if (empty($response)) {
                EasyTransac\Core\Logger::getInstance()->write('EasyTransac Refund', 'empty response.');
            }
            else if (!$response->isSuccess()) {
                throw new Exception ($response->getErrorMessage());
            }
            else if ($response->isSuccess()) {
                EasyTransac\Core\Logger::getInstance()->write('EasyTransac Refund', 'success');
            }
        }
        catch (Exception $exc) {
            error_log('EasyTransac Refund Exception: ' . $exc->getMessage());
        }
    }

    /**
     * Return the transaction id for an order.
     */
    function getTransactionId($order_id)
	{
        if($this->debugLogEnabled){
            $a = Db::getInstance()->ExecuteS('SELECT * FROM `' . _DB_PREFIX_ 
                                             . 'easytransac_transaction`');
            EasyTransac\Core\Logger::getInstance()->write('easytransac debug list all: '. json_encode($a));
        }

		$sql = 'SELECT external_id FROM `' . _DB_PREFIX_ . 'easytransac_transaction` '
				. ' WHERE id_order = \'' . intval($order_id) . '\'';
		return Db::getInstance()->getValue($sql);
	}

    /**
     * Save the transaction id.
     */
	function setTransactionId($order_id, $transaction_id)
	{
		Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'easytransac_transaction` '
				. ' VALUES(' . intval($order_id) . ',\'' . $transaction_id . '\')');
	}

	public function debugLog($title, $data=""){

        if(!is_string($data))
            $data = json_encode($data);

        if(!is_string($title))
            $title = json_encode($title);

        $message = sprintf('%s : %s', $title, $data);
		EasyTransac\Core\Logger::getInstance()->write($message);

    }
}
