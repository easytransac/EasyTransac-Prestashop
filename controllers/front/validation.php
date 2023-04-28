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
class EasyTransacValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $this->module->loginit();
        $this->module->debugLog('Start Validation');
        $this->module->debugLog("\n\n" . var_export($_POST, true), FILE_APPEND);

        $this->module->debugLog('Validation order cart id : ' . $this->context->cookie->cart_id);
        $cart = new Cart($this->context->cookie->cart_id);

        if (empty($cart->id)) {
            Logger::AddLog('EasyTransac: Unknown Cart id');
            Tools::redirect('index.php?controller=order&step=1');
        }

        $existing_order_id = OrderCore::getOrderByCartId($this->context->cookie->cart_id);

        $existing_order = new Order($existing_order_id);

        $this->module->debugLog('Validation order id from cart : ' . $existing_order_id);
        $this->module->debugLog('Validation customer : ' . $existing_order->id_customer);

        $this->module->create_easytransac_order_state();

        /*
         * Version 2.1 : spinner forced, update via notification only.
         */
        // HTTP Only
        $this->module->debugLog('Validation redirect');
        // Redirect to validation page.
        if (empty($existing_order->id) || empty($existing_order->current_state)) {
            Tools::redirect(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'module/easytransac/spinner');
        } else {
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $this->context->cookie->cart_id . '&id_module=' . $this->module->id . '&id_order=' . $existing_order->id . '&nohttps=1&key=' . $this->context->customer->secure_key);
        }
    }
}
