<?php
/**
 * EasyTransac checkout page handler.
 *
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

use EasyTransac\Entities\PaymentPageInfos;

class EasyTransacPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
        $cart = $this->context->cart;
        $customer = $this->context->customer;
        $user_address = new Address((int) $cart->id_address_invoice);
        $user_country = new Country((int) $user_address->id_country);
        $api_key = Configuration::get('EASYTRANSAC_API_KEY');
        $total = 100 * $cart->getOrderTotal(true, Cart::BOTH);
        $langcode = $this->context->language->iso_code == 'fr' ? 'FRE' : 'ENG';
        $iso3_user_country = EasyTransac::convertCountryToISO3($user_country->iso_code);
        $this->module->loginit();
        $this->module->debugLog('Start Payment Page Request');
        EasyTransac\Core\Services::getInstance()->provideAPIKey($api_key);

        // Replaces "+" with "00" since there is no calling code field
        if (strpos($user_address->phone, '+') !== false) {
            $user_address->phone = str_replace('+', '00', $user_address->phone);
        }

        // SDK Payment Page
        $customer_ET = (new EasyTransac\Entities\Customer())
                ->setEmail($customer->email)
                ->setUid($user_address->id_customer)
                ->setFirstname($customer->firstname)
                ->setLastname($customer->lastname)
                ->setAddress($user_address->address1 . ' - ' . $user_address->address2)
                ->setZipCode($user_address->postcode)
                ->setCity($user_address->city)
                ->setBirthDate($customer->birthday == '0000-00-00' ? '' : $customer->birthday)
                ->setNationality('')
                ->setCallingCode('')
                ->setCountry($iso3_user_country === null ? '' : $iso3_user_country)
                ->setPhone($user_address->phone);

        $transaction = (new EasyTransac\Entities\PaymentPageTransaction())
                ->setAmount($total)
                ->setCustomer($customer_ET)
                ->setOrderId($this->context->cart->id)
                ->setReturnUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'module/easytransac/validation')
                ->setCancelUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?controller=order&step=3')
                ->setSecure('yes')
                ->setVersion($this->module->get_server_info_string())
                ->setLanguage($langcode);

        // Cart description.
        if ($cart && ($products = $cart->getProducts()) && is_array($products)) {
            $description = [];
            foreach ($products as $product) {
                if (isset($product['name']) && isset($product['cart_quantity'])) {
                    $description[] = $product['cart_quantity'] . 'x ' . $product['name'];
                }
            }
            if ($description) {
                $raw_text = implode(',', $description);
                $description_text = strlen($raw_text) > 255 ? substr($raw_text, 255) . '...' : $raw_text;
                $transaction->setDescription($description_text);
            }
        }

        $request = new EasyTransac\Requests\PaymentPage();

        /* @var  $response PaymentPageInfos */
        try {
            $response = $request->execute($transaction);
        } catch (Exception $exc) {
            $this->module->debugLog('Payment Exception: ' . $exc->getMessage());
        }

        // Store cart_id in session
        $this->module->create_easytransac_order_state();
        $this->context->cookie->cart_id = $this->context->cart->id;
        $this->context->cookie->order_total = $cart->getOrderTotal(true, Cart::BOTH);

        if (!$response->isSuccess()) {
            $this->module->debugLog('Payment Page Request error: ' . $response->getErrorCode() . ' - ' . $response->getErrorMessage());
            throw new Exception('Please check your EasyTransac configuration.');
        } else {
            Tools::redirect($response->getContent()->getPageUrl());
            exit;
        }
    }
}
