<?php
namespace Okinus\Payment\Controller\Checkout;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

/**
 * Poll endpoint for the pending page: reports whether the quote identified
 * by cart id + token has been converted to an order yet.
 */
class Status extends Action
{
    protected $jsonFactory;
    protected $quoteFactory;
    protected $orderFactory;
    protected $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        QuoteFactory $quoteFactory,
        OrderFactory $orderFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->quoteFactory = $quoteFactory;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        $cartId = $this->getRequest()->getParam('cart');
        $token = $this->getRequest()->getParam('token');

        if (!$cartId || !$token) {
            return $result->setData(['ready' => false]);
        }

        $quote = $this->quoteFactory->create()->setSharedStoreIds(['*'])->load($cartId);

        $storedToken = null;
        if ($quote->getId()) {
            $storedToken = $quote->getPayment()->getAdditionalInformation()['success_token'] ?? null;
        }

        if (!$storedToken || !hash_equals((string)$storedToken, (string)$token)) {
            return $result->setData(['ready' => false]);
        }

        $order = $this->orderFactory->create()->loadByAttribute('quote_id', $quote->getId());
        if ($order && $order->getId()) {
            return $result->setData([
                'ready' => true,
                'order_id' => $order->getIncrementId()
            ]);
        }

        return $result->setData(['ready' => false]);
    }
}
