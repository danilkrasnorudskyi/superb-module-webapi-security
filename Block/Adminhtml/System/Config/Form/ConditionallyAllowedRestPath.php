<?php

namespace Superb\WebapiSecurity\Block\Adminhtml\System\Config\Form;

use Superb\WebapiSecurity\Helper\Data;

class ConditionallyAllowedRestPath extends AbstractRestPath
{
    protected function _prepareToRender()
    {
        parent::_prepareToRender();
        $this->addColumn(Data::IP_CONDITION, [
            'label' => __('IP / CIDR'),
            'renderer' => $this->getTextareaRenderer(),
        ]);
        $this->addColumn(Data::USER_AGENT_CONDITION, [
            'label' => __('User Agent'),
            'renderer' => $this->getTextareaRenderer(),
        ]);
    }
}
