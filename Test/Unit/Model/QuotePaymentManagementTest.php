<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Test\Unit\Model;

use Avarda\Checkout3\Api\Data\PaymentQueueInterface;
use Avarda\Checkout3\Api\Data\PaymentQueueInterfaceFactory;
use Avarda\Checkout3\Api\ItemManagementInterface;
use Avarda\Checkout3\Api\ItemStorageInterface;
use Avarda\Checkout3\Api\OrphanPurchaseResolverInterface;
use Avarda\Checkout3\Api\PaymentQueueRepositoryInterface;
use Avarda\Checkout3\Model\AlternativeApi;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PaymentMethod;
use Avarda\Checkout3\Helper\PurchaseState;
use Avarda\Checkout3\Model\QuoteLock;
use Avarda\Checkout3\Model\QuotePaymentManagement;
use Avarda\Checkout3\Test\Unit\QuoteStub;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
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
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class QuotePaymentManagementTest extends TestCase
{
    protected QuotePaymentManagement $quotePaymentManagement;

    protected PaymentData|MockObject $paymentDataHelperMock;

    protected PurchaseState|MockObject $purchaseStateHelperMock;

    protected CommandPoolInterface|MockObject $commandPoolMock;

    protected PaymentDataObjectFactoryInterface|MockObject $paymentDataObjectFactoryMock;

    protected CartRepositoryInterface|MockObject $quoteRepositoryMock;

    protected PaymentQueueInterfaceFactory|MockObject $paymentQueueFactoryMock;

    protected array $constructorArguments;

    public function setUp(): void
    {
        $this->paymentDataHelperMock = $this->createMock(PaymentData::class);
        $this->purchaseStateHelperMock = $this->createMock(PurchaseState::class);
        $this->commandPoolMock = $this->createMock(CommandPoolInterface::class);
        $this->paymentDataObjectFactoryMock = $this->createMock(PaymentDataObjectFactoryInterface::class);
        $this->quoteRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->paymentQueueFactoryMock = $this->createMock(PaymentQueueInterfaceFactory::class);

        $this->constructorArguments = [
            $this->createMock(ItemManagementInterface::class),
            $this->createMock(ItemStorageInterface::class),
            $this->paymentDataHelperMock,
            $this->purchaseStateHelperMock,
            $this->createMock(AlternativeApi::class),
            $this->commandPoolMock,
            $this->paymentDataObjectFactoryMock,
            $this->quoteRepositoryMock,
            $this->createMock(PaymentQueueRepositoryInterface::class),
            $this->paymentQueueFactoryMock,
            $this->createMock(CartManagementInterface::class),
            $this->createMock(OrderSender::class),
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(CollectionFactory::class),
            $this->createMock(OrderResourceInterface::class),
            $this->createMock(OrderFactory::class),
            $this->createMock(AddressFactory::class),
            $this->createMock(ManagerInterface::class),
            $this->createMock(QuoteLock::class),
            $this->createMock(OrphanPurchaseResolverInterface::class),
            $this->createMock(LoggerInterface::class),
        ];
        $this->quotePaymentManagement = new QuotePaymentManagement(...$this->constructorArguments);
    }

    /**
     * @param string[] $methods
     */
    public function createManagementMock(array $methods): QuotePaymentManagement|MockObject
    {
        return $this->getMockBuilder(QuotePaymentManagement::class)
            ->setConstructorArgs($this->constructorArguments)
            ->onlyMethods($methods)
            ->getMock();
    }

    public function createQuote(Payment|MockObject $payment, float $grandTotal): Quote|MockObject
    {
        $quote = $this->getMockBuilder(QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getGrandTotal'])
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

    public function testUpdatePurchaseStatusDoesNotRecordSyncedTotal(): void
    {
        $this->paymentDataHelperMock->method('isAvardaPayment')->willReturn(true);

        $payment = $this->createMock(Payment::class);
        $payment->expects($this->never())->method('setAdditionalInformation');

        $quote = $this->createQuote($payment, 23.95);

        $this->paymentDataObjectFactoryMock->method('create')
            ->with($payment)
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));

        $command = $this->createMock(CommandInterface::class);
        $command->expects($this->once())->method('execute');
        $this->commandPoolMock->method('get')
            ->with('avarda_get_purchase_status')
            ->willReturn($command);

        $this->quotePaymentManagement->updatePurchaseStatus($quote);
    }

    public function testUpdatePurchaseStatusRejectsQuoteWithoutAvardaPayment(): void
    {
        $this->paymentDataHelperMock->method('isAvardaPayment')->willReturn(false);
        $this->commandPoolMock->expects($this->never())->method('get');

        $this->expectException(PaymentException::class);
        $this->quotePaymentManagement->updatePurchaseStatus(
            $this->createQuote($this->createMock(Payment::class), 23.95)
        );
    }

    public function createPurchaseQuote(Payment|MockObject $payment): Quote|MockObject
    {
        $quote = $this->getMockBuilder(QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getPayment',
                'getGrandTotal',
                'getId',
                'getStoreId',
                'getIsActive',
                'collectTotals',
                'reserveOrderId',
                'dataHasChangedFor',
                'getReservedOrderId',
                'getResource',
                'setBillingAddress',
                'save',
            ])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getGrandTotal')->willReturn(23.95);
        $quote->method('getId')->willReturn(11);
        $quote->method('getStoreId')->willReturn(3);
        $quote->method('getIsActive')->willReturn(true);
        $quote->method('getReservedOrderId')->willReturn('000000123');
        $this->quoteRepositoryMock->method('get')->with(11)->willReturn($quote);

        return $quote;
    }

    public function prepareExistingPurchase(string $state): Quote|MockObject
    {
        $quote = $this->createPurchaseQuote($this->createMock(Payment::class));
        $this->paymentDataHelperMock->method('getPurchaseData')->willReturn([
            'purchaseId' => 'purchase-1',
            'jwt' => 'jwt-1',
            'storeId' => 3,
            'useAltApi' => false,
        ]);
        $this->paymentDataHelperMock->method('isAvardaPayment')->willReturn(true);
        $this->paymentDataHelperMock->method('getState')->willReturn($state);
        $this->commandPoolMock->method('get')->willReturn($this->createMock(CommandInterface::class));

        return $quote;
    }

    public function prepareInitialization(Payment|MockObject $payment): void
    {
        $this->paymentDataHelperMock->method('getPurchaseData')->willReturn([
            'purchaseId' => 'purchase-2',
            'jwt' => 'jwt-2',
            'expiredUtc' => '2026-11-23T12:00:00Z',
        ]);
        $this->paymentDataObjectFactoryMock->method('create')
            ->with($payment)
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->paymentQueueFactoryMock->method('create')
            ->willReturn($this->createMock(PaymentQueueInterface::class));
    }

    public function testCompletedAndDeadPurchasesAreRenewed(): void
    {
        $this->purchaseStateHelperMock->method('isComplete')
            ->willReturnCallback(fn (string $state) => $state === PurchaseState::COMPLETED);
        $this->purchaseStateHelperMock->method('isDead')
            ->willReturnCallback(fn (string $state) => $state === PurchaseState::TIMED_OUT);
        $quote = $this->createPurchaseQuote($this->createMock(Payment::class));

        $this->assertTrue($this->quotePaymentManagement->isRenewRequired($quote, [], PurchaseState::COMPLETED));
        $this->assertTrue($this->quotePaymentManagement->isRenewRequired($quote, [], PurchaseState::TIMED_OUT));
        $this->assertFalse($this->quotePaymentManagement->isRenewRequired($quote, [], PurchaseState::PERSONAL_INFO));
    }

    public function testPurchaseInCheckoutIsKept(): void
    {
        $this->prepareExistingPurchase(PurchaseState::PERSONAL_INFO);

        $purchaseData = $this->quotePaymentManagement->getPurchaseData(11);

        $this->assertSame('purchase-1', $purchaseData['purchaseId']);
    }

    public function testPurchaseIsRenewedWhenRenewIsRequired(): void
    {
        $quote = $this->prepareExistingPurchase(PurchaseState::PERSONAL_INFO);
        $management = $this->createManagementMock(['isRenewRequired', 'doInitializePurchase']);
        $management->expects($this->once())
            ->method('isRenewRequired')
            ->with($quote, $this->arrayHasKey('purchaseId'), PurchaseState::PERSONAL_INFO)
            ->willReturn(true);
        $management->expects($this->once())
            ->method('doInitializePurchase')
            ->with($quote)
            ->willReturn(['purchaseId' => 'purchase-2']);

        $this->assertSame(['purchaseId' => 'purchase-2'], $management->getPurchaseData(11));
    }

    public function testInitializedPurchaseSavesOnlyThePaymentAndTheReservedOrderId(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->expects($this->once())->method('save');
        $quote = $this->createPurchaseQuote($payment);
        $quote->method('dataHasChangedFor')->with('reserved_order_id')->willReturn(true);
        $quote->expects($this->never())->method('save');
        $this->prepareInitialization($payment);
        $this->commandPoolMock->method('get')
            ->with('avarda_initialize_payment')
            ->willReturn($this->createMock(CommandInterface::class));

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('update')
            ->with('quote', ['reserved_order_id' => '000000123'], ['entity_id = ?' => 11]);
        $resource = $this->createMock(QuoteResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('quote');
        $resource->method('getIdFieldName')->willReturn('entity_id');
        $quote->method('getResource')->willReturn($resource);

        $purchaseData = $this->quotePaymentManagement->initializePurchase($quote);

        $this->assertSame('purchase-2', $purchaseData['purchaseId']);
        $this->assertSame(3, $purchaseData['storeId']);
    }

    public function testUnchangedReservedOrderIdIsNotWritten(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->expects($this->once())->method('save');
        $quote = $this->createPurchaseQuote($payment);
        $quote->method('dataHasChangedFor')->willReturn(false);
        $quote->expects($this->never())->method('getResource');

        $this->quotePaymentManagement->savePurchase($quote);
    }

    public function testFailedInitializationIsRetriedWithClearedAddressAndSavesTheQuote(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->expects($this->never())->method('save');
        $quote = $this->createPurchaseQuote($payment);
        $quote->expects($this->once())->method('save');
        $this->prepareInitialization($payment);
        $failure = new RuntimeException('Invalid address');
        $failingCommand = $this->createMock(CommandInterface::class);
        $failingCommand->method('execute')->willThrowException($failure);
        $this->commandPoolMock->method('get')->willReturn($failingCommand);

        $management = $this->createManagementMock(['initializeWithClearedAddress']);
        $management->expects($this->once())->method('initializeWithClearedAddress')->with($quote, $failure);

        $this->assertSame('purchase-2', $management->initializePurchase($quote)['purchaseId']);
    }

    public function testPluggedInitializationFailureKeepsTheAddress(): void
    {
        $quote = $this->createPurchaseQuote($this->createMock(Payment::class));
        $quote->expects($this->never())->method('setBillingAddress');
        $quote->expects($this->never())->method('save');
        $failingCommand = $this->createMock(CommandInterface::class);
        $failingCommand->method('execute')->willThrowException(new RuntimeException('Invalid address'));
        $this->commandPoolMock->method('get')->willReturn($failingCommand);

        $management = $this->createManagementMock(['initializeWithClearedAddress']);
        $management->method('initializeWithClearedAddress')
            ->willThrowException(new LocalizedException(__('Please check your address.')));

        $this->expectException(LocalizedException::class);
        $management->initializePurchase($quote);
    }
}
