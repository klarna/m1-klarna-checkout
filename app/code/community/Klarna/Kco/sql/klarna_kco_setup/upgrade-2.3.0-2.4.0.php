<?php
/**
 * Creating the table klarna_kco_shipping_method_gateway
 */
$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('klarna_kco/shipping_method_gateway'))
    ->addColumn(
        'kco_shipping_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
        ),
        'Klarna shipping id'
    )
    ->addColumn(
        'klarna_checkout_id', Varien_Db_Ddl_Table::TYPE_TEXT, 50, array(
            'nullable' => false,
        ),
        'Klarna checkout id'
    )
    ->addColumn(
        'is_active', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
            'nullable' => false,
            'default' => 0
        ),
        'Provides the information if it can be used for the respective klarna quote'
    )
    ->addColumn(
        'is_pick_up_point', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
            'nullable' => false,
            'default' => 0
        ),
        'Indicates if the selected shipping method is a pickup point'
    )
    ->addColumn(
        'pick_up_point_name', Varien_Db_Ddl_Table::TYPE_TEXT, 50, array(
            'nullable' => false,
        ),
        'Name of the pickup point'
    )
    ->addColumn(
        'shipping_amount', Varien_Db_Ddl_Table::TYPE_DECIMAL, '10,2', array(
            'nullable' => false,
            'default' => 0
        ),
        'Shipping amount'
    )
    ->addColumn(
        'tax_amount', Varien_Db_Ddl_Table::TYPE_DECIMAL, '10,2', array(
            'nullable' => false,
            'default' => 0
        ),
        'Tax amount'
    )
    ->addColumn(
        'tax_rate', Varien_Db_Ddl_Table::TYPE_DECIMAL, '5,2', array(
            'nullable' => false,
            'default' => 0
        ),
        'Tax rate'
    );

$installer->getConnection()->createTable($table);
$installer->endSetup();