<?php
namespace Ebanx\Standard\Setup;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
	public function install(SchemaSetupInterface $setup, ModuleContextInterface $context){
		
		$installer = $setup;
		$installer->startSetup();
		$table = $installer->getConnection()->newTable(
			$installer->getTable('ebanx_payment')
		)->addColumn(
			'id',
			\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
			null,
			array('identity' => true, 'nullable' => false, 'primary' => true),
			'ID'
		)->addColumn(
			'hash',
			\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
			null,
			array('nullable' => false),
			'Hash'
		)->addColumn(
			'order_id',
			\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
			255,
			array('nullable' => false),
			'Transaction ID (Order Number in Magento)'
		)->addColumn(
			'status', 
			\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
			255,
			array('nullable' => false),
			'Ebanx transaction status'
		)->addColumn(
			'open_date',
			\Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
			null,
			array(),
			'Transaction open date'
		)->addColumn(
			'due_date',
			\Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
			null,
			array(),
			'Transaction due date'
		)->addColumn(
			'confirm_date',
			\Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
			null,
			array(),
			'Transaction confirm date'
		)->addColumn(
            'amount',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            array('nullable' => false),
            'Transaction amount'
		)->addColumn(
            'payment_method',
           \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
			255,
			array('nullable' => false),
            'Payment method'
        )->addColumn(
            'instalments',
           \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
			255,
			array('nullable' => false),
            'Payment Instalments'
        )->addColumn(
            'merchant_payment_code',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            555,
            array(),
            'Merchant payment code'
		);
		$installer->getConnection()->createTable($table);		
		$installer->endSetup();
	}
}