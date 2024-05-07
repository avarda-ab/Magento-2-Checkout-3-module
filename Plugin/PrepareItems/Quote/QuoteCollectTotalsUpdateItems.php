<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Plugin\PrepareItems\Quote;

use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Exception;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class QuoteCollectTotalsUpdateItems
{
    protected QuotePaymentManagementInterface $quotePaymentManagement;
    protected PaymentData $paymentDataHelper;
    protected PurchaseState $purchaseStateHelper;
    protected Http $request;
    protected ConfigInterface $config;

    static bool $collectTotalsFlag = false;

    public function __construct(
        QuotePaymentManagementInterface $quotePaymentManagement,
        PaymentData $paymentDataHelper,
        PurchaseState $purchaseStateHelper,
        Http $request,
        ConfigInterface $config
    ) {
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->paymentDataHelper = $paymentDataHelper;
        $this->purchaseStateHelper = $purchaseStateHelper;
        $this->request = $request;
        $this->config = $config;
    }

    /**
     * Collect totals is triggered when quote is updated in any way, making it a
     * safe function to utilize and guarantee item updates to Avarda.
     *
     * @param CartInterface|Quote $subject
     * @param CartInterface|Quote $result
     *
     * @return CartInterface
     */
    public function afterCollectTotals(CartInterface $subject, CartInterface $result)
    {
        if (!$this->config->isActive()) {
            return $result;
        }

        $payment = $subject->getPayment();
        if (!self::$collectTotalsFlag &&
            count($subject->getAllVisibleItems()) > 0 &&
            $this->paymentDataHelper->isAvardaPayment($payment)
        ) {
            // avoid infinite loops, because the calls here might call also collectTotals
            self::$collectTotalsFlag = true;
            try {
                // Update payment status to determine if session is outdated and needs to be initialized
                $this->quotePaymentManagement->updateOnlyPaymentStatus($subject);

                $state = $this->getState($subject);
                if ($this->purchaseStateHelper->isComplete($state)) {
                    return $result;
                }
                if (($renew = $this->purchaseStateHelper->isDead($state)) === false) {
                    try {
                        $this->quotePaymentManagement->updateItems($subject);
                    } catch (WebapiException $e) {
                        $renew = true;
                    }
                }
            } catch (Exception $e) {
                $renew = true;
            }
            if ($renew) {
                $this->quotePaymentManagement->initializePurchase($subject);
            }
            self::$collectTotalsFlag = false;
        }

        return $result;
    }

    /**
     * Get state based on payment object
     *
     * @param CartInterface|Quote $subject
     *
     * @return string
     * @throws PaymentException
     *
     */
    protected function getState(CartInterface $subject)
    {
        $payment = $subject->getPayment();
        $state = $this->paymentDataHelper->getState($payment);
        if (!$this->purchaseStateHelper->isInCheckout($state)) {
            if ($this->purchaseStateHelper->isWaiting($state)) {
                throw new PaymentException(
                    __('Avarda is processing the purchase, unable to update items.')
                );
            }
        }

        return $state;
    }
}
