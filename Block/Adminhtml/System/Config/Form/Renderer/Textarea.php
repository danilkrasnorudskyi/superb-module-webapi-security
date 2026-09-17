<?php

namespace Superb\WebapiSecurity\Block\Adminhtml\System\Config\Form\Renderer;

use Magento\Framework\View\Element\AbstractBlock;

/**
 * Textarea column for dynamic rows
 *
 * @method $this setName(string $name)
 * @method string|null getName()
 * @method string|null getRows()
 * @method string|null getStyle()
 * @method string|null getColumnName()
 */
class Textarea extends AbstractBlock
{
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    public function setInputId($value)
    {
        return $this->setId($value);
    }

    protected function _toHtml()
    {
        // name/id hold the "<%- _id %>" template placeholder and must stay unescaped
        return '<textarea id="' . $this->getId() . '" name="' . $this->getName()
            . '" class="admin__control-textarea" rows="' . (int)($this->getRows() ?: 3)
            . '" style="' . ($this->getStyle() ?: 'width:100%;min-width:160px')
            . '"><%- ' . $this->getColumnName() . ' %></textarea>';
    }
}
