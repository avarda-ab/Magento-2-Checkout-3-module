<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Test\Unit\Plugin\PrepareItems\Quote;

use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Gateway\Config\Config;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Avarda\Checkout3\Model\OrderPlacementState;
use Avarda\Checkout3\Model\QuoteLock;
use Avarda\Checkout3\Plugin\PrepareItems\Quote\QuoteCollectTotalsUpdateItems;
use Avarda\Checkout3\Test\Unit\QuoteStub;
use Magento\Framework\App\Request\Http;
use Magento\InventoryInStorePickupQuote\Model\ResourceModel\GetPickupLocationCodeByQuoteAddressId;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class QuoteCollectTotalsUpdateItemsTest extends TestCase
{
    protected QuoteCollectTotalsUpdateItems $plugin;

    protected QuotePaymentManagementInterface|MockObject $quotePaymentManagementMock;

    protected OrderPlacementState $orderPlacementState;

    protected Payment|MockObject $paymentMock;

    protected function setUp(): void
    {
        $this->quotePaymentManagementMock = $this->createMock(QuotePaymentManagementInterface::class);
        $this->orderPlacementState = new OrderPlacementState();
        $this->paymentMock = $this->createMock(Payment::class);

        $paymentDataHelper = $this->createMock(PaymentData::class);
        $paymentDataHelper->method('isAvardaPayment')->willReturn(true);

        $purchaseStateHelper = $this->createMock(PurchaseState::class);
        $purchaseStateHelper->method('isInCheckout')->willReturn(true);
        $purchaseStateHelper->method('isComplete')->willReturn(false);
        $purchaseStateHelper->method('isDead')->willReturn(false);

        $config = $this->createMock(Config::class);
        $config->method('isActive')->willReturn(true);

        $this->plugin = new QuoteCollectTotalsUpdateItems(
            $this->quotePaymentManagementMock,
            $paymentDataHelper,
            $purchaseStateHelper,
            $this->createMock(Http::class),
            $config,
            $this->createMock(GetPickupLocationCodeByQuoteAddressId::class),
            $this->createMock(QuoteLock::class),
            $this->orderPlacementState,
            $this->createMock(LoggerInterface::class),
        );
    }

    protected function createQuote(mixed $isActive): Quote|MockObject
    {
        $quote = $this->getMockBuilder(QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIsActive', 'getPayment', 'getItemsCount', 'getItems', 'getShippingAddress', 'getId'])
            ->getMock();
        $quote->method('getIsActive')->willReturn($isActive);
        $quote->method('getPayment')->willReturn($this->paymentMock);
        $quote->method('getItemsCount')->willReturn(1);
        $quote->method('getItems')->willReturn([$this->createMock(Item::class)]);
        $quote->method('getShippingAddress')->willReturn(null);
        $quote->method('getId')->willReturn(11);

        return $quote;
    }

    /**
     * @dataProvider activeFlagProvider
     */
    #[DataProvider('activeFlagProvider')]
    public function testActiveQuoteIsSynced(mixed $isActive): void
    {
        $quote = $this->createQuote($isActive);

        $this->quotePaymentManagementMock->expects($this->once())
            ->method('updateOnlyPaymentStatus')
            ->with($quote);
        $this->quotePaymentManagementMock->expects($this->once())
            ->method('updateItems')
            ->with($quote);

        $this->assertSame($quote, $this->plugin->afterCollectTotals($quote, $quote));
    }

    public static function activeFlagProvider(): array
    {
        return [
            'integer' => [1],
            'string' => ['1'],
            'boolean' => [true],
        ];
    }

    /**
     * @dataProvider inactiveFlagProvider
     */
    #[DataProvider('inactiveFlagProvider')]
    public function testInactiveQuoteIsNotSynced(mixed $isActive): void
    {
        $quote = $this->createQuote($isActive);

        $this->quotePaymentManagementMock->expects($this->never())->method('updateOnlyPaymentStatus');
        $this->quotePaymentManagementMock->expects($this->never())->method('updateItems');
        $this->paymentMock->expects($this->never())->method('setAdditionalInformation');

        $this->assertSame($quote, $this->plugin->afterCollectTotals($quote, $quote));
    }

    public static function inactiveFlagProvider(): array
    {
        return [
            'integer' => [0],
            'string' => ['0'],
            'boolean' => [false],
        ];
    }

    public function testQuoteWithoutActiveFlagIsSynced(): void
    {
        $quote = $this->createQuote(null);

        $this->quotePaymentManagementMock->expects($this->once())->method('updateItems');

        $this->plugin->afterCollectTotals($quote, $quote);
    }

    public function testQuoteIsNotSyncedWhileOrderIsBeingPlaced(): void
    {
        $quote = $this->createQuote(1);
        $this->orderPlacementState->start();

        $this->quotePaymentManagementMock->expects($this->never())->method('updateOnlyPaymentStatus');
        $this->quotePaymentManagementMock->expects($this->never())->method('updateItems');

        $this->plugin->afterCollectTotals($quote, $quote);
    }
}
