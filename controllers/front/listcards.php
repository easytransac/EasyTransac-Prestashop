<?php
/**
 * EasyTransac listcards controller.
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
class EasyTransacListcardsModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        if (!$this->context->customer->id) {
            exit;
        }

        if (!Configuration::get('EASYTRANSAC_ONECLICK')) {
            exit(json_encode(['status' => 0]));
        }

        $this->module->loginit();

        if (!$this->module->isCustomerKnown()) {
            exit(json_encode(['status' => 0]));
        }
        $this->module->debugLog('Client id', $this->context->customer->getClient_id());

        EasyTransac\Core\Services::getInstance()->provideAPIKey(Configuration::get('EASYTRANSAC_API_KEY'));
        $clientId = $this->context->customer->getClient_id();

        $customer = (new EasyTransac\Entities\Customer())->setClientId($clientId);

        $request = new EasyTransac\Requests\CreditCardsList();
        $response = $request->execute($customer);

        if ($response->isSuccess()) {
            $buffer = [];
            foreach ($response->getContent()->getCreditCards() as $cc) {
                /* @var $cc EasyTransac\Entities\CreditCard */
                $year = substr($cc->getYear(), -2, 2);

                $buffer[] = ['Alias' => $cc->getAlias(), 'CardNumber' => $cc->getNumber(), 'CardYear' => $year, 'CardMonth' => $cc->getMonth()];
            }
            $output = ['status' => !empty($buffer), 'packet' => $buffer];
            $this->module->debugLog($output);
            echo json_encode($output);
            exit;
        } else {
            $this->module->debugLog('List Cards Error: ' . $response->getErrorCode() . ' - ' . $response->getErrorMessage());
        }
        exit(json_encode(['status' => 0]));
    }
}
