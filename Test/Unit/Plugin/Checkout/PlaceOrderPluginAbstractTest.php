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
use Avarda\Checkout3\Model\OrderPlacementState;
use Avarda\Checkout3\Plugin\Checkout\PlaceOrderPluginAbstract;
use Avarda\Checkout3\Test\Unit\AddressStub;
use Avarda\Checkout3\Test\Unit\QuoteStub;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\PaymentException;
use Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PlaceOrderPluginAbstractTest extends TestCase
{
    protected PlaceOrderPluginAbstract $plugin;

    protected QuotePaymentManagementInterface|MockObject $quotePaymentManagementMock;

    protected PaymentData|MockObject $paymentDataHelperMock;

    protected PurchaseState|MockObject $purchaseStateHelperMock;

    protected PaymentQueueRepositoryInterface|MockObject $paymentQueueRepositoryMock;

    protected OrderPlacementState $orderPlacementState;

    protected LoggerInterface|MockObject $loggerMock;

    protected Payment|MockObject $paymentMock;

    protected Quote|MockObject $quoteMock;

    protected function setUp(): void
    {
        $this->quotePaymentManagementMock = $this->createMock(QuotePaymentManagementInterface::class);
        // The amount and rate helpers run for real so the plugin is exercised with its actual comparisons
        $this->paymentDataHelperMock = $this->getMockBuilder(PaymentData::class)
            ->onlyMethods(['getState', 'syncedTotalMismatches', 'getPurchaseTotal', 'forgetPurchaseTotal'])
            ->getMock();
        $this->purchaseStateHelperMock = $this->createMock(PurchaseState::class);
        $this->paymentQueueRepositoryMock = $this->createMock(PaymentQueueRepositoryInterface::class);
        $this->orderPlacementState = new OrderPlacementState();
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->paymentMock = $this->createPayment('purchase-1');
        $this->quoteMock = $this->createQuote($this->paymentMock);

        $this->plugin = new class (
            $this->createMock(AvardaOrderRepositoryInterface::class),
            $this->createMock(AddressFactory::class),
            $this->quotePaymentManagementMock,
            $this->paymentDataHelperMock,
            $this->purchaseStateHelperMock,
            $this->paymentQueueRepositoryMock,
            $this->orderPlacementState,
            $this->loggerMock,
        ) extends PlaceOrderPluginAbstract {
        };
    }

    protected function createPayment(string $purchaseId): Payment|MockObject
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')
            ->with(PaymentDetailsInterface::PURCHASE_DATA)
            ->willReturn(['purchaseId' => $purchaseId]);

        return $payment;
    }

    /**
     * @param string[] $rateCodes
     */
    protected function createAddress(?string $shippingMethod, array $rateCodes = []): Address|MockObject
    {
        $address = $this->getMockBuilder(AddressStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingMethod', 'getAllShippingRates', 'setCollectShippingRates'])
            ->getMock();
        $address->method('getShippingMethod')->willReturn($shippingMethod);
        $address->method('getAllShippingRates')->willReturn(array_map(
            fn (string $code) => new DataObject(['code' => $code]),
            $rateCodes
        ));

        return $address;
    }

    protected function createQuote(
        Payment|MockObject $payment,
        ?Address $shippingAddress = null,
        float $grandTotal = 23.97,
        bool $isVirtual = false,
    ): Quote|MockObject {
        $quote = $this->getMockBuilder(QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getPayment',
                'collectTotals',
                'setTotalsCollectedFlag',
                'getId',
                'getGrandTotal',
                'isVirtual',
                'getShippingAddress',
            ])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getId')->willReturn(11);
        $quote->method('getGrandTotal')->willReturn($grandTotal);
        $quote->method('isVirtual')->willReturn($isVirtual);
        $quote->method('getShippingAddress')->willReturn($shippingAddress ?? $this->createAddress(null));

        return $quote;
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

    public function testRenewBranchWinsOverPurchaseTotalMismatch(): void
    {
        $this->purchaseStateHelperMock->method('isComplete')->willReturn(true);
        $this->paymentDataHelperMock->method('syncedTotalMismatches')->willReturn(true);
        $this->paymentDataHelperMock->method('getPurchaseTotal')->willReturn(23.97);
        $quote = $this->createQuote($this->paymentMock, null, 21.07);

        $this->quotePaymentManagementMock->expects($this->once())
            ->method('initializePurchase')
            ->with($quote);
        $this->quotePaymentManagementMock->expects($this->never())->method('updateItems');
        $this->loggerMock->expects($this->never())->method('critical');

        $this->expectException(PaymentException::class);
        $this->plugin->validatePurchase($quote, ['purchaseId' => 'purchase-1']);
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
        $payment = $this->createPayment('purchase-1');
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(PaymentDetailsInterface::PURCHASE_DATA, ['purchaseId' => 'other-purchase', 'jwt' => 'queue-jwt']);
        $payment->expects($this->once())->method('save');

        $quote = $this->createQuote($payment);

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

    public function testOrderPlacementStartsBeforeTotalsAreCollected(): void
    {
        $this->quoteMock->expects($this->once())
            ->method('collectTotals')
            ->willReturnCallback(function () {
                $this->assertTrue($this->orderPlacementState->isPlacingOrder());

                return $this->quoteMock;
            });

        $this->plugin->validatePurchase($this->quoteMock, ['purchaseId' => 'purchase-1']);
    }

    public function testPurchaseStatusIsRefreshedBeforeTotalsAreCollected(): void
    {
        $calls = [];
        $this->quotePaymentManagementMock->expects($this->once())
            ->method('updatePurchaseStatus')
            ->with($this->quoteMock)
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'status';
            });
        $this->quoteMock->method('collectTotals')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'collect';

                return $this->quoteMock;
            });

        $this->plugin->validatePurchase($this->quoteMock, ['purchaseId' => 'purchase-1']);

        $this->assertSame(['status', 'collect'], $calls);
    }

    public function testPurchaseTotalMismatchIsRefused(): void
    {
        $this->paymentDataHelperMock->method('getPurchaseTotal')->willReturn(23.97);
        $quote = $this->createQuote($this->paymentMock, null, 21.07);

        $this->loggerMock->expects($this->once())->method('critical');
        $this->quotePaymentManagementMock->expects($this->never())->method('updateItems');
        $this->quotePaymentManagementMock->expects($this->never())->method('initializePurchase');

        $this->expectException(PaymentException::class);
        $this->plugin->validatePurchase($quote, ['purchaseId' => 'purchase-1']);
    }

    public function testMatchingPurchaseTotalIsAccepted(): void
    {
        $this->paymentDataHelperMock->method('getPurchaseTotal')->willReturn(23.97);
        $quote = $this->createQuote($this->paymentMock, null, 23.97);

        $this->loggerMock->expects($this->never())->method('critical');
        $this->quotePaymentManagementMock->expects($this->once())
            ->method('updateItems')
            ->with($quote);

        $this->assertTrue($this->plugin->validatePurchase($quote, ['purchaseId' => 'purchase-1']));
    }

    public function testSubCentGrandTotalDifferenceIsAccepted(): void
    {
        $this->paymentDataHelperMock->method('getPurchaseTotal')->willReturn(23.97);
        $quote = $this->createQuote($this->paymentMock, null, 23.9650);

        $this->quotePaymentManagementMock->expects($this->once())->method('updateItems');

        $this->assertTrue($this->plugin->validatePurchase($quote, ['purchaseId' => 'purchase-1']));
    }

    public function testUnknownPurchaseTotalIsAccepted(): void
    {
        $this->paymentDataHelperMock->method('getPurchaseTotal')->willReturn(null);

        $this->quotePaymentManagementMock->expects($this->once())
            ->method('updateItems')
            ->with($this->quoteMock);

        $this->assertTrue($this->plugin->validatePurchase($this->quoteMock, ['purchaseId' => 'purchase-1']));
    }

    public function testFailedPurchaseStatusCallLeavesTotalUnknown(): void
    {
        $this->quotePaymentManagementMock->method('updatePurchaseStatus')
            ->willThrowException(new RuntimeException('Avarda unavailable'));

        $this->paymentDataHelperMock->expects($this->never())->method('getPurchaseTotal');
        $this->paymentDataHelperMock->expects($this->never())->method('forgetPurchaseTotal');
        $this->loggerMock->expects($this->once())->method('error');
        $this->quotePaymentManagementMock->expects($this->once())
            ->method('updateItems')
            ->with($this->quoteMock);

        $this->assertTrue($this->plugin->validatePurchase($this->quoteMock, ['purchaseId' => 'purchase-1']));
    }

    public function testPurchaseTotalIsForgottenAfterRead(): void
    {
        $calls = [];
        $this->paymentDataHelperMock->method('getPurchaseTotal')
            ->with($this->paymentMock)
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'read';

                return 23.97;
            });
        $this->paymentDataHelperMock->expects($this->once())
            ->method('forgetPurchaseTotal')
            ->with($this->paymentMock)
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'forget';
            });

        $this->assertSame(23.97, $this->plugin->fetchPurchaseTotal($this->quoteMock));
        $this->assertSame(['read', 'forget'], $calls);
    }

    public function testMissingStoredRateIsRecollected(): void
    {
        $address = $this->createAddress('markupposti_1', ['flatrate_flatrate']);
        $address->expects($this->once())->method('setCollectShippingRates')->with(true);
        $this->loggerMock->expects($this->once())->method('warning');

        $this->plugin->restoreMissingShippingRate($this->createQuote($this->paymentMock, $address));
    }

    public function testStoredRateIsKept(): void
    {
        $address = $this->createAddress('markupposti_1', ['flatrate_flatrate', 'markupposti_1']);
        $address->expects($this->never())->method('setCollectShippingRates');
        $this->loggerMock->expects($this->never())->method('warning');

        $this->plugin->restoreMissingShippingRate($this->createQuote($this->paymentMock, $address));
    }

    public function testInStorePickupRateIsNotRecollected(): void
    {
        $address = $this->createAddress(InStorePickup::DELIVERY_METHOD);
        $address->expects($this->never())->method('setCollectShippingRates');

        $this->plugin->restoreMissingShippingRate($this->createQuote($this->paymentMock, $address));
    }

    public function testQuoteWithoutShippingMethodIsLeftAlone(): void
    {
        $address = $this->createAddress(null);
        $address->expects($this->never())->method('setCollectShippingRates');

        $this->plugin->restoreMissingShippingRate($this->createQuote($this->paymentMock, $address));
    }

    public function testVirtualQuoteSkipsRateCheck(): void
    {
        $quote = $this->createQuote($this->paymentMock, null, 23.97, true);
        $quote->expects($this->never())->method('getShippingAddress');

        $this->plugin->restoreMissingShippingRate($quote);
    }

    public function testMissingStoredRateIsRecollectedBeforeTotalsAreCollected(): void
    {
        $calls = [];
        $address = $this->createAddress('markupposti_1');
        $address->method('setCollectShippingRates')
            ->willReturnCallback(function () use (&$calls, $address) {
                $calls[] = 'rates';

                return $address;
            });
        $quote = $this->createQuote($this->paymentMock, $address);
        $quote->method('collectTotals')
            ->willReturnCallback(function () use (&$calls, $quote) {
                $calls[] = 'collect';

                return $quote;
            });

        $this->plugin->validatePurchase($quote, ['purchaseId' => 'purchase-1']);

        $this->assertSame(['rates', 'collect'], $calls);
    }
}
