<?php
namespace Okinus\Payment\Model\Config\Source;

/**
 * @api
 * @since 100.0.2
 */
class OkinusBranding implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => 1, 'label' => __('Breeze Leasing')], ['value' => 0, 'label' => __('Okinus')]];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [0 => __('Okinus'), 1 => __('Breeze Leasing')];
    }
}
