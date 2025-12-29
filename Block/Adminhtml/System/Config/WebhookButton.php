<?php
namespace Okinus\Payment\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class WebhookButton extends Field
{
    protected $_template = 'Okinus_Payment::system/config/webhook-button.phtml';

    /**
     * Constructor
     *
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context, 
        array $data = []
    )
    {
        parent::__construct($context, $data);
    }

    /**
     * Render Button
     *
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }
    
    public function getSubscribeButtonHtml()
    {
        $button_label = [
                'id' => 'webhook_subscribe', 
                'label' => __('Subscribe')
            ];
        $button = $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button');
        $button->setData($button_label);
        return $button->toHtml();
    }

    public function getCheckStatusButtonHtml()
    {
        $button_label = [
                'id' => 'webhook_check_status', 
                'label' => __('Check Status')
            ];
        $button = $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button');
        $button->setData($button_label);
        return $button->toHtml();
    }

    public function getUnsubscribeButtonHtml()
    {
        $button_label = [
                'id' => 'webhook_unsubscribe', 
                'label' => __('Unsubscribe')
            ];
        $button = $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button');
        $button->setData($button_label);
        return $button->toHtml();
    }
}
