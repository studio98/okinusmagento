<?php
namespace Okinus\Payment\Controller\Webhook;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Okinus\Payment\Helper\Webhook;
use Psr\Log\LoggerInterface;

class Receive extends Action implements CsrfAwareActionInterface
{
    protected $jsonFactory;
    protected $webhookHelper;
    protected $logger;

    /**
     * Constructor
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        Webhook $webhookHelper,
        LoggerInterface $logger
    )
    {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->webhookHelper = $webhookHelper;
        $this->logger = $logger;
    }

    /**
     * Handle webhook notification
     */
    public function execute()
    {
        $jsonFactory = $this->jsonFactory->create();
        
        try {
            // Get raw POST data
            $rawBody = $this->getRequest()->getContent();
            $webhookData = json_decode($rawBody, true);

            // Log received webhook
            $this->logger->info('Webhook received: ' . $rawBody);

            if (!$webhookData) {
                $this->logger->error('Webhook: Invalid JSON data received');
                return $jsonFactory->setData([
                    'success' => false,
                    'message' => 'Invalid JSON data'
                ])->setHttpResponseCode(400);
            }

            // Process the webhook
            $result = $this->webhookHelper->processWebhook($webhookData);

            $httpCode = $result['success'] ? 200 : 400;
            
            return $jsonFactory->setData($result)->setHttpResponseCode($httpCode);

        } catch (\Exception $e) {
            $this->logger->error('Webhook receiver error: ' . $e->getMessage());
            return $jsonFactory->setData([
                'success' => false,
                'message' => 'Internal server error'
            ])->setHttpResponseCode(500);
        }
    }

    /**
     * Create CSRF validation exception
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Bypass CSRF validation for webhook
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
