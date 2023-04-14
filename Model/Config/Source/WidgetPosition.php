<?php
namespace Okinus\Payment\Model\Config\Source;

/**
 * @api
 * @since 100.0.2
 */
class WidgetPosition implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => 1, 'label' => __('Bottom Left')], ['value' => 0, 'label' => __('Bottom Right')]];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [0 => __('Bottom Right'), 1 => __('Botttom Left')];
    }
}
