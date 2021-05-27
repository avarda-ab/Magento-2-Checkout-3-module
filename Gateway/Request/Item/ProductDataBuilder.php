<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Request\Item;

use Avarda\Checkout3\Gateway\Helper\ItemSubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class ProductDataBuilder
 */
class ProductDataBuilder implements BuilderInterface
{
    /**
     * String (max. 35 characters)
     */
    const DESCRIPTION = 'Description';

    /**
     * String (max. 35 characters)
     */
    const NOTES = 'Notes';

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $item = ItemSubjectReader::readItem($buildSubject);

        return [
            self::DESCRIPTION => mb_substr($item->getName(), 0, 35),
            self::NOTES => mb_substr($item->getSku(), 0, 35),
        ];
    }
}
