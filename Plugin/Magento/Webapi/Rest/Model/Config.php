<?php

namespace Superb\WebapiSecurity\Plugin\Magento\Webapi\Rest\Model;

use Superb\WebapiSecurity\Helper\Data;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Webapi\Model\Rest\Config as RestConfig;

class Config
{
    protected $helper;

    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    public function afterGetRestRoutes(RestConfig $restConfig, array $routes, Request $request)
    {
        return $this->helper->filterRoutes($routes, $request->getHttpMethod());
    }
}
