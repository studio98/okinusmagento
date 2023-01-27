<?php
namespace Okinus\Payment\Model\Config\Source;

/**
 * @api
 * @since 100.0.2
 */
class OkinusEnvironment implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => 1, 'label' => __('Sandbox')], ['value' => 0, 'label' => __('Live')]];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [0 => __('Live'), 1 => __('Sandbox')];
    }
}
