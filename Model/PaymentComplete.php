<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\PaymentCompleteInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use \Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Psr\Log\LoggerInterface;

class PaymentComplete implements PaymentCompleteInterface
{
    /** @var QuotePaymentManagementInterface */
    protected $quotePaymentManagement;

    /** @var RequestInterface */
    protected $avardaOrderRepository;

    /** @var RedirectFactory */
    protected $redirectFactory;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(
        QuotePaymentManagementInterface $quotePaymentManagement,
        AvardaOrderRepositoryInterface $avardaOrderRepository,
        RedirectFactory $redirectFactory,
        LoggerInterface $logger
    ) {
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->avardaOrderRepository = $avardaOrderRepository;
        $this->redirectFactory = $redirectFactory;
        $this->logger = $logger;
    }

    /**
     * Handle serverside order complete handling
     */
    public function execute($purchaseId)
    {
        try {
            try {
                $this->quotePaymentManagement->getQuoteIdByPurchaseId($purchaseId);
                try {
                    $this->avardaOrderRepository->save($purchaseId);
                } catch (AlreadyExistsException $alreadyExistsException) {
                    $this->logger->warning("Order with purchase $purchaseId already saved");

                    return "Order already saved";
                }

                $quoteId = $this->quotePaymentManagement->getQuoteIdByPurchaseId($purchaseId);
                $this->quotePaymentManagement->updatePaymentStatus($quoteId);
                $this->quotePaymentManagement->placeOrder($quoteId);

                return "OK";
            } catch (PaymentException $e) {
                $message = $e->getMessage();
                $this->logger->error($e);
            } catch (\Exception $e) {
                // log stacktrace to get why saving fails
                $this->logger->error($e, $e->getTrace());
                $message = __('Failed to save Avarda order. Please try again later.');
            }

            $this->logger->warning($message);
        } catch (NoSuchEntityException $noSuchEntityException) {
            // Order is already saved
        }
    }
}
