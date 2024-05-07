<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Avarda\Checkout3\Api\Data\PaymentDetailsInterfaceFactory;
use Avarda\Checkout3\Api\GuestPaymentManagementInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Magento\Framework\Exception\PaymentException;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestPaymentManagement implements GuestPaymentManagementInterface
{
    protected PaymentDetailsInterfaceFactory $paymentDetailsFactory;
    protected QuotePaymentManagementInterface $quotePaymentManagement;
    protected QuoteIdMaskFactory $quoteIdMaskFactory;

    public function __construct(
        PaymentDetailsInterfaceFactory $paymentDetailsFactory,
        QuotePaymentManagementInterface $quotePaymentManagement,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->paymentDetailsFactory = $paymentDetailsFactory;
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getPurchaseData($cartId, $renew = false)
    {
        $purchaseData = $this->quotePaymentManagement->getPurchaseData(
            $this->getQuoteId($cartId),
            $renew
        );

        $paymentDetails = $this->paymentDetailsFactory->create();
        $paymentDetails->setPurchaseData($purchaseData);
        return $paymentDetails;
    }

    /**
     * {@inheritdoc}
     */
    public function freezeCart($cartId)
    {
        $this->quotePaymentManagement
            ->setQuoteIsActive($this->getQuoteId($cartId), false);
    }

    /**
     * {@inheritdoc}
     */
    public function getItemDetailsList($cartId)
    {
        return $this->quotePaymentManagement
            ->getItemDetailsList($this->getQuoteId($cartId));
    }

    /**
     * Get the quote ID from masked cart ID.
     *
     * Note: getQuoteId() == $cartId == quote::entity_id
     *
     * @param string $cartId
     * @return int
     * @throws PaymentException
     */
    protected function getQuoteId($cartId)
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()
            ->load($cartId, 'masked_id');

        $quoteId = $quoteIdMask->getData('quote_id');
        if ($quoteId === null) {
            throw new PaymentException(
                __('Could not find quote with given ID.')
            );
        }

        return $quoteId;
    }
}
