<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Test\Unit\Plugin\Checkout;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Avarda\Checkout3\Api\Data\PaymentQueueInterface;
use Avarda\Checkout3\Api\PaymentQueueRepositoryInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Avarda\Checkout3\Plugin\Checkout\PlaceOrderPluginAbstract;
use Avarda\Checkout3\Test\Unit\QuoteStub;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\PaymentException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlaceOrderPluginAbstractTest extends TestCase
{
    protected PlaceOrderPluginAbstract $plugin;

    protected QuotePaymentManagementInterface|MockObject $quotePaymentManagementMock;

    protected PaymentData|MockObject $paymentDataHelperMock;

    protected PurchaseState|MockObject $purchaseStateHelperMock;

    protected PaymentQueueRepositoryInterface|MockObject $paymentQueueRepositoryMock;

    protected Quote|MockObject $quoteMock;

    protected function setUp(): void
    {
        $this->quotePaymentManagementMock = $this->createMock(QuotePaymentManagementInterface::class);
        $this->paymentDataHelperMock = $this->createMock(PaymentData::class);
        $this->purchaseStateHelperMock = $this->createMock(PurchaseState::class);
        $this->paymentQueueRepositoryMock = $this->createMock(PaymentQueueRepositoryInterface::class);

        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')
            ->with(PaymentDetailsInterface::PURCHASE_DATA)
            ->willReturn(['purchaseId' => 'purchase-1']);

        $this->quoteMock = $this->getMockBuilder(QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'collectTotals', 'setTotalsCollectedFlag'])
            ->getMock();
        $this->quoteMock->method('getPayment')->willReturn($payment);

        $this->plugin = new class (
            $this->createMock(AvardaOrderRepositoryInterface::class),
            $this->createMock(AddressFactory::class),
            $this->quotePaymentManagementMock,
            $this->paymentDataHelperMock,
            $this->purchaseStateHelperMock,
            $this->paymentQueueRepositoryMock
        ) extends PlaceOrderPluginAbstract {
        };
    }

    public function testMismatchingPurchaseIdIsRejected(): void
    {
        $this->quotePaymentManagementMock->expects($this->never())->method('updateItems');

        $this->expectException(LocalizedException::class);
        $this->plugin->validatePurchase($this->quoteMock, ['purchaseId' => 'other-purchase']);
    }

    public function testCompletedPurchaseWithChangedTotalIsRenewedAndRejected(): void
    {
        $this->purchaseStateHelperMock->method('isComplete')->willReturn(true);
        $this->paymentDataHelperMock->method('syncedTotalMismatches')->willReturn(true);

        $this->quotePaymentManagementMock->expects($this->once())
            ->method('initializePurchase')
            ->with($this->quoteMock);
        $this->quotePaymentManagementMock->expects($this->never())->method('updateItems');

        $this->expectException(PaymentException::class);
        $this->plugin->validatePurchase($this->quoteMock, ['purchaseId' => 'purchase-1']);
    }

    public function testCompletedPurchaseWithMatchingTotalIsAccepted(): void
    {
        $this->purchaseStateHelperMock->method('isComplete')->willReturn(true);
        $this->paymentDataHelperMock->method('syncedTotalMismatches')->willReturn(false);

        $this->quotePaymentManagementMock->expects($this->never())->method('initializePurchase');
        $this->quotePaymentManagementMock->expects($this->once())
            ->method('updateItems')
            ->with($this->quoteMock);

        $this->assertTrue($this->plugin->validatePurchase($this->quoteMock, ['purchaseId' => 'purchase-1']));
    }

    public function testNonCompletedPurchaseOnlyUpdatesItems(): void
    {
        $this->purchaseStateHelperMock->method('isComplete')->willReturn(false);

        $this->paymentDataHelperMock->expects($this->never())->method('syncedTotalMismatches');
        $this->quotePaymentManagementMock->expects($this->once())
            ->method('updateItems')
            ->with($this->quoteMock);

        $this->assertTrue($this->plugin->validatePurchase($this->quoteMock, ['purchaseId' => 'purchase-1']));
    }

    public function testMismatchingPurchaseIdMappedToQuoteInQueueIsResynced(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')
            ->with(PaymentDetailsInterface::PURCHASE_DATA)
            ->willReturn(['purchaseId' => 'purchase-1']);
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(PaymentDetailsInterface::PURCHASE_DATA, ['purchaseId' => 'other-purchase', 'jwt' => 'queue-jwt']);
        $payment->expects($this->once())->method('save');

        $quote = $this->getMockBuilder(QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'collectTotals', 'getId', 'setTotalsCollectedFlag'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getId')->willReturn(11);

        $paymentQueue = $this->createMock(PaymentQueueInterface::class);
        $paymentQueue->method('getQuoteId')->willReturn(11);
        $paymentQueue->method('getIsProcessed')->willReturn(false);
        $paymentQueue->method('getJwt')->willReturn('queue-jwt');
        $this->paymentQueueRepositoryMock->method('get')
            ->with('other-purchase')
            ->willReturn($paymentQueue);

        $this->quotePaymentManagementMock->expects($this->once())
            ->method('updateItems')
            ->with($quote);

        $this->assertTrue($this->plugin->validatePurchase($quote, ['purchaseId' => 'other-purchase']));
    }

    public function testTotalsAreRecollectedBeforeValidation(): void
    {
        $this->purchaseStateHelperMock->method('isComplete')->willReturn(false);

        $this->quoteMock->expects($this->once())->method('setTotalsCollectedFlag')->with(false);
        $this->quoteMock->expects($this->once())->method('collectTotals');

        $this->plugin->validatePurchase($this->quoteMock, ['purchaseId' => 'purchase-1']);
    }
}
