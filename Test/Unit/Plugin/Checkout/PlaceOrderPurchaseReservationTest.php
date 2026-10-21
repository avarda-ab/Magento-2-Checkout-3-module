<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Test\Unit\Plugin\Checkout;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\PaymentQueueRepositoryInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Avarda\Checkout3\Model\OrderPlacementState;
use Avarda\Checkout3\Plugin\Checkout\PlaceOrderPluginAbstract;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PlaceOrderPurchaseReservationTest extends TestCase
{
    protected LockManagerInterface|MockObject $lockManagerMock;
    protected OrderRepositoryInterface|MockObject $orderRepositoryMock;
    protected QuotePaymentManagementInterface|MockObject $quotePaymentManagementMock;
    protected PlaceOrderPluginAbstract $plugin;
    protected Quote|MockObject $quoteMock;
    protected array $purchaseIds = [];

    protected function setUp(): void
    {
        $this->lockManagerMock = $this->createMock(LockManagerInterface::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->quotePaymentManagementMock = $this->createMock(QuotePaymentManagementInterface::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($this->createMock(SearchCriteria::class));

        $paymentDataHelper = $this->createMock(PaymentData::class);
        $paymentDataHelper->method('getPurchaseId')->willReturnCallback(
            fn (Payment|MockObject $payment) => $this->purchaseIds[spl_object_id($payment)] ?? null
        );

        $this->plugin = new class (
            $this->createMock(AvardaOrderRepositoryInterface::class),
            $this->createMock(AddressFactory::class),
            $this->quotePaymentManagementMock,
            $paymentDataHelper,
            $this->createMock(PurchaseState::class),
            $this->createMock(PaymentQueueRepositoryInterface::class),
            new OrderPlacementState(),
            $this->createMock(LoggerInterface::class),
            $this->lockManagerMock,
            $this->orderRepositoryMock,
            $searchCriteriaBuilder,
        ) extends PlaceOrderPluginAbstract {
            public function place(CartInterface $quote, callable $place)
            {
                return $this->placeOrderOnce($quote, $place);
            }
        };

        $this->quoteMock = $this->createMock(Quote::class);
        $this->quoteMock->method('getId')->willReturn(11);
        $this->quoteMock->method('getPayment')->willReturn($this->createPayment('purchase-1'));
    }

    public function testRefusesWhenPurchaseLockIsBusy(): void
    {
        $this->lockManagerMock->method('lock')->willReturn(false);

        $this->expectException(PaymentException::class);
        $this->plugin->place($this->quoteMock, fn () => $this->fail('Order was placed'));
    }

    public function testReturnsOrderAlreadyPlacedForPurchase(): void
    {
        $this->lockManagerMock->method('lock')->willReturn(true);
        $this->lockManagerMock->expects($this->once())->method('unlock')->with('purchase-1');
        $this->quotePaymentManagementMock->expects($this->once())->method('setQuoteIsActive')->with(11, false);
        $this->orderRepositoryMock->method('getList')->willReturn($this->createSearchResult([
            $this->createOrder(500, Order::STATE_PENDING_PAYMENT, 'purchase-1'),
        ]));

        $result = $this->plugin->place($this->quoteMock, fn () => $this->fail('A second order was placed'));

        $this->assertSame(500, $result);
    }

    public function testPlacesOrderWhenPurchaseHasNoOrder(): void
    {
        $this->lockManagerMock->method('lock')->willReturn(true);
        $this->lockManagerMock->expects($this->once())->method('unlock')->with('purchase-1');
        $this->quotePaymentManagementMock->expects($this->never())->method('setQuoteIsActive');
        $this->orderRepositoryMock->method('getList')->willReturn($this->createSearchResult([
            $this->createOrder(499, Order::STATE_PENDING_PAYMENT, 'purchase-0'),
        ]));

        $this->assertSame(501, $this->plugin->place($this->quoteMock, fn () => 501));
    }

    public function testIgnoresCancelledOrderOfPurchase(): void
    {
        $this->lockManagerMock->method('lock')->willReturn(true);
        $this->orderRepositoryMock->method('getList')->willReturn($this->createSearchResult([
            $this->createOrder(500, Order::STATE_CANCELED, 'purchase-1'),
        ]));

        $this->assertSame(501, $this->plugin->place($this->quoteMock, fn () => 501));
    }

    protected function createPayment(string $purchaseId): Payment|MockObject
    {
        $payment = $this->createMock(Payment::class);
        $this->purchaseIds[spl_object_id($payment)] = $purchaseId;

        return $payment;
    }

    protected function createOrder(int $entityId, string $state, string $purchaseId): OrderInterface|MockObject
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn($entityId);
        $order->method('getIncrementId')->willReturn('1000' . $entityId);
        $order->method('getState')->willReturn($state);
        $order->method('getPayment')->willReturn($this->createPayment($purchaseId));

        return $order;
    }

    protected function createSearchResult(array $orders): OrderSearchResultInterface|MockObject
    {
        $result = $this->createMock(OrderSearchResultInterface::class);
        $result->method('getItems')->willReturn($orders);

        return $result;
    }
}
