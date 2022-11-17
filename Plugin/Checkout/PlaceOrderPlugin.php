<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Plugin\Checkout;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Helper\PaymentData;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote\AddressFactory;

class PlaceOrderPlugin extends PlaceOrderPluginAbstract
{
    /** @var CartRepositoryInterface */
    protected $cartRepository;

    /** @var PaymentData */
    protected $paymentDataHelper;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        AvardaOrderRepositoryInterface $avardaOrderRepository,
        PaymentData $paymentDataHelper,
        AddressFactory $addressFactory
    ) {
        $this->cartRepository = $cartRepository;
        $this->paymentDataHelper = $paymentDataHelper;
        parent::__construct($avardaOrderRepository, $addressFactory);
    }

    /**
     * @param $subject
     * @param $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return void|array
     * @throws NoSuchEntityException
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    ) {
        if (isset($paymentMethod->getAdditionalData()['avarda'])) {
            $additionalData = json_decode($paymentMethod->getAdditionalData()['avarda'] ?? '', true);
            $quote = $this->cartRepository->getActive($cartId);
            $this->setShippingAddress($quote, $additionalData);
            $billingAddress = $this->setBillingAddress($billingAddress, $additionalData);
            return [$cartId, $paymentMethod, $billingAddress];
        }
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
        $quote = $this->cartRepository->get($cartId);
        if ($this->paymentDataHelper->isAvardaPayment($quote->getPayment())) {
            $this->saveOrderCreated($orderId, $quote);
        }
        return $orderId;
    }
}
