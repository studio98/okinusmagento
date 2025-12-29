<?php
namespace Okinus\Payment\Model\Config\Source;

/**
 * @api
 * @since 100.0.2
 */
class OkinusSiteType implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => 1, 'label' => __('Home Improvement')], ['value' => 0, 'label' => __('Merchandise')]];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [0 => __('Merchandise'), 1 => __('Home Improvement')];
    }
}
