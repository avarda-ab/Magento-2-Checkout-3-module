<?php
/**
 * Created by avarda.
 * User: juhni
 * Date: 24.8.2022
 * Time: 13.53
 */

namespace Avarda\Checkout3\Plugin\Checkout;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Helper\PaymentData;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class GuestPlaceOrderPlugin extends PlaceOrderPluginAbstract
{
    /** @var CartRepositoryInterface */
    protected $cartRepository;

    /** @var QuoteIdMaskFactory */
    protected $quoteIdMaskFactory;

    /** @var PaymentData */
    protected $paymentDataHelper;

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        AvardaOrderRepositoryInterface $avardaOrderRepository,
        PaymentData $paymentDataHelper,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->cartRepository = $cartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->paymentDataHelper = $paymentDataHelper;
        $this->orderRepository = $orderRepository;
        parent::__construct($avardaOrderRepository);
    }

    /**
     * @param $subject
     * @param $cartId
     * @param $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return void
     * @throws NoSuchEntityException
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        $subject,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    ) {
        if (isset($paymentMethod->getAdditionalData()['avarda'])) {
            $additionalData = json_decode($paymentMethod->getAdditionalData()['avarda'], true);
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            $quote = $this->cartRepository->get($quoteIdMask->getQuoteId());
            $this->setShippingAddress($quote, $additionalData);
            if ($billingAddress && !$billingAddress->getTelephone()) {
                $this->setBillingAddress($billingAddress, $additionalData);
            }
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
    public function afterSavePaymentInformationAndPlaceOrder($subject, $orderId)
    {
        $order = $this->orderRepository->get($orderId);
        if ($this->paymentDataHelper->isAvardaPayment($order->getPayment())) {
            $this->saveOrderCreated($orderId, $order);
        }
        return $orderId;
    }
}
