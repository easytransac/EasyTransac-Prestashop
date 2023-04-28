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
class Customer extends CustomerCore
{
    public function getClient_id()
    {
        $sql = 'SELECT client_id FROM `' . _DB_PREFIX_ . 'easytransac_customer` '
            . ' WHERE id_customer = \'' . $this->id . '\'';

        return Db::getInstance()->getValue($sql);
    }

    public function setClient_id($client_id)
    {
        Db::getInstance()->insert('easytransac_customer', [
            'id_customer' => (int) $this->id,
            'client_id' => pSQL($client_id),
        ]);
    }
}
