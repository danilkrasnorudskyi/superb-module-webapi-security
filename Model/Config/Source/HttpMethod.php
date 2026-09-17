<?php

namespace Superb\WebapiSecurity\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class HttpMethod implements OptionSourceInterface
{
    const METHODS = ['GET', 'POST', 'PUT', 'DELETE'];

    public function toOptionArray()
    {
        $options = [];
        foreach (self::METHODS as $method) {
            $options[] = ['value' => $method, 'label' => $method];
        }
        return $options;
    }
}
