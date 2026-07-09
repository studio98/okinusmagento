<?php
namespace Okinus\Payment\Controller\Checkout;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

class Success extends Action implements CsrfAwareActionInterface
{
    protected $jsonFactory;
    protected $checkoutSession;
    protected $orderFactory;
    protected $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
    }

    public function execute()
    {
        // If the checkout session already has an order (set by MST1/one-step checkout after
        // placeOrder() completes), there is nothing to do. Return 200 so the Okinus iframe
        // notification is acknowledged without triggering a double redirect.
        if ($this->checkoutSession->getLastRealOrderId()) {
            return $this->jsonFactory->create()->setData(['success' => true]);
        }

        // Fallback for non-MST1 flows where Okinus redirects the parent window here before
        // placeOrder() has run. Poll briefly for the order, set the session, then redirect.
        $quote = $this->checkoutSession->getQuote();

        if (!$quote || !$quote->getId()) {
            $this->logger->error('Okinus Success: No quote in session and no last order ID set');
            return $this->_redirect('checkout/onepage/success');
        }

        $quoteId = $quote->getId();
        $order = null;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $order = $this->orderFactory->create()->loadByAttribute('quote_id', $quoteId);
                if ($order && $order->getId()) {
                    break;
                }
            } catch (\Exception $e) {
                $this->logger->error('Okinus Success: Error loading order - ' . $e->getMessage());
            }
            $order = null;
            if ($attempt < 5) {
                usleep(2000000);
            }
        }

        if ($order && $order->getId()) {
            $this->logger->info('Okinus Success: Setting session for order ' . $order->getIncrementId());
            $this->checkoutSession->setLastOrderId($order->getId());
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
            $this->checkoutSession->setLastSuccessQuoteId($quoteId);
            $this->checkoutSession->setLastQuoteId($quoteId);
        } else {
            $this->logger->error('Okinus Success: Order not found for quote ' . $quoteId . ' after 5 attempts');
        }

        return $this->_redirect('checkout/onepage/success');
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
