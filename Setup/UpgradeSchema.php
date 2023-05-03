<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
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
            $table = $setup->getConnection()
                ->newTable($setup->getTable('avarda3_payment_queue'))
                ->addColumn(
                    'queue_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'Queue ID'
                )
                ->addColumn(
                    'purchase_id',
                    Table::TYPE_TEXT,
                    64,
                    ['nullable' => false],
                    'Purchase ID'
                )
                ->addColumn(
                    'quote_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => true],
                    'Quote ID'
                )
                ->addColumn(
                    'jwt',
                    Table::TYPE_TEXT,
                    256,
                    ['nullable' => true],
                    'Purchase ID'
                )
                ->addColumn(
                    'expires',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => true],
                    'Quote ID'
                )
                ->addColumn(
                    'updated_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
                    'Updated At'
                )
                ->addIndex(
                    $setup->getIdxName(
                        'avarda3_payment_queue',
                        ['purchase_id'],
                        AdapterInterface::INDEX_TYPE_UNIQUE
                    ),
                    ['purchase_id'],
                    ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
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
                    Table::ACTION_SET_NULL
                )
                ->setComment('Avarda Payment Queue');
            $setup->getConnection()->createTable($table);

            $table = $setup->getConnection()
                ->newTable($setup->getTable('avarda3_order_created'))
                ->addColumn(
                    'entity_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'Entity ID'
                )
                ->addColumn(
                    'purchase_id',
                    Table::TYPE_TEXT,
                    64,
                    ['nullable' => false],
                    'Purchase ID'
                )->addIndex(
                    $setup->getIdxName(
                        'avarda3_order_created',
                        ['purchase_id'],
                        AdapterInterface::INDEX_TYPE_UNIQUE
                    ),
                    ['purchase_id'],
                    ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
                );
            $setup->getConnection()->createTable($table);
        }

        if (version_compare($context->getVersion(), '1.1.4', '<')) {
            $setup->getConnection()
                ->addColumn(
                    $setup->getTable('avarda3_payment_queue'),
                    'is_processed',
                    [
                        'type'     => Table::TYPE_INTEGER,
                        'default'  => 0,
                        'unsigned' => true,
                        'nullable' => false,
                        'comment'  => 'Is queue processed',
                    ]
                );
        }

        if (version_compare($context->getVersion(), '1.2.7', '<')) {
            $setup->getConnection()
                ->addColumn(
                    $setup->getTable('avarda3_order_created'),
                    'order_id',
                    [
                        'type'     => Table::TYPE_INTEGER,
                        'unsigned' => true,
                        'nullable' => true,
                        'comment'  => 'Order Id',
                    ]
                );
            $setup->getConnection()
                ->addIndex(
                    $setup->getTable('avarda3_order_created'),
                    $setup->getIdxName(
                        'avarda3_order_created',
                        ['order_id'],
                        AdapterInterface::INDEX_TYPE_INDEX
                    ),
                    ['order_id']
                );
        }

        $setup->endSetup();
    }
}
