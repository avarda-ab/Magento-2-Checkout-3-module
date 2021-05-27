<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Request;

use Avarda\Checkout3\Api\ItemStorageInterface;
use Avarda\Checkout3\Gateway\Data\ItemDataObjectInterface;
use Magento\Framework\Exception\PaymentException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;

/**
 * Class AmountDataBuilder
 */
class ItemsDataBuilder implements BuilderInterface
{
    use Formatter;

    /**
     * The amount to add to the payment
     */
    const ITEMS = 'Items';

    /**
     * @var ItemStorageInterface
     */
    protected $itemStorage;

    /**
     * @var BuilderInterface
     */
    protected $itemBuilder;

    /**
     * ItemsDataBuilder constructor.
     *
     * @param ItemStorageInterface $itemStorage
     * @param BuilderInterface $itemBuilder
     */
    public function __construct(
        ItemStorageInterface $itemStorage,
        BuilderInterface $itemBuilder
    ) {
        $this->itemStorage = $itemStorage;
        $this->itemBuilder = $itemBuilder;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $preparedItems = $this->itemStorage->getItems();
        if (count($preparedItems) === 0) {
            throw new PaymentException(
                __('Could not generate items for Avarda checkout.')
            );
        }

        $items[self::ITEMS] = [];
        foreach ($preparedItems as $preparedItem) {
            if (!$preparedItem instanceof ItemDataObjectInterface) {
                throw new PaymentException(
                    __('Could not generate items for Avarda checkout.')
                );
            }

            $items[self::ITEMS][] = (object) $this->itemBuilder->build(
                $preparedItem->getSubject()
            );
        }

        return $items;
    }
}
