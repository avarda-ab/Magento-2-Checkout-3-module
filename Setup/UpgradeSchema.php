<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            /**
             * Create table 'avarda_order_queue'
             */
            $table = $setup->getConnection()
                ->newTable($setup->getTable('avarda3_payment_queue'))
                ->addColumn(
                    'queue_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'Queue ID'
                )
                ->addColumn(
                    'purchase_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    64,
                    ['nullable' => false],
                    'Purchase ID'
                )
                ->addColumn(
                    'quote_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => true],
                    'Quote ID'
                )
                ->addColumn(
                    'jwt',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    256,
                    ['nullable' => true],
                    'Purchase ID'
                )
                ->addColumn(
                    'expires',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => true],
                    'Quote ID'
                )
                ->addColumn(
                    'updated_at',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
                    'Updated At'
                )
                ->addIndex(
                    $setup->getIdxName(
                        'avarda3_payment_queue',
                        ['purchase_id'],
                        \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
                    ),
                    ['purchase_id'],
                    ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
                )
                ->addIndex(
                    $setup->getIdxName('avarda3_payment_queue', ['updated_at']),
                    ['updated_at']
                )
                ->addForeignKey(
                    $setup->getFkName('avarda3_payment_queue', 'quote_id', 'quote', 'entity_id'),
                    'quote_id',
                    $setup->getTable('quote'),
                    'entity_id',
                    \Magento\Framework\DB\Ddl\Table::ACTION_SET_NULL
                )
                ->setComment('Avarda Payment Queue');
            $setup->getConnection()->createTable($table);

            $table = $setup->getConnection()
                ->newTable($setup->getTable('avarda3_order_created'))
                ->addColumn(
                    'entity_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'Entity ID'
                )
                ->addColumn(
                    'purchase_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    64,
                    ['nullable' => false],
                    'Purchase ID'
                )->addIndex(
                    $setup->getIdxName(
                        'avarda3_order_created',
                        ['purchase_id'],
                        \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
                    ),
                    ['purchase_id'],
                    ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
                );
            $setup->getConnection()->createTable($table);
        }

        $setup->endSetup();
    }
}
