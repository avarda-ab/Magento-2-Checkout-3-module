<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Test\Unit\Plugin\Checkout;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Avarda\Checkout3\Api\PaymentQueueRepositoryInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Avarda\Checkout3\Model\OrderPlacementState;
use Avarda\Checkout3\Plugin\Checkout\PlaceOrderPlugin;
use Avarda\Checkout3\Test\Unit\QuoteStub;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Framework\Exception\PaymentException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PlaceOrderPluginTest extends TestCase
{
    public function testPlacementStateIsStoppedWhenPlacementIsRefused(): void
    {
        $orderPlacementState = new OrderPlacementState();

        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')
            ->with(PaymentDetailsInterface::PURCHASE_DATA)
            ->willReturn(['purchaseId' => 'purchase-1']);

        $quote = $this->getMockBuilder(QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getPayment',
                'collectTotals',
                'setTotalsCollectedFlag',
                'getId',
                'getGrandTotal',
                'isVirtual',
            ])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getId')->willReturn(11);
        $quote->method('getGrandTotal')->willReturn(21.07);
        $quote->method('isVirtual')->willReturn(true);
        $quote->method('collectTotals')
            ->willReturnCallback(function () use ($quote, $orderPlacementState) {
                $this->assertTrue($orderPlacementState->isPlacingOrder());

                return $quote;
            });

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('getActive')->with(11)->willReturn($quote);

        $paymentDataHelper = $this->getMockBuilder(PaymentData::class)
            ->onlyMethods(['getState', 'getPurchaseTotal', 'forgetPurchaseTotal'])
            ->getMock();
        $paymentDataHelper->method('getPurchaseTotal')->willReturn(23.97);

        $plugin = new PlaceOrderPlugin(
            $cartRepository,
            $this->createMock(AvardaOrderRepositoryInterface::class),
            $paymentDataHelper,
            $this->createMock(AddressFactory::class),
            $this->createMock(QuotePaymentManagementInterface::class),
            $this->createMock(PurchaseState::class),
            $this->createMock(PaymentQueueRepositoryInterface::class),
            $orderPlacementState,
            $this->createMock(LoggerInterface::class),
        );

        $paymentMethod = $this->createMock(PaymentInterface::class);
        $paymentMethod->method('getAdditionalData')->willReturn(['avarda' => '{"purchaseId":"purchase-1"}']);

        try {
            $plugin->beforeSavePaymentInformationAndPlaceOrder(
                $this->createMock(PaymentInformationManagementInterface::class),
                11,
                $paymentMethod
            );
            $this->fail('Placement was not refused');
        } catch (PaymentException $e) {
            $this->assertFalse($orderPlacementState->isPlacingOrder());
        }
    }
}
