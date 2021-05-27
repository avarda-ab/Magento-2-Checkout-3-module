<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Data;

/**
 * Service for creation transferable item object from model
 *
 * @api
 * @since 0.2.0
 */
class ItemDataObjectFactory implements ItemDataObjectFactoryInterface
{
    /**
     * Object Manager instance
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Factory constructor
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * {@inheritdoc}
     */
    public function create(
        ItemAdapterInterface $item,
        $qty,
        $amount,
        $taxAmount
    ) {
        return $this->objectManager->create(
            ItemDataObjectInterface::class,
            [
                'item' => $item,
                'qty' => $qty,
                'amount' => $amount,
                'taxAmount' => $taxAmount,
            ]
        );
    }
}
