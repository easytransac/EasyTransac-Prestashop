<?php
/**
 * EasyTransac oneclick controller.
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
use EasyTransac\Entities\DoneTransaction;

class EasyTransacOneClickModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $this->module->loginit();
        $this->module->debugLog('Start Oneclick');

        $this->module->debugLog($this->context->cart->getOrderTotal(true, Cart::BOTH));
        if (!$this->context->customer->id || empty(Tools::getValue('Alias')) || !$this->context->cart->id) {
            exit;
        }

        $dump_path = __DIR__ . '/dump';
        $api_key = Configuration::get('EASYTRANSAC_API_KEY');
        $total = 100 * $this->context->cart->getOrderTotal(true, Cart::BOTH);
        //		EasyTransac\Core\Services::getInstance()->setDebug(true);
        EasyTransac\Core\Services::getInstance()->provideAPIKey($api_key);

        // SDK OneClick
        $transaction = (new EasyTransac\Entities\OneClickTransaction())
                ->setAlias(strip_tags(Tools::getValue('Alias')))
                ->setAmount($total)
                ->setOrderId($this->context->cart->id)
                ->setClientId($this->context->customer->getClient_id())
                ->setSecure('yes')
                ->setReturnUrl(
                    Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'module/easytransac/validation'
                );

        $dp = new EasyTransac\Requests\OneClickPayment();
        $response = $dp->execute($transaction);

        if (!$response->isSuccess()) {
            echo json_encode([
                'error' => 'yes', 'message' => $response->getErrorMessage(),
            ]);

            return;
        }

        /* @var  $doneTransaction DoneTransaction */
        $doneTransaction = $response->getContent();

        $url = $doneTransaction->getSecureUrl();
        if ($url) {
            echo json_encode([
                'redirect_page' => $url,
            ]);

            return;
        }

        $this->module->create_easytransac_order_state();

        $cart = new Cart($doneTransaction->getOrderId());

        if (empty($cart->id)) {
            Logger::AddLog('EasyTransac:OneClick: Unknown Cart id');
            exit;
        }
        $customer = new Customer($cart->id_customer);

        $existing_order_id = OrderCore::getOrderByCartId($doneTransaction->getOrderId());
        $existing_order = new Order($existing_order_id);

        $this->module->debugLog('OneClick customer : ' . $existing_order->id_customer);

        $payment_status = null;

        $payment_message = $doneTransaction->getMessage();

        if ($doneTransaction->getError()) {
            $payment_message .= '.' . $doneTransaction->getError();
        }

        $error2 = $doneTransaction->getAdditionalError();
        if (!empty($error2) && is_array($error2)) {
            $payment_message .= ' ' . implode(' ', $error2);
        }

        // 2: payment accepted, 6: canceled, 7: refunded, 8: payment error
        switch ($doneTransaction->getStatus()) {
            case 'captured':
                $payment_status = 2;
                break;

            case 'pending':
                $payment_status = $this->module->get_pending_payment_state();
                break;

            case 'refunded':
                $payment_status = 7;
                break;

            case 'failed':
            default:
                $payment_status = 8;
                break;
        }

        $this->module->debugLog('OneClick for OrderId : ' . $doneTransaction->getOrderId() . ', Status: ' . $doneTransaction->getStatus() . ', Prestashop Status: ' . $payment_status);

        // Check that paid amount matches cart total (v1.7)
        // String string format compare
        $paid_total = number_format($doneTransaction->getAmount(), 2, '.', '');
        $cart_total = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
        $amount_match = $paid_total === $cart_total;

        $this->module->debugLog('OneClick Paid total: ' . $paid_total . ' prestashop price: ' . $cart_total);

        if (!$amount_match && 2 == $payment_status) {
            $payment_message = $this->module->l('Price paid on EasyTransac is not the same that on Prestashop - Transaction : ') . $doneTransaction->getTid();
            $payment_status = 8;
            $this->module->debugLog('OneClick Amount mismatch');
        }

        // Creating Order
        $total_paid_float = (float) $paid_total;
        $this->module->debugLog('OneClick Total paid float: ' . $total_paid_float);

        if ('failed' != $doneTransaction->getStatus()) {
            $this->module->validateOrder($cart->id, $payment_status, $total_paid_float, $this->module->displayName, $payment_message, $mailVars = [], null, false, $customer->secure_key);

            $existing_order_id = OrderCore::getOrderByCartId($cart->id);

            // for Prestashop >= 1.7.7
            $this->module->addTransactionMessage(
                $existing_order_id,
                $doneTransaction->getTid(),
                $this->l('One Click payment processed') . ': ' . $doneTransaction->getStatus(),
                $doneTransaction->getAmount() * 100,
                $doneTransaction->getStatus());
        }
        $this->module->debugLog('OneClick Order validated');

        // AJAX Output
        $json_status_output = '';
        switch ($doneTransaction->getStatus()) {
            case 'captured':
            case 'pending':
                $json_status_output = 'processed';
                break;

            default:
                $json_status_output = 'failed';
                break;
        }

        if ($doneTransaction->getError()) {
            $this->module->debugLog('OneClick error:' . $doneTransaction->getError());
            echo json_encode([
                'error' => 'yes',
                'message' => $doneTransaction->getError(),
            ]);
        } else {
            $next_hop = sprintf('%s/index.php?controller=order-confirmation&id_cart=%d&id_module=%d&id_order=%d&key=%s', Tools::getShopDomainSsl(true, true), $this->context->cookie->cart_id, $this->module->id, $this->module->currentOrder, $this->context->customer->secure_key);
            echo json_encode([
                'paid_status' => $json_status_output,
                'error' => 'no',
                'redirect_page' => $next_hop,
            ]);
        }
        exit;
    }
}
