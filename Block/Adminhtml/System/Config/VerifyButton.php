<?php
namespace Okinus\Payment\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class VerifyButton extends Field
{
    protected $_template = 'Okinus_Payment::system/config/verify-button.phtml';

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
    
    public function getButtonHtml()
    {
        $button_label = [
                'id' => 'verify_api', 
                'label' => __('Check')
            ];
        $button = $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button');
        $button->setData($button_label);
        return $button->toHtml();
    }
}
