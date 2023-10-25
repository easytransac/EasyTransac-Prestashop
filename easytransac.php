<?php
/**
 * @author Easytransac SAS
 * @copyright Copyright (c) 2022 Easytransac
 * @license Apache 2.0
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use EasyTransac\Entities\Refund;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class EasyTransac extends PaymentModule
{
    /**
     * Development variable.
     */
    private $debugLogEnabled = false;

    public $is_eu_compatible;

    /**
     * Module init.
     *
     * @return void
     */
    public function __construct()
    {
        $this->name = 'easytransac';
        $this->tab = 'payments_gateways';
        $this->version = '3.3.3';
        $this->author = 'EasyTransac';
        $this->is_eu_compatible = 1;
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = $this->l('Credit card payment via EasyTransac');
        $this->description = $this->l('Website payment service');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('EASYTRANSAC_API_KEY')) {
            $this->warning = $this->l('No API key provided');
        }

        $this->module_key = '3b00196a26285f2cf9414263b7f70b50';

        // Disable onclick payment
        // WIP : feature will come back soon
        Configuration::updateValue('EASYTRANSAC_ONECLICK', 0);
    }

    /**
     * EasyTransac log init with conditional configuration.
     *
     * @return void
     */
    public function loginit()
    {
        EasyTransac\Core\Logger::getInstance()
            ->setActive(Configuration::get('EASYTRANSAC_DEBUG'));

        EasyTransac\Core\Logger::getInstance()
            ->setFilePath(_PS_ROOT_DIR_ . '/modules/easytransac/logs/');
    }

    /**
     * Module install.
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('paymentOptions') ||
            !$this->registerHook('actionOrderSlipAdd') ||
            !$this->registerHook('displayAdminOrderTabContent')
        ) {
            return false;
        }
        include_once _PS_MODULE_DIR_ . $this->name . '/easytransac_install.php';
        // unlink(_PS_CACHE_DIR_ . 'class_index.php');
        $easytransac_install = new EasyTransacInstall();
        $easytransac_install->updateConfiguration();
        $easytransac_install->createTables();
        $this->create_easytransac_order_state();

        return true;
    }

    /**
     * Module uninstall.
     *
     * @return bool
     */
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        include_once _PS_MODULE_DIR_ . $this->name . '/easytransac_install.php';
        $easytransac_install = new EasyTransacInstall();
        $easytransac_install->deleteConfiguration();
        $easytransac_install->deleteTables();

        return true;
    }

    /**
     * Settings page.
     *
     * @return string
     */
    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $api_key = (string) Tools::getValue('EASYTRANSAC_API_KEY');
            if (!empty($api_key)) {
                Configuration::updateValue('EASYTRANSAC_API_KEY', $api_key);
            }

            $enable_debug = (string) Tools::getValue('EASYTRANSAC_DEBUG');
            if (empty($enable_debug)) {
                Configuration::updateValue('EASYTRANSAC_DEBUG', 0);
                $this->loginit();
                EasyTransac\Core\Logger::getInstance()->delete();
            } else {
                Configuration::updateValue('EASYTRANSAC_DEBUG', 1);
            }

            // Disable onclick payment
            // WIP : feature will come back soon
            //            $enable_oneclick = (string)(Tools::getValue('EASYTRANSAC_ONECLICK'));
            //            if (empty($enable_oneclick)) {
            //                Configuration::updateValue('EASYTRANSAC_ONECLICK', 0);
            //            } else {
            //                Configuration::updateValue('EASYTRANSAC_ONECLICK', 1);
            //            }

            // Payments in instalments
            Configuration::updateValue('EASYTRANSAC_MULTIPAY', 0);

            foreach ([2, 3, 4] as $a) {
                $key = sprintf('EASYTRANSAC_MULTIPAY%dX', $a);
                $isEnabled = (string) Tools::getValue($key);

                if (empty($isEnabled)) {
                    Configuration::updateValue($key, 0);
                } else {
                    Configuration::updateValue($key, 1);

                    Configuration::updateValue('EASYTRANSAC_MULTIPAY', 1);
                }
            }
            // END Payments in instalments

            $enable_icon = (string) Tools::getValue('EASYTRANSAC_ICONDISPLAY');
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
     * Helper function to get whether payment in instalments for $count
     * times is enabled.
     *
     * @return bool
     */
    private function instalment_payment_count_enabled($count)
    {
        if (!in_array($count, [2, 3, 4])) {
            return false;
        }
        $key = 'EASYTRANSAC_MULTIPAY' . $count . 'X';

        return (bool) Configuration::get($key);
    }

    /**
     * Get the latest version number of Prestashop 1.7 EasyTransac module.
     *
     * @return string
     */
    public static function getLatestVersion()
    {
        $uri = 'https://easytransac.com/files/prestashop1.7_module_version.txt';
        $streamContext = stream_context_create(
            [
                'http' => [
                        'timeout' => 4,  // 120 seconds
                    ],
            ]
        );

        // Pass this stream context to file_get_contents via the third parameter.
        try {
            return Tools::file_get_contents($uri, false, $streamContext);
        } catch (Throwable $th) {
            return false;
        }
    }

    /**
     * Helper function for admin success message.
     * For prestashop validator.
     */
    private function successMessage($value)
    {
        $this->context->smarty->assign(['message' => $value]);

        return $this->context->smarty->fetch(
            'module:easytransac/views/templates/admin/success.tpl'
        );
    }

    /**
     * Helper function for admin alert message.
     * For prestashop validator.
     */
    private function alertMessage($value)
    {
        $this->context->smarty->assign(['message' => $value]);

        return $this->context->smarty->fetch(
            'module:easytransac/views/templates/admin/alert.tpl'
        );
    }

    /**
     * Helper function for admin alert message.
     * For prestashop validator.
     */
    private function htmlLink($title, $link)
    {
        $this->context->smarty->assign([
            'title' => $title,
            'link' => $link,
        ]);

        return $this->context->smarty->fetch(
            'module:easytransac/views/templates/admin/link.tpl'
        );
    }

    /**
     * Settings form.
     *
     * @return string
     */
    public function displayForm()
    {
        // Get default language
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        // Requirement messages to display.
        $require_buffer = [];

        // Requirements message;
        $openssl_version_supported = OPENSSL_VERSION_NUMBER >= 0x10001000;
        $curl_activated = function_exists('curl_version');

        if ($openssl_version_supported) {
            $info = sprintf(
                '%s "%s" >= 1.0.1',
                $this->l('[OK] OpenSSL version'),
                OPENSSL_VERSION_TEXT
            );

            $require_buffer[] = $this->successMessage($info);
        } else {
            $info = sprintf(
                '%s "%s" < 1.0.1',
                $this->l('[ERROR] OpenSSL version not supported'),
                OPENSSL_VERSION_TEXT
            );

            $require_buffer[] = $this->alertMessage($info);
        }

        if ($curl_activated) {
            $info = $this->l('[OK] cURL is installed');

            $require_buffer[] = $this->successMessage($info);
        } else {
            $info = $this->l('[ERROR] PHP cURL extension missing');

            $require_buffer[] = $this->alertMessage($info);
        }

        // Message about a newer version of this module.
        $latestVersion = self::getLatestVersion();
        if (!empty($latestVersion)) {
            $isActualVersion = $latestVersion === $this->version;
            if ($isActualVersion) {
                $info = $this->l('[OK] Latest module version installed');

                $require_buffer[] = $this->successMessage($info);
            } else {
                $info = $this->l('[ERROR] New module is available on www.easytransac.com');

                $require_buffer[] = $this->alertMessage($info);
            }
        }

        $requirements_message = implode('', $require_buffer);

        $et_link = $this->htmlLink(
            ' ' . $this->l('your application'),
            'https://www.easytransac.com/' . $this->l('en') . '/login/application/all'
        );

        // Init Fields form array
        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Settings'),
            ],
            'input' => [
                [
                    'type' => 'free',
                    'label' => $this->l('Requirements'),
                    'desc' => $requirements_message,
                    'name' => 'EASYTRANSAC_REQUIREMENTS_HELP',
                    'size' => 20,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Api Key'),
                    'desc' => $this->l(
                        'Your Easytransac application API Key is available in your back office, by editing '
                    )
                        . $et_link . '.',
                    'name' => 'EASYTRANSAC_API_KEY',
                    'size' => 20,
                    'required' => true,
                ],
                [
                    'type' => 'free',
                    'label' => $this->l('Notification URL'),
                    'desc' => $this->l('Enter this notification URL when editing ')
                        . $et_link . '.',
                    'name' => 'EASYTRANSAC_NOTIFICATION_URL',
                    'size' => 20,
                ],
                // Disable onclick payment
                // WIP : feature will come back soon
                //                array(
                //                    'type' => 'radio',
                //                    'label' => $this->l('One Click Payment'),
                //                    'desc' => $this->l('The credit card is stored for future payments.'),
                //                    'name' => 'EASYTRANSAC_ONECLICK',
                //                    'size' => 20,
                //                    'is_bool' => true,
                //                    'values' => array(
                //                        array(
                //                            'id' => 'active2_on',
                //                            'value' => 1,
                //                            'label' => $this->l('Enabled')
                //                        ),
                //                        array(
                //                            'id' => 'active2_off',
                //                            'value' => 0,
                //                            'label' => $this->l('Disabled')
                //                        )
                //                    ),
                //                ),
                [
                    'type' => 'radio',
                    'label' => $this->l('Enable payment in 2 instalments'),
                    'name' => 'EASYTRANSAC_MULTIPAY2X',
                    'size' => 20,
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active2_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'active2_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type' => 'radio',
                    'label' => $this->l('Enable payment in 3 instalments'),
                    'name' => 'EASYTRANSAC_MULTIPAY3X',
                    'size' => 20,
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active2_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'active2_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type' => 'radio',
                    'label' => $this->l('Enable payment in 4 instalments'),
                    'name' => 'EASYTRANSAC_MULTIPAY4X',
                    'size' => 20,
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active2_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'active2_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type' => 'radio',
                    'label' => $this->l('Icon display'),
                    'name' => 'EASYTRANSAC_ICONDISPLAY',
                    'desc' => $this->l('Display Easytransac icon at checkout'),
                    'size' => 20,
                    'is_bool' => true,
                    'values' => [// $values contains the data itself.
                        [
                            'id' => 'active2_on',
                            // The content of the 'id' attribute of the <input> tag, and of the 'for' attribute for the <label> tag.
                            'value' => 1,
                            // The content of the 'value' attribute of the <input> tag.
                            'label' => $this->l('Enabled'),
                            // The <label> for this radio button.
                        ],
                        [
                            'id' => 'active2_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type' => 'radio',
                    'label' => $this->l('Debug mode'),
                    'desc' => $this->l('Save the transaction log for debugging purpose.'),
                    'name' => 'EASYTRANSAC_DEBUG',
                    'size' => 20,
                    'is_bool' => true,
                    'values' => [// $values contains the data itself.
                        [
                            'id' => 'active2_on',
                            // The content of the 'id' attribute of the <input> tag, and of the 'for' attribute for the <label> tag.
                            'value' => 1,
                            // The content of the 'value' attribute of the <input> tag.
                            'label' => $this->l('Enabled'),
                            // The <label> for this radio button.
                        ],
                        [
                            'id' => 'active2_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'button',
            ],
        ];

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
        $helper->toolbar_btn = [
            'save' => [
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex . '&configure=' .
                        $this->name . '&save' . $this->name .
                        '&token=' . Tools::getAdminTokenLite('AdminModules'),
                ],
            'back' => [
                'href' => AdminController::$currentIndex . '&token=' .
                    Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list'),
            ],
        ];

        // Load current value
        $helper->fields_value['EASYTRANSAC_ONECLICK'] = Configuration::get('EASYTRANSAC_ONECLICK');

        // true if one instalment payment count is enabled
        $helper->fields_value['EASYTRANSAC_MULTIPAY'] = Configuration::get('EASYTRANSAC_MULTIPAY');

        $helper->fields_value['EASYTRANSAC_MULTIPAY2X'] = Configuration::get('EASYTRANSAC_MULTIPAY2X');
        $helper->fields_value['EASYTRANSAC_MULTIPAY3X'] = Configuration::get('EASYTRANSAC_MULTIPAY3X');
        $helper->fields_value['EASYTRANSAC_MULTIPAY4X'] = Configuration::get('EASYTRANSAC_MULTIPAY4X');

        $helper->fields_value['EASYTRANSAC_DEBUG'] = Configuration::get('EASYTRANSAC_DEBUG');
        $helper->fields_value['EASYTRANSAC_ICONDISPLAY'] = Configuration::get('EASYTRANSAC_ICONDISPLAY');
        $helper->fields_value['EASYTRANSAC_API_KEY'] = Configuration::get('EASYTRANSAC_API_KEY');

        $helper->fields_value['EASYTRANSAC_NOTIFICATION_URL'] =
            Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'module/easytransac/notification';

        $et_link = $this->htmlLink(
            'www.easytransac.com',
            'https://www.easytransac.com/'
        );

        $helper->fields_value['EASYTRANSAC_HELP'] = sprintf(
            '%s %s %s',
            $this->l('Visit'),
            $et_link,
            $this->l('in order to create an account and configure your application.')
        );

        $helper->fields_value['EASYTRANSAC_REQUIREMENTS_HELP'] =
            Configuration::get('EASYTRANSAC_REQUIREMENTS_HELP');

        return $helper->generateForm($fields_form);
    }

    /**
     * Payment method choice.
     *
     * @return mixed
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        $this->loginit();
        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->l('Pay by credit card'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment'));

        if (Configuration::get('EASYTRANSAC_ICONDISPLAY')) {
            $newOption->setLogo(_MODULE_DIR_ . 'easytransac/views/img/icon.png');
        }

        // EasyTransac\Core\Logger::getInstance()->write($this->context->customer);

        // Adding up templates.
        $buffer = [];

        // Disable onclick payment
        // WIP : feature will come back soon
        //        // Customer should have paid at least one time using Easytransac.
        //        if (Configuration::get('EASYTRANSAC_ONECLICK') &&
        //            $this->isCustomerKnown())
        //        {
        //            $buffer[] = $this->context->smarty->fetch(
        //                                        'module:easytransac/views/templates/hook/oneclick_payment.tpl');
        //        }

        $cart = $this->context->cart;
        $total = 100 * $cart->getOrderTotal(true, Cart::BOTH);

        if (Configuration::get('EASYTRANSAC_MULTIPAY')
            && $total >= 5000) {
            if ($buffer) {
                $buffer[] = '<br/>';
            }
            // Template vars.
            $enabled_vars = [];
            foreach ([2, 3, 4] as $count) {
                $enabled_vars['enableInstallment' . $count] =
                    $this->instalment_payment_count_enabled($count);
            }

            $this->context->smarty->assign($enabled_vars);

            $multi = $this->context->smarty->fetch(
                'module:easytransac/views/templates/hook/multiple_payments.tpl'
            );
            $buffer[] = $multi;
        }

        if ($buffer) {
            $newOption->setAdditionalInformation(implode('', $buffer));
        }

        return [$newOption];
    }

    /**
     * Returns whether the customer has already paid via Easytransac.
     *
     * @return bool
     */
    public function isCustomerKnown()
    {
        $client_id = $this->context->customer->getClient_id();

        return !empty($client_id);
    }

    /**
     * Helper function to fetch a template.
     */
    public function fetchTemplate($name)
    {
        if (version_compare(_PS_VERSION_, '1.4', '<')) {
            $this->context->smarty->currentTemplate = $name;
        } elseif (version_compare(_PS_VERSION_, '1.5', '<')) {
            $views = 'views/templates/';
            if (@filemtime(dirname(__FILE__) . '/' . $name)) {
                return $this->display(__FILE__, $name);
            } elseif (@filemtime(dirname(__FILE__) . '/' . $views . 'hook/' . $name)) {
                return $this->display(__FILE__, $views . 'hook/' . $name);
            } elseif (@filemtime(dirname(__FILE__) . '/' . $views . 'front/' . $name)) {
                return $this->display(__FILE__, $views . 'front/' . $name);
            } elseif (@filemtime(dirname(__FILE__) . '/' . $views . 'admin/' . $name)) {
                return $this->display(__FILE__, $views . 'admin/' . $name);
            }
        }

        return $this->display(__FILE__, $name);
    }

    /**
     * Make an order out of a cart.
     *
     * @return void
     */
    public function validateOrder(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method = 'Unknown',
        $message = null,
        $transaction = [],
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        Shop $shop = null,
        ?string $order_reference = null
    ) {
        if (!$this->active) {
            return;
        }

        // new argument $order_reference added in parent class since prestashop version 8.0
        if (isset($order_reference)) {
            parent::validateOrder(
                (int) $id_cart,
                (int) $id_order_state,
                (float) $amount_paid,
                $payment_method,
                $message,
                $transaction,
                $currency_special,
                $dont_touch_amount,
                $secure_key,
                $shop,
                $order_reference
            );
        } else {
            parent::validateOrder(
                (int) $id_cart,
                (int) $id_order_state,
                (float) $amount_paid,
                $payment_method,
                $message,
                $transaction,
                $currency_special,
                $dont_touch_amount,
                $secure_key,
                $shop
            );
        }
    }

    /**
     * Plugin version info.
     *
     * @return string
     */
    public function get_server_info_string()
    {
        $curl_info_string = function_exists('curl_version') ? 'enabled' : 'not found';
        $openssl_info_string = OPENSSL_VERSION_NUMBER >= 0x10001000 ? 'TLSv1.2' : 'OpenSSL version deprecated';
        $https_info_string = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'S' : '';

        return sprintf(
            'Prestashop %s [cURL %s, OpenSSL %s, HTTP%s]',
            $this->version,
            $curl_info_string,
            $openssl_info_string,
            $https_info_string
        );
    }

    /**
     * Creates EasyTransac order state if not already registered.
     * State for payments in instalments added.
     * Prupose : plugin update from older versions.
     *
     * @return void
     */
    public function create_easytransac_order_state()
    {
        if (!(Configuration::get('EASYTRANSAC_ID_ORDER_STATE') > 0)) {
            // for sites upgrading from older version
            $OrderState = new OrderState();
            $OrderState->id = 'EASYTRANSAC_STATE_ID';
            $OrderState->name = array_fill(0, 10, 'EasyTransac payment pending');
            $OrderState->send_email = 0;
            $OrderState->invoice = 0;
            $OrderState->color = '#ff9900';
            $OrderState->unremovable = false;
            $OrderState->logable = 0;
            $OrderState->add();
            Configuration::updateValue('EASYTRANSAC_ID_ORDER_STATE', $OrderState->id);
        }

        if (!(Configuration::get('EASYTRANSAC_ID_PAYMENT_INSTALLMENT_STATE') > 0)) {
            // for sites upgrading from older version
            $OrderState = new OrderState();
            $OrderState->id = 'EASYTRANSAC_ID_PAYMENT_INSTALLMENT_STATE';
            $OrderState->name = array_fill(0, 10, 'EasyTransac payment in instalments');
            $OrderState->send_email = 0;
            $OrderState->invoice = 0;
            $OrderState->color = '#8067ee';
            $OrderState->unremovable = false;
            $OrderState->logable = 0;
            $OrderState->paid = 0;
            $OrderState->add();
            Configuration::updateValue('EASYTRANSAC_ID_PAYMENT_INSTALLMENT_STATE', $OrderState->id);
        }
    }

    /**
     * Helper function to get Order payment in instalment state.
     *
     * @return int
     */
    public function get_split_payment_state()
    {
        return (int) Configuration::get('EASYTRANSAC_ID_PAYMENT_INSTALLMENT_STATE');
    }

    /**
     * Helper function to get Order payment payment pending state.
     */
    public function get_pending_payment_state()
    {
        return (int) Configuration::get('EASYTRANSAC_ID_ORDER_STATE');
    }

    // Hook for Prestashop version >= 1.7.7 for a block on order page.
    public function hookDisplayAdminOrderTabContent($params)
    {
        $this->loginit();

        // # Display transaction's saved messages.
        $items = $this->getTransactionMessages($params['id_order']);

        $notice = $this->l('No transactions yet.');
        $show_history = false;

        $history = [];
        if (!empty($params)) {
            foreach ($items as $item) {
                $item['amount'] = number_format($item['amount'] / 100, 2, '.', '');
                $history[] = $item;
                $show_history = true;
            }
            if ($history) {
                $notice = '';
            }
        }

        $vars = [
            'notice' => $notice,
            'show_history' => $show_history,
            'history' => $history,
        ];

        $this->context->smarty->assign($vars);

        return $this->context->smarty->fetch(
            'module:easytransac/views/templates/hook/adminordertab.tpl'
        );
    }

    /**
     * Order transaction refund.
     *
     * @return int
     *
     * @throws Exception
     */
    public function hookActionOrderSlipAdd($params)
    {
        $this->loginit();
        EasyTransac\Core\Logger::getInstance()->write('Start Refund');
        $api_key = Configuration::get('EASYTRANSAC_API_KEY');
        EasyTransac\Core\Services::getInstance()->provideAPIKey($api_key);

        EasyTransac\Core\Logger::getInstance()->write(__FUNCTION__ . ' ' . is_object($params['order']));
        EasyTransac\Core\Logger::getInstance()->write('is partialRefund ' . Tools::isSubmit('partialRefund'));
        EasyTransac\Core\Logger::getInstance()->write(
            'is partialRefundProduct ' .
            Tools::isSubmit('partialRefundProduct')
        );
        EasyTransac\Core\Logger::getInstance()->write(
            'is partialRefund value ' .
            json_encode(Tools::getValue('partialRefundProduct'))
        );

        $products = $params['order']->getProducts(true);

        foreach ($products as $key => $item) {
            EasyTransac\Core\Logger::getInstance()
                ->write('product amount ' . $item->amount);

            EasyTransac\Core\Logger::getInstance()
                ->write('product map ' . json_encode($item));
        }

        // $items = $params['order']->getOrderSlipsCollection()->getResults();
        // $total_refund_amount = 0;

        // foreach ($items as $key => $item) {
        //     EasyTransac\Core\Logger::getInstance()
        //         ->write('refund amount for item '.  $item->amount);

        //     $total_refund_amount += $item->amount;
        // }

        $orderSlip = $params['order']->getOrderSlipsCollection()
            ->orderBy('date_upd', 'desc')
            ->getFirst();

        $total_refund_amount = $orderSlip->amount + $orderSlip->shipping_cost_amount;

        EasyTransac\Core\Logger::getInstance()
            ->write(
                'refund amount for order slip: ' . $orderSlip->amount
                . ' - shipping cost amount: ' . $orderSlip->shipping_cost_amount
                . ' - total: ' . $total_refund_amount
            );

        if ($total_refund_amount > 0) {
            $logMsg = json_encode(print_r($params['order'], true));
            EasyTransac\Core\Logger::getInstance()->write($logMsg);
            EasyTransac\Core\Logger::getInstance()->write('ID cart : ' . $params['order']->id_cart);

            if (!($transactionId = $this->getTransactionId($params['order']->id_cart))) {
                $errorMsg = 'EasyTransac exception: not Tid for cart id: ' . $params['order']->id_cart;
                error_log($errorMsg);
                EasyTransac\Core\Logger::getInstance()->write($errorMsg);
                throw new Exception($errorMsg);
            }
        }

        EasyTransac\Core\Logger::getInstance()
            ->write('Transaction id found', $transactionId);

        // Fixing PHP's floating point issue.
        $total_to_refund = 100 * $total_refund_amount;
        $total_to_refund = (int) (string) $total_to_refund;

        try {
            $refund = (new Refund())
                ->setTid($transactionId)
                ->setAmount($total_to_refund);
            $request = (new EasyTransac\Requests\PaymentRefund());
            $response = $request->execute($refund);

            if (empty($response)) {
                EasyTransac\Core\Logger::getInstance()
                    ->write('EasyTransac Refund', 'empty response.');
            } else {
                if (!$response->isSuccess()) {
                    throw new Exception($response->getErrorMessage());
                } else {
                    if ($response->isSuccess()) {
                        EasyTransac\Core\Logger::getInstance()
                            ->write('EasyTransac Refund', 'success');
                    }
                }
            }
        } catch (Exception $exc) {
            error_log('EasyTransac Refund Exception: ' . $exc->getMessage());
        }
    }

    /**
     * Return the transaction id for an order.
     *
     * @return string
     */
    public function getTransactionId($order_id)
    {
        if ($this->debugLogEnabled) {
            $a = Db::getInstance()->ExecuteS(
                'SELECT * FROM `' . _DB_PREFIX_
                . 'easytransac_transaction`'
            );
            EasyTransac\Core\Logger::getInstance()->write('easytransac debug list all: ' . json_encode($a));
        }

        $sql = 'SELECT external_id FROM `' . _DB_PREFIX_ . 'easytransac_transaction` '
            . ' WHERE id_order = \'' . ((int) $order_id) . '\'';

        return Db::getInstance()->getValue($sql);
    }

    /**
     * Save the transaction id.
     *
     * @return void
     */
    public function setTransactionId($order_id, $transaction_id)
    {
        Db::getInstance()->insert('easytransac_transaction', [
            'id_order' => (int) $order_id,
            'external_id' => pSQL($transaction_id),
        ]);
    }

    /**
     * Save the transaction message.
     */
    public function addTransactionMessage($order_id, $transaction_id, $message, $amount = null, $status = '')
    {
        // amount can be null.
        $save_amount = 'NULL';

        if ($amount !== null) {
            $save_amount = (int) $amount;
        }

        // message max size.
        $message = strip_tags($message);
        if (strlen($message) > 255) {
            $message = substr($message, 0, 250);
        }

        // status max size.
        $status = strip_tags($status);
        if (strlen($status) > 20) {
            $status = substr($status, 0, 19);
        }

        $now = date('Y-m-d H:i:s');

        Db::getInstance()->insert('easytransac_message', [
            'id_order' => (int) $order_id,
            'date' => $now,
            'message' => pSQL($message),
            'status' => pSQL($status),
            'external_id' => pSQL($transaction_id),
            'amount' => (int) $save_amount,
        ]);
    }

    /**
     * Helper function to translate a transaction's capture status.
     *
     * @return string
     */
    public function translateStatus($status)
    {
        if (strtolower($status) == 'captured') {
            $this->debugLog('translate', $this->l('Success'));

            return $this->l('Success');
        } elseif (strtolower($status) == 'failed') {
            $this->debugLog('translate', $this->l('Failure'));

            return $this->l('Failure');
        }

        return $status;
    }

    /**
     * Return the saved transaction messages for an order.
     *
     * @return array [[id_order, date, message, external_id, amount]]
     */
    public function getTransactionMessages($order_id)
    {
        /** @var array $result */
        $result = Db::getInstance()->ExecuteS(
            'SELECT * FROM `' . _DB_PREFIX_
            . 'easytransac_message` WHERE id_order = ' . (int) $order_id
        );

        foreach ($result as &$item) {
            $item['status'] = $this->translateStatus($item['status']);
        }

        if ($this->debugLogEnabled) {
            $this->debugLog('easytransac debug saved messages', json_encode($result));
        }

        return $result;
    }

    /**
     * Log a message in debug file.
     *
     * @return void
     */
    public function debugLog($title, $data = '')
    {
        if (!is_string($data)) {
            $data = json_encode($data);
        }

        if (!is_string($title)) {
            $title = json_encode($title);
        }

        $message = sprintf('%s : %s', $title, $data);
        EasyTransac\Core\Logger::getInstance()->write($message);
    }

    /**
     * Helper to add a message to an order.
     *
     * @return void
     */
    public function addOrderMessage($order_id, $message)
    {
        $msg = new Message();
        $msg->message = strip_tags($message, '<br>');
        $msg->id_order = (int) $order_id;
        $msg->private = 1;
        $msg->add();
        $this->debugLog(
            'Message added to order '
            . $order_id,
            $message
        );
    }

    /**
     * Call payment status endpoint.
     *
     * @return false|string returns transaction status string
     */
    public function fetchStatus($order_id)
    {
        $this->debugLog('PaymentStatus status', 'in fetch function');
        try {
            EasyTransac\Core\Services::getInstance()
                ->provideAPIKey(Configuration::get('EASYTRANSAC_API_KEY'));
            $entity = (new EasyTransac\Entities\PaymentStatus())
                ->setOrderId($order_id);

            $request = new EasyTransac\Requests\PaymentStatus();
            $response = $request->execute($entity);

            if (!$response) {
                throw new Exception('empty response');
            }

            if ($response->getErrorMessage()) {
                throw new Exception($response->getErrorMessage());
            }
        } catch (Exception $exc) {
            $this->debugLog('Fetch PaymentStatus error', $exc->getMessage());
            error_log('EasyTransac error: ' . $exc->getMessage());

            return false;
        }

        $status = $response->getContent();
        $this->debugLog('PaymentStatus status', $status->getStatus());

        return $status->getStatus();
    }

    /**
     * Enable Prestashop v1.7.8 new translation system.
     */
    public function isUsingNewTranslationSystem()
    {
        return false;
    }

    /**
     * Convert a iso2 country code to iso3
     *
     * @param string $iso2 prestashop country iso_code
     *
     * @return ?string iso3 country code
     */
    public static function convertCountryToISO3(string $iso2): ?string
    {
        $conversions = [
            'AF' => 'AFG',
            'AX' => 'ALA',
            'AL' => 'ALB',
            'DZ' => 'DZA',
            'AS' => 'ASM',
            'AD' => 'AND',
            'AO' => 'AGO',
            'AI' => 'AIA',
            'AQ' => 'ATA',
            'AG' => 'ATG',
            'AR' => 'ARG',
            'AM' => 'ARM',
            'AW' => 'ABW',
            'AU' => 'AUS',
            'AT' => 'AUT',
            'AZ' => 'AZE',
            'BS' => 'BHS',
            'BH' => 'BHR',
            'BD' => 'BGD',
            'BB' => 'BRB',
            'BY' => 'BLR',
            'BE' => 'BEL',
            'BZ' => 'BLZ',
            'BJ' => 'BEN',
            'BM' => 'BMU',
            'BT' => 'BTN',
            'BO' => 'BOL',
            'BA' => 'BIH',
            'BW' => 'BWA',
            'BV' => 'BVT',
            'BR' => 'BRA',
            'VG' => 'VGB',
            'IO' => 'IOT',
            'BN' => 'BRN',
            'BG' => 'BGR',
            'BF' => 'BFA',
            'BI' => 'BDI',
            'KH' => 'KHM',
            'CM' => 'CMR',
            'CA' => 'CAN',
            'CV' => 'CPV',
            'KY' => 'CYM',
            'CF' => 'CAF',
            'TD' => 'TCD',
            'CL' => 'CHL',
            'CN' => 'CHN',
            'HK' => 'HKG',
            'MO' => 'MAC',
            'CX' => 'CXR',
            'CC' => 'CCK',
            'CO' => 'COL',
            'KM' => 'COM',
            'CG' => 'COG',
            'CD' => 'COD',
            'CK' => 'COK',
            'CR' => 'CRI',
            'CI' => 'CIV',
            'HR' => 'HRV',
            'CU' => 'CUB',
            'CY' => 'CYP',
            'CZ' => 'CZE',
            'DK' => 'DNK',
            'DJ' => 'DJI',
            'DM' => 'DMA',
            'DO' => 'DOM',
            'EC' => 'ECU',
            'EG' => 'EGY',
            'SV' => 'SLV',
            'GQ' => 'GNQ',
            'ER' => 'ERI',
            'EE' => 'EST',
            'ET' => 'ETH',
            'FK' => 'FLK',
            'FO' => 'FRO',
            'FJ' => 'FJI',
            'FI' => 'FIN',
            'FR' => 'FRA',
            'GF' => 'GUF',
            'PF' => 'PYF',
            'TF' => 'ATF',
            'GA' => 'GAB',
            'GM' => 'GMB',
            'GE' => 'GEO',
            'DE' => 'DEU',
            'GH' => 'GHA',
            'GI' => 'GIB',
            'GR' => 'GRC',
            'GL' => 'GRL',
            'GD' => 'GRD',
            'GP' => 'GLP',
            'GU' => 'GUM',
            'GT' => 'GTM',
            'GG' => 'GGY',
            'GN' => 'GIN',
            'GW' => 'GNB',
            'GY' => 'GUY',
            'HT' => 'HTI',
            'HM' => 'HMD',
            'VA' => 'VAT',
            'HN' => 'HND',
            'HU' => 'HUN',
            'IS' => 'ISL',
            'IN' => 'IND',
            'ID' => 'IDN',
            'IR' => 'IRN',
            'IQ' => 'IRQ',
            'IE' => 'IRL',
            'IM' => 'IMN',
            'IL' => 'ISR',
            'IT' => 'ITA',
            'JM' => 'JAM',
            'JP' => 'JPN',
            'JE' => 'JEY',
            'JO' => 'JOR',
            'KZ' => 'KAZ',
            'KE' => 'KEN',
            'KI' => 'KIR',
            'KP' => 'PRK',
            'KR' => 'KOR',
            'KW' => 'KWT',
            'KG' => 'KGZ',
            'LA' => 'LAO',
            'LV' => 'LVA',
            'LB' => 'LBN',
            'LS' => 'LSO',
            'LR' => 'LBR',
            'LY' => 'LBY',
            'LI' => 'LIE',
            'LT' => 'LTU',
            'LU' => 'LUX',
            'MK' => 'MKD',
            'MG' => 'MDG',
            'MW' => 'MWI',
            'MY' => 'MYS',
            'MV' => 'MDV',
            'ML' => 'MLI',
            'MT' => 'MLT',
            'MH' => 'MHL',
            'MQ' => 'MTQ',
            'MR' => 'MRT',
            'MU' => 'MUS',
            'YT' => 'MYT',
            'MX' => 'MEX',
            'FM' => 'FSM',
            'MD' => 'MDA',
            'MC' => 'MCO',
            'MN' => 'MNG',
            'ME' => 'MNE',
            'MS' => 'MSR',
            'MA' => 'MAR',
            'MZ' => 'MOZ',
            'MM' => 'MMR',
            'NA' => 'NAM',
            'NR' => 'NRU',
            'NP' => 'NPL',
            'NL' => 'NLD',
            'NC' => 'NCL',
            'NZ' => 'NZL',
            'NI' => 'NIC',
            'NE' => 'NER',
            'NG' => 'NGA',
            'NU' => 'NIU',
            'NF' => 'NFK',
            'MP' => 'MNP',
            'NO' => 'NOR',
            'OM' => 'OMN',
            'PK' => 'PAK',
            'PW' => 'PLW',
            'PS' => 'PSE',
            'PA' => 'PAN',
            'PG' => 'PNG',
            'PY' => 'PRY',
            'PE' => 'PER',
            'PH' => 'PHL',
            'PN' => 'PCN',
            'PL' => 'POL',
            'PT' => 'PRT',
            'PR' => 'PRI',
            'QA' => 'QAT',
            'RE' => 'REU',
            'RO' => 'ROU',
            'RU' => 'RUS',
            'RW' => 'RWA',
            'BL' => 'BLM',
            'SH' => 'SHN',
            'KN' => 'KNA',
            'LC' => 'LCA',
            'MF' => 'MAF',
            'SX' => 'SXM',
            'PM' => 'SPM',
            'VC' => 'VCT',
            'WS' => 'WSM',
            'SM' => 'SMR',
            'ST' => 'STP',
            'SA' => 'SAU',
            'SN' => 'SEN',
            'RS' => 'SRB',
            'SC' => 'SYC',
            'SL' => 'SLE',
            'SG' => 'SGP',
            'SK' => 'SVK',
            'SI' => 'SVN',
            'SB' => 'SLB',
            'SO' => 'SOM',
            'ZA' => 'ZAF',
            'GS' => 'SGS',
            'SS' => 'SSD',
            'ES' => 'ESP',
            'LK' => 'LKA',
            'SD' => 'SDN',
            'SR' => 'SUR',
            'SJ' => 'SJM',
            'SZ' => 'SWZ',
            'SE' => 'SWE',
            'CH' => 'CHE',
            'SY' => 'SYR',
            'TW' => 'TWN',
            'TJ' => 'TJK',
            'TZ' => 'TZA',
            'TH' => 'THA',
            'TL' => 'TLS',
            'TG' => 'TGO',
            'TK' => 'TKL',
            'TO' => 'TON',
            'TT' => 'TTO',
            'TN' => 'TUN',
            'TR' => 'TUR',
            'TM' => 'TKM',
            'TC' => 'TCA',
            'TV' => 'TUV',
            'UG' => 'UGA',
            'UA' => 'UKR',
            'AE' => 'ARE',
            'GB' => 'GBR',
            'US' => 'USA',
            'UM' => 'UMI',
            'UY' => 'URY',
            'UZ' => 'UZB',
            'VU' => 'VUT',
            'VE' => 'VEN',
            'VN' => 'VNM',
            'VI' => 'VIR',
            'WF' => 'WLF',
            'EH' => 'ESH',
            'YE' => 'YEM',
            'ZM' => 'ZMB',
            'ZW' => 'ZWE',
        ];

        return $conversions[$iso2] ?? null;
    }
}
