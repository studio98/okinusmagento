<?php
namespace Okinus\Payment\Controller\Adminhtml\System;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Okinus\Payment\Helper\Webhook;

class WebhookUnsubscribe extends Action
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
        
        // Unsubscribe from webhook
        $result = $this->webhookHelper->unsubscribe();

        // Clear subscription details from config if successful
        if ($result['success']) {
            $this->configWriter->delete(Webhook::XML_PATH_WEBHOOK_SUBSCRIPTION_ID);
            $this->configWriter->delete(Webhook::XML_PATH_WEBHOOK_EXPIRES_AT);
        }

        return $jsonFactory->setData($result);
    }
}
