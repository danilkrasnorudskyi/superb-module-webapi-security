<?php

namespace Superb\WebapiSecurity\Block\Adminhtml\System\Config\Form;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Superb\WebapiSecurity\Block\Adminhtml\System\Config\Form\Renderer\Methods;
use Superb\WebapiSecurity\Block\Adminhtml\System\Config\Form\Renderer\Textarea;

abstract class AbstractRestPath extends AbstractFieldArray
{
    const PATH = 'path';
    const METHODS = 'methods';

    protected $methodsRenderer;

    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Path');
    }

    protected function _prepareToRender()
    {
        $this->addColumn(self::PATH, [
            'label' => __('Path'),
            'class' => 'required-entry',
            'style' => 'width:100%;min-width:180px',
        ]);
        $this->addColumn(self::METHODS, [
            'label' => __('Methods'),
            'renderer' => $this->getMethodsRenderer(),
        ]);
    }

    protected function getMethodsRenderer()
    {
        if (!$this->methodsRenderer) {
            /** @var Methods $renderer */
            $renderer = $this->getLayout()->createBlock(
                Methods::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $this->methodsRenderer = $renderer->setClass('required-entry admin__control-multiselect');
        }
        return $this->methodsRenderer;
    }

    protected function getTextareaRenderer($rows = 3)
    {
        return $this->getLayout()->createBlock(Textarea::class, '', ['data' => ['rows' => $rows]]);
    }

    protected function _prepareArrayRow(DataObject $row)
    {
        $options = [];
        foreach ((array)$row->getData(self::METHODS) as $method) {
            $options['option_' . $this->getMethodsRenderer()->calcOptionHash($method)] = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $options);
    }
}
