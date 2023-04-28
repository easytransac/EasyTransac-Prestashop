<?php
/**
 * @author Easytransac SAS
 * @copyright 2022 Easytransac
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
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Prestashop installation class.
 */
class EasyTransacInstall
{
    /**
     * Set configuration table
     */
    public function updateConfiguration()
    {
        Configuration::updateValue('EASYTRANSAC_API_KEY', 0);
        Configuration::updateValue('EASYTRANSAC_DEBUG', 0);
        Configuration::updateValue('EASYTRANSAC_ONECLICK', 0);
        Configuration::updateValue('EASYTRANSAC_MULTIPAY', 0);
        Configuration::updateValue('EASYTRANSAC_MULTIPAY2X', 0);
        Configuration::updateValue('EASYTRANSAC_MULTIPAY3X', 0);
        Configuration::updateValue('EASYTRANSAC_MULTIPAY4X', 0);
    }

    /**
     * Delete EasyTransac configuration
     */
    public function deleteConfiguration()
    {
        Configuration::deleteByName('EASYTRANSAC_API_KEY');
    }

    /**
     * Create EasyTransac table
     */
    public function createTables()
    {
        $sqls = [
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'easytransac_customer` (
                `id_customer` int(10) unsigned NOT NULL,
                `client_id` VARCHAR(20) NOT NULL,
                PRIMARY KEY (`id_customer`)                
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;',

            'ALTER TABLE `' . _DB_PREFIX_ . 'easytransac_customer` ADD KEY `easytransac_client_id` (`client_id`);',

            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'easytransac_transaction` (
                `id_order` int(10) unsigned NOT NULL,
                `external_id` VARCHAR(20) NOT NULL,
                PRIMARY KEY (`id_order`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;',

            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'easytransac_message` (
                `id_order` int(10) unsigned NOT NULL,
                `date` DATETIME NOT NULL,
                `message` VARCHAR(256) NOT NULL,
                `status` VARCHAR(20) NOT NULL,
                `external_id` VARCHAR(20) NOT NULL,
                `amount` int(10) NULL,
                INDEX (`id_order`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;',
        ];

        foreach ($sqls as $sql) {
            if (!Db::getInstance()->Execute($sql)) {
                return false;
            }
        }
    }

    /**
     * Delete EasyTransac table
     */
    public function deleteTables()
    {
        Db::getInstance()->Execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'easytransac_customer`;');
        Db::getInstance()->Execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'easytransac_transaction`;');
        Db::getInstance()->Execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'easytransac_message`;');
    }
}
