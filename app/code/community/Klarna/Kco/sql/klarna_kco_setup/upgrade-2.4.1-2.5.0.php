<?php
/**
 * Copyright 2019 Klarna AB
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

/**
 * Converting serialized custom checkbox data to JSON encoded
 */

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$connection     = $installer->getConnection();
$table          = 'core_config_data';
$pathValue      = 'checkout/klarna_kco/custom_checkboxes';
$configIdColumn = 'config_id';
$select         = $connection->select()
    ->from($table)
    ->where('path = ?', $pathValue);

$checkboxesRows = $select->query()->fetchAll();
foreach ($checkboxesRows as $row) {
    $configValue  = $row['value'];
    $unserialized = unserialize($configValue);

    // If current value isn't serialized, skip encoding
    if ($unserialized !== false) {
        $configId                       = $row[$configIdColumn];
        $encodedConfig                  = json_encode($unserialized);
        $updateBind                     = array(
            'value'                     => $encodedConfig
        );
        $where["{$configIdColumn} = ?"] = $configId;

        $connection->update($table, $updateBind, $where);
    }
}

$installer->endSetup();
