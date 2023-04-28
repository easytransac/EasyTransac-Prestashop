<?php
/**
 * EasyTransac spinner controller.
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
class EasyTransacSpinnerModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;

    public function initContent()
    {
        $this->module->loginit();
        $this->module->debugLog('Start Spinner');

        parent::initContent();
        $this->module->debugLog('Spinner context cookie cart: ' . $this->context->cookie->cart_id);
        $existing_order = Order::getByCartId($this->context->cookie->cart_id);

        $this->module->debugLog('Spinner context order id: ' . $existing_order->id);

        // Check if order_id and current_states are not empty.
        // When an order is initialized, it has still a current state 0.
        if (Validate::isLoadedObject($existing_order) && !empty($existing_order->current_state)) {
            // EasyTransac API returned and validated order.
            Tools::redirect(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'module/easytransac/validation');

            return;
        }

        $this->setTemplate('module:easytransac/views/templates/front/spinner.tpl');
    }

    public function postProcess()
    {
        $this->context->smarty->assign([
            'isPending' => 1,
            'isCanceled' => 0,
            'isAccepted' => 0,
        ]);
    }
}
