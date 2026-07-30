<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Test\Unit\Model;

use Avarda\Checkout3\Api\Data\PaymentQueueInterfaceFactory;
use Avarda\Checkout3\Api\ItemManagementInterface;
use Avarda\Checkout3\Api\ItemStorageInterface;
use Avarda\Checkout3\Api\PaymentQueueRepositoryInterface;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PaymentMethod;
use Avarda\Checkout3\Helper\PurchaseState;
use Avarda\Checkout3\Model\QuoteLock;
use Avarda\Checkout3\Model\QuotePaymentManagement;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Quote\Model\Quote\Payment;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuotePaymentManagementTest extends TestCase
{
    protected QuotePaymentManagement $quotePaymentManagement;

    protected PaymentData|MockObject $paymentDataHelperMock;

    protected PurchaseState|MockObject $purchaseStateHelperMock;

    protected CommandPoolInterface|MockObject $commandPoolMock;

    protected PaymentDataObjectFactoryInterface|MockObject $paymentDataObjectFactoryMock;

    protected function setUp(): void
    {
        $this->paymentDataHelperMock = $this->createMock(PaymentData::class);
        $this->purchaseStateHelperMock = $this->createMock(PurchaseState::class);
        $this->commandPoolMock = $this->createMock(CommandPoolInterface::class);
        $this->paymentDataObjectFactoryMock = $this->createMock(PaymentDataObjectFactoryInterface::class);

        $this->quotePaymentManagement = new QuotePaymentManagement(
            $this->createMock(ItemManagementInterface::class),
            $this->createMock(ItemStorageInterface::class),
            $this->paymentDataHelperMock,
            $this->purchaseStateHelperMock,
            $this->commandPoolMock,
            $this->paymentDataObjectFactoryMock,
            $this->createMock(CartRepositoryInterface::class),
            $this->createMock(PaymentQueueRepositoryInterface::class),
            $this->createMock(PaymentQueueInterfaceFactory::class),
            $this->createMock(CartManagementInterface::class),
            $this->createMock(OrderSender::class),
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(CollectionFactory::class),
            $this->createMock(OrderResourceInterface::class),
            $this->createMock(OrderFactory::class),
            $this->createMock(AddressFactory::class),
            $this->createMock(ManagerInterface::class),
            $this->createMock(QuoteLock::class)
        );
    }

    protected function createQuote(Payment|MockObject $payment, float $grandTotal): Quote|MockObject
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->addMethods(['getGrandTotal'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getGrandTotal')->willReturn($grandTotal);

        return $quote;
    }

    public function testFinalizeOrderRejectsNonZeroOrderOnZeroAmountPurchase(): void
    {
        $this->purchaseStateHelperMock->method('isComplete')->willReturn(true);

        $payment = $this->createMock(Order\Payment::class);
        $payment->method('getMethod')->willReturn(PaymentMethod::$codes[PaymentMethod::ZERO_AMOUNT]);

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getBaseGrandTotal'])
            ->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->method('getBaseGrandTotal')->willReturn(20.00);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Order total does not match the completed zero-amount Avarda purchase.');
        $this->quotePaymentManagement->finalizeOrder($order);
    }

    public function testUpdateItemsRecordsSyncedTotal(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(PaymentData::SYNCED_TOTAL, 23.95);

        $quote = $this->createQuote($payment, 23.95);

        $this->paymentDataObjectFactoryMock->method('create')
            ->with($payment)
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));

        $command = $this->createMock(CommandInterface::class);
        $command->expects($this->once())->method('execute');
        $this->commandPoolMock->method('get')
            ->with('avarda_update_items')
            ->willReturn($command);

        $this->quotePaymentManagement->updateItems($quote);
    }

    public function testUpdateDeliveryAddressDoesNotRecordSyncedTotal(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->expects($this->never())->method('setAdditionalInformation');

        $quote = $this->createQuote($payment, 23.95);

        $this->paymentDataObjectFactoryMock->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));

        $command = $this->createMock(CommandInterface::class);
        $this->commandPoolMock->method('get')
            ->with('update_delivery_address')
            ->willReturn($command);

        $this->quotePaymentManagement->updateDeliveryAddress($quote);
    }
}
