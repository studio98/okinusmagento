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
use Magento\Quote\Model\QuoteFactory;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

class Success extends Action implements CsrfAwareActionInterface
{
    protected $jsonFactory;
    protected $checkoutSession;
    protected $orderFactory;
    protected $quoteFactory;
    protected $pageFactory;
    protected $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        QuoteFactory $quoteFactory,
        PageFactory $pageFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->quoteFactory = $quoteFactory;
        $this->pageFactory = $pageFactory;
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

        // Session-independent return: the checkout's return URL carries the
        // cart id and a token, so we can resolve the order even when the
        // customer paid the down payment from the Okinus Hub on another
        // device (or long after their session expired).
        $cartId = $this->getRequest()->getParam('cart');
        $token = $this->getRequest()->getParam('token');
        if ($cartId && $token) {
            return $this->processTokenizedReturn($cartId, $token);
        }

        // Fallback for non-MST1 flows where Okinus redirects the parent window here before
        // placeOrder() has run. Poll briefly for the order, set the session, then redirect.
        //
        // Read the quote id straight from session storage instead of getQuote(): once the
        // webhook has converted the quote, getQuote() finds no *active* quote, clears the
        // session quote id, and we lose our only handle on the order that was just created.
        $quoteId = $this->checkoutSession->getQuoteId();

        if (!$quoteId) {
            $this->logger->error('Okinus Success: No quote in session and no last order ID set');
            return $this->_redirect('checkout/onepage/success');
        }

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

    /**
     * Resolve the return using the cart id + token from the checkout's
     * return URL instead of the browser session. If the order exists, hand
     * it to the session and show the native confirmation; if the cron
     * hasn't converted the quote yet, render the "finalizing your order"
     * page, which polls okinus/checkout/status and comes back here.
     */
    private function processTokenizedReturn($cartId, $token)
    {
        $quote = $this->quoteFactory->create()->setSharedStoreIds(['*'])->load($cartId);

        $storedToken = null;
        if ($quote->getId()) {
            $storedToken = $quote->getPayment()->getAdditionalInformation()['success_token'] ?? null;
        }

        if (!$storedToken || !hash_equals((string)$storedToken, (string)$token)) {
            $this->logger->error('Okinus Success: Invalid cart/token on return URL for cart ' . $cartId);
            return $this->_redirect('checkout/cart');
        }

        $order = $this->orderFactory->create()->loadByAttribute('quote_id', $quote->getId());
        if ($order && $order->getId()) {
            $this->checkoutSession->setLastOrderId($order->getId());
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
            $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
            $this->checkoutSession->setLastQuoteId($quote->getId());
            return $this->_redirect('checkout/onepage/success');
        }

        $this->logger->info('Okinus Success: Order not created yet for cart ' . $cartId . ', showing pending page');
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Payment received'));
        return $page;
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
