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
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class PlaceOrderPlugin extends PlaceOrderPluginAbstract
{
    protected CartRepositoryInterface $cartRepository;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        AvardaOrderRepositoryInterface $avardaOrderRepository,
        PaymentData $paymentDataHelper,
        AddressFactory $addressFactory,
        QuotePaymentManagementInterface $quotePaymentManagement,
        PurchaseState $purchaseStateHelper,
        PaymentQueueRepositoryInterface $paymentQueueRepository,
        OrderPlacementState $orderPlacementState,
        LoggerInterface $logger,
        LockManagerInterface $lockManager,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
        $this->cartRepository = $cartRepository;
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
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return void|array
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ) {
        if (isset($paymentMethod->getAdditionalData()['avarda'])) {
            $additionalData = json_decode($paymentMethod->getAdditionalData()['avarda'] ?? '', true);
            $quote = $this->cartRepository->getActive($cartId);

            // The after-plugin never runs when placement is refused here, and the state is a shared instance
            try {
                $this->validatePurchase($quote, $additionalData);

                $this->setShippingAddress($quote, $additionalData);
                $billingAddress = $this->setBillingAddress($billingAddress, $additionalData);

                $this->assertTotalMatchesPurchase($quote);
            } catch (Exception $e) {
                $this->orderPlacementState->stop();
                throw $e;
            }

            return [$cartId, $paymentMethod, $billingAddress];
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
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null,
    ) {
        $place = function () use ($proceed, $cartId, $paymentMethod, $billingAddress) {
            return $proceed($cartId, $paymentMethod, $billingAddress);
        };
        if (!isset($paymentMethod->getAdditionalData()['avarda'])) {
            return $place();
        }

        return $this->placeOrderOnce($this->cartRepository->get($cartId), $place);
    }

    /**
     * @param $subject
     * @param $orderId
     * @param $cartId
     * @return mixed
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    public function afterSavePaymentInformationAndPlaceOrder($subject, $orderId, $cartId)
    {
        $this->orderPlacementState->stop();

        $quote = $this->cartRepository->get($cartId);
        if ($this->paymentDataHelper->isAvardaPayment($quote->getPayment())) {
            $this->saveOrderCreated($orderId, $quote);
        }

        return $orderId;
    }
}
