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

/**
 * GuestPaymentManagement
 * @see \Avarda\Checkout3\Api\GuestPaymentManagementInterface
 */
class GuestPaymentManagement implements GuestPaymentManagementInterface
{
    /**
     * Required to create purchase ID response.
     *
     * @var PaymentDetailsInterfaceFactory
     */
    protected $paymentDetailsFactory;

    /**
     * A common interface to execute Webapi actions.
     *
     * @var QuotePaymentManagementInterface
     */
    protected $quotePaymentManagement;

    /**
     * Required to get the real quote ID from masked quote ID.
     *
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * GuestPaymentManagement constructor.
     *
     * @param PaymentDetailsInterfaceFactory $paymentDetailsFactory
     * @param QuotePaymentManagementInterface $quotePaymentManagement
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
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
