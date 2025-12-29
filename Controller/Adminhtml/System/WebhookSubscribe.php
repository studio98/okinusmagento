<?php
namespace Okinus\Payment\Controller\Adminhtml\System;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Okinus\Payment\Helper\Webhook;

class WebhookSubscribe extends Action
{
    protected $jsonFactory;
    protected $webhookHelper;
    protected $configWriter;

    /**
     * Constructor
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        Webhook $webhookHelper,
        WriterInterface $configWriter
    )
    {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->webhookHelper = $webhookHelper;
        $this->configWriter = $configWriter;
    }

    public function execute()
    {
        $jsonFactory = $this->jsonFactory->create();
        $apiKey = $this->getRequest()->getParam('api_key', null);
        $storeId = $this->getRequest()->getParam('store_id', null);

        if (!$apiKey || !$storeId) {
            return $jsonFactory->setData([
                'success' => false,
                'message' => 'API Key and Store ID are required'
            ]);
        }

        // Subscribe to webhook
        $result = $this->webhookHelper->subscribe($apiKey, $storeId);

        // Save subscription details to config if successful
        if ($result['success']) {
            if (isset($result['subscription_id'])) {
                $this->configWriter->save(
                    Webhook::XML_PATH_WEBHOOK_SUBSCRIPTION_ID,
                    $result['subscription_id']
                );
            }
            
            if (isset($result['expires_at'])) {
                $this->configWriter->save(
                    Webhook::XML_PATH_WEBHOOK_EXPIRES_AT,
                    $result['expires_at']
                );
            }
        }

        return $jsonFactory->setData($result);
    }
}
