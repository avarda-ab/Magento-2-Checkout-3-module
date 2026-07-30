<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Plugin\Checkout;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Avarda\Checkout3\Api\PaymentQueueRepositoryInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

abstract class PlaceOrderPluginAbstract
{
    protected AvardaOrderRepositoryInterface $avardaOrderRepository;
    protected AddressFactory $addressFactory;
    protected QuotePaymentManagementInterface $quotePaymentManagement;
    protected PaymentData $paymentDataHelper;
    protected PurchaseState $purchaseStateHelper;
    protected PaymentQueueRepositoryInterface $paymentQueueRepository;

    public function __construct(
        AvardaOrderRepositoryInterface $avardaOrderRepository,
        AddressFactory $addressFactory,
        QuotePaymentManagementInterface $quotePaymentManagement,
        PaymentData $paymentDataHelper,
        PurchaseState $purchaseStateHelper,
        PaymentQueueRepositoryInterface $paymentQueueRepository
    ) {
        $this->avardaOrderRepository = $avardaOrderRepository;
        $this->addressFactory = $addressFactory;
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->paymentDataHelper = $paymentDataHelper;
        $this->purchaseStateHelper = $purchaseStateHelper;
        $this->paymentQueueRepository = $paymentQueueRepository;
    }

    /**
     * @param $quote CartInterface
     * @param $additionalData array
     * @return void
     */
    public function setShippingAddress($quote, $additionalData)
    {
        $shippingAddress = $quote->getShippingAddress();
        $isInStorePickup = $shippingAddress->getShippingMethod() === InStorePickup::DELIVERY_METHOD;

        $deliverySource = $additionalData['deliveryAddress'];
        if (empty($deliverySource['firstName']) && empty($deliverySource['address1'])) {
            $deliverySource = $additionalData['invoicingAddress'];
        }

        // If b2b address there should be company name,
        // but if no delivery address given it might not be there
        if ($additionalData['mode'] == 'B2B' && isset($deliverySource['name'])) {
            $shippingAddress->setFirstname($deliverySource['name']);
            $shippingAddress->setLastname($deliverySource['name']);
            $shippingAddress->setCompany($deliverySource['name']);
        } else {
            $shippingAddress->setFirstname($deliverySource['firstName']);
            $shippingAddress->setLastname($deliverySource['lastName']);
        }

        if (!$isInStorePickup) {
            $shippingAddress->setStreet([$deliverySource['address1'], $deliverySource['address2']]);
            $shippingAddress->setCity($deliverySource['city']);
            $shippingAddress->setPostcode($deliverySource['zip']);
            $shippingAddress->setCountryId($deliverySource['country']);
        }

        // @todo fix this when phone number is available
        // We don't have customer phone number, so we add dummy here
        // After order is paid it will be updated by status update
        $shippingAddress->setTelephone('010123123');
        $quote->setShippingAddress($shippingAddress);
    }

    /**
     * @param $billingAddress AddressInterface
     * @param $additionalData array
     * @return AddressInterface
     */
    public function setBillingAddress($billingAddress, $additionalData)
    {
        if (!$billingAddress) {
            $billingAddress = $this->addressFactory->create();
        }

        if ($additionalData['mode'] == 'B2B') {
            $billingAddress->setFirstname($additionalData['invoicingAddress']['name']);
            $billingAddress->setLastname($additionalData['invoicingAddress']['name']);
            $billingAddress->setCompany($additionalData['invoicingAddress']['name']);
        } else {
            $billingAddress->setFirstname($additionalData['invoicingAddress']['firstName']);
            $billingAddress->setLastname($additionalData['invoicingAddress']['lastName']);
        }

        $billingAddress->setStreet([$additionalData['invoicingAddress']['address1'], $additionalData['invoicingAddress']['address2']]);
        $billingAddress->setCity($additionalData['invoicingAddress']['city']);
        $billingAddress->setPostcode($additionalData['invoicingAddress']['zip']);
        $billingAddress->setCountryId($additionalData['invoicingAddress']['country']);

        // @todo fix this when phone number is available
        // We don't have customer phone number, so we add dummy here
        // After order is paid it will be updated by status update
        $billingAddress->setTelephone('010123123');

        return $billingAddress;
    }

    /**
     * @param $orderId int|string
     * @param $order Quote|CartInterface|Order|OrderInterface
     * @return void
     * @throws AlreadyExistsException
     */
    public function saveOrderCreated($orderId, $order)
    {
        $purchaseId = $order->getPayment()->getAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA)['purchaseId'];
        $this->avardaOrderRepository->save($purchaseId, $orderId);
    }

    /**
     * @param string $email
     * @param array $additionalData
     * @return string
     */
    public function checkEmail($email, $additionalData)
    {
        if (!$email) {
            $email = $additionalData['email'];
        }

        return $email;
    }

    /**
     * Validate that frontend purchaseId matches purchaseId saved in quote
     *
     * @param $quote Quote|CartInterface
     * @param $data array
     * @return true
     * @throws LocalizedException
     */
    public function validatePurchase($quote, $data): bool
    {
        $purchaseId = $quote->getPayment()->getAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA)['purchaseId'] ?? '';
        if ($purchaseId != $data['purchaseId']) {
            $this->resyncPurchase($quote, $data['purchaseId'] ?? '');
        }

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        // A Completed purchase holds a fixed amount at Avarda, so it cannot be re-synced
        // to a changed cart total (the ZeroAmount poisoning case). Renew it and refuse
        // this placement before the updateItems below would overwrite synced_total and
        // hide the mismatch.
        $state = $this->paymentDataHelper->getState($quote->getPayment());
        if ($this->purchaseStateHelper->isComplete($state)
            && $this->paymentDataHelper->syncedTotalMismatches($quote)
        ) {
            $this->quotePaymentManagement->initializePurchase($quote);
            throw new PaymentException(
                __('Your order total has changed. Please refresh the page and complete your payment again.')
            );
        }

        $this->quotePaymentManagement->updateItems($quote);

        return true;
    }

    /**
     * Accepts the paid purchase when the payment queue maps it to this quote, because a
     * concurrent initialization can leave the quote payment holding a different purchase
     *
     * @param $quote Quote|CartInterface
     * @param $purchaseId string
     * @throws LocalizedException
     */
    protected function resyncPurchase($quote, $purchaseId)
    {
        $paymentQueue = null;
        if ($purchaseId) {
            try {
                $paymentQueue = $this->paymentQueueRepository->get($purchaseId);
            } catch (NoSuchEntityException $e) {
                $paymentQueue = null;
            }
        }

        if ($paymentQueue === null
            || (int)$paymentQueue->getQuoteId() !== (int)$quote->getId()
            || $paymentQueue->getIsProcessed()
        ) {
            throw new LocalizedException(__('Validation error, please refresh page and try again'));
        }

        $payment = $quote->getPayment();
        $purchaseData = $payment->getAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA) ?: [];
        $purchaseData['purchaseId'] = $purchaseId;
        if ($paymentQueue->getJwt()) {
            $purchaseData['jwt'] = $paymentQueue->getJwt();
        }
        unset($purchaseData['renew']);
        $payment->setAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA, $purchaseData);
        // The paid purchase wins even over a newer one, so bypass the save guard
        $payment->setData('avarda_purchase_resync', true);
        $payment->save();
    }
}
