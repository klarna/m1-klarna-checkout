<?php
/**
 * Copyright 2018 Klarna Bank AB (publ)
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
 * @category   Klarna
 * @package    Klarna_KcoKred
 * @author     Jason Grim <jason.grim@klarna.com>
 */

/** @var $installer Mage_Sales_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

/**
 * Create table 'klarna_kcokred/push_queue'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('klarna_kcokred/push_queue'))
    ->addColumn(
        'push_queue_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
        ), 'Queue Id'
    )
    ->addColumn('klarna_checkout_id', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(), 'Klarna Checkout Id')
    ->addColumn(
        'count', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
        'nullable' => false,
        ), 'Count'
    )
    ->addColumn(
        'creation_time', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        ), 'Creation Time'
    )
    ->addColumn(
        'update_time', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        ), 'Modification Time'
    )
    ->setComment('Klarna Checkout Push Queue');
$installer->getConnection()->createTable($table);

$installer->endSetup();
