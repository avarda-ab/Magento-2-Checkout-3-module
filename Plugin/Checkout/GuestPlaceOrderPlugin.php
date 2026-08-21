<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Plugin\Checkout;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\PaymentQueueRepositoryInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Avarda\Checkout3\Model\OrderPlacementState;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote\AddressFactory;
use Psr\Log\LoggerInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class GuestPlaceOrderPlugin extends PlaceOrderPluginAbstract
{
    protected CartRepositoryInterface $cartRepository;
    protected QuoteIdMaskFactory $quoteIdMaskFactory;
    protected OrderRepositoryInterface $orderRepository;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        AvardaOrderRepositoryInterface $avardaOrderRepository,
        PaymentData $paymentDataHelper,
        OrderRepositoryInterface $orderRepository,
        AddressFactory $addressFactory,
        QuotePaymentManagementInterface $quotePaymentManagement,
        PurchaseState $purchaseStateHelper,
        PaymentQueueRepositoryInterface $paymentQueueRepository,
        OrderPlacementState $orderPlacementState,
        LoggerInterface $logger,
    ) {
        $this->cartRepository = $cartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->orderRepository = $orderRepository;
        parent::__construct(
            $avardaOrderRepository,
            $addressFactory,
            $quotePaymentManagement,
            $paymentDataHelper,
            $purchaseStateHelper,
            $paymentQueueRepository,
            $orderPlacementState,
            $logger,
        );
    }

    /**
     * @param $subject
     * @param $cartId
     * @param $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return void|array
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        $subject,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ) {
        if (isset($paymentMethod->getAdditionalData()['avarda'])) {
            $additionalData = json_decode($paymentMethod->getAdditionalData()['avarda'] ?? '', true);
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            $quote = $this->cartRepository->get($quoteIdMask->getQuoteId());

            $this->validatePurchase($quote, $additionalData);

            // Everything below re-collects totals from Avarda's address
            $this->orderPlacementState->start();

            $this->setShippingAddress($quote, $additionalData);
            $billingAddress = $this->setBillingAddress($billingAddress, $additionalData);
            $email = $this->checkEmail($email, $additionalData);

            $this->assertTotalMatchesPurchase($quote);

            return [$cartId, $email, $paymentMethod, $billingAddress];
        }
    }

    /**
     * @param $subject
     * @param $orderId
     * @return mixed
     * @throws AlreadyExistsException
     */
    public function afterSavePaymentInformationAndPlaceOrder($subject, $orderId)
    {
        $this->orderPlacementState->stop();

        $order = $this->orderRepository->get($orderId);
        if ($this->paymentDataHelper->isAvardaPayment($order->getPayment())) {
            $this->saveOrderCreated($orderId, $order);
        }

        return $orderId;
    }
}
