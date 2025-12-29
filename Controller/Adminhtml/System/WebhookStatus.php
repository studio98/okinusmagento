<?php
namespace Okinus\Payment\Controller\Adminhtml\System;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\Action;
use Okinus\Payment\Helper\Webhook;

class WebhookStatus extends Action
{
    protected $jsonFactory;
    protected $webhookHelper;

    /**
     * Constructor
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        Webhook $webhookHelper
    )
    {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->webhookHelper = $webhookHelper;
    }

    public function execute()
    {
        $jsonFactory = $this->jsonFactory->create();
        
        // Check webhook status
        $result = $this->webhookHelper->checkStatus();

        return $jsonFactory->setData($result);
    }
}
