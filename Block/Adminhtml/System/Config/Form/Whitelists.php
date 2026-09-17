<?php

namespace Superb\WebapiSecurity\Block\Adminhtml\System\Config\Form;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Superb\WebapiSecurity\Block\Adminhtml\System\Config\Form\Renderer\Textarea;

class Whitelists extends AbstractFieldArray
{
    const NAME = 'name';
    const VALUES = 'values';

    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Whitelist');
    }

    protected function _prepareToRender()
    {
        $this->addColumn(self::NAME, [
            'label' => __('Name'),
            'class' => 'required-entry',
            'style' => 'width:100%;min-width:180px',
        ]);
        $this->addColumn(self::VALUES, [
            'label' => __('Values'),
            'renderer' => $this->getLayout()->createBlock(Textarea::class, '', ['data' => ['rows' => 6]]),
        ]);
    }
}
