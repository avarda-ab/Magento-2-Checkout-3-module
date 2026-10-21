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
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Lock\LockManagerInterface;
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
        LockManagerInterface $lockManager,
        SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
        $this->cartRepository = $cartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        parent::__construct(
            $avardaOrderRepository,
            $addressFactory,
            $quotePaymentManagement,
            $paymentDataHelper,
            $purchaseStateHelper,
            $paymentQueueRepository,
            $orderPlacementState,
            $logger,
            $lockManager,
            $orderRepository,
            $searchCriteriaBuilder,
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

            // The after-plugin never runs when placement is refused here, and the state is a shared instance
            try {
                $this->validatePurchase($quote, $additionalData);

                $this->setShippingAddress($quote, $additionalData);
                $billingAddress = $this->setBillingAddress($billingAddress, $additionalData);
                $email = $this->checkEmail($email, $additionalData);

                $this->assertTotalMatchesPurchase($quote);
            } catch (Exception $e) {
                $this->orderPlacementState->stop();
                throw $e;
            }

            return [$cartId, $email, $paymentMethod, $billingAddress];
        }
    }

    /**
     * @return int|string
     * @throws LocalizedException
     */
    public function aroundSavePaymentInformationAndPlaceOrder(
        $subject,
        callable $proceed,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null,
    ) {
        $place = function () use ($proceed, $cartId, $email, $paymentMethod, $billingAddress) {
            return $proceed($cartId, $email, $paymentMethod, $billingAddress);
        };
        if (!isset($paymentMethod->getAdditionalData()['avarda'])) {
            return $place();
        }

        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');

        return $this->placeOrderOnce($this->cartRepository->get($quoteIdMask->getQuoteId()), $place);
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
