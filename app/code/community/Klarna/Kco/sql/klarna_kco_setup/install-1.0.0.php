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
 * @package    Klarna_Kco
 * @author     Jason Grim <jason.grim@klarna.com>
 */

/** @var $installer Mage_Sales_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

/**
 * Create table 'klarna_kco/quote'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('klarna_kco/quote'))
    ->addColumn(
        'kco_quote_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
        ), 'Checkout Id'
    )
    ->addColumn('klarna_checkout_id', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(), 'Klarna Checkout Id')
    ->addColumn(
        'is_active', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'nullable' => false,
        'default'  => '0',
        ), 'Is Active'
    )
    ->addColumn(
        'quote_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
        'nullable' => false,
        ), 'Quote Id'
    )
    ->addColumn(
        'is_changed', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'nullable' => false,
        'default'  => '0',
        ), 'Klarna Checkout Id'
    )
    ->addForeignKey(
        $installer->getFkName('klarna_kco/quote', 'quote_id', 'sales/quote', 'entity_id'),
        'quote_id', $installer->getTable('sales/quote'), 'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('Klarna Checkout Quote');
$installer->getConnection()->createTable($table);

/**
 * Create table 'klarna_kco/order'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('klarna_kco/order'))
    ->addColumn(
        'kco_order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
        ), 'Order Id'
    )
    ->addColumn('klarna_checkout_id', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(), 'Klarna Checkout Id')
    ->addColumn('klarna_reservation_id', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(), 'Klarna Reservation Id')
    ->addColumn(
        'order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
        'nullable' => false,
        ), 'Order Id'
    )
    ->addColumn(
        'is_acknowledged', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'nullable' => false,
        'default'  => '0',
        ), 'Is Acknowledged'
    )
    ->addForeignKey(
        $installer->getFkName('klarna_kco/order', 'order_id', 'sales/order', 'entity_id'),
        'order_id', $installer->getTable('sales/order'), 'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('Klarna Checkout Order');
$installer->getConnection()->createTable($table);

$installer->endSetup();
