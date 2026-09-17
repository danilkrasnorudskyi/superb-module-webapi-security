<?php

namespace Superb\WebapiSecurity\Block\Adminhtml\System\Config\Form\Renderer;

use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;
use Superb\WebapiSecurity\Model\Config\Source\HttpMethod;

/**
 * HTTP method multiselect column for dynamic rows
 *
 * @method $this setName(string $name)
 * @method $this setExtraParams(string $params)
 */
class Methods extends Select
{
    protected $httpMethod;

    public function __construct(
        Context $context,
        HttpMethod $httpMethod,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->httpMethod = $httpMethod;
    }

    public function setInputName($value)
    {
        return $this->setName($value . '[]');
    }

    public function setInputId($value)
    {
        return $this->setId($value);
    }

    protected function _toHtml()
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->httpMethod->toOptionArray());
        }
        $this->setExtraParams('multiple="multiple" size="4" style="min-width:110px"');
        return parent::_toHtml();
    }
}
