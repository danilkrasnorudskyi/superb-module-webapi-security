<?php

namespace Superb\WebapiSecurity\Plugin\Magento\GraphQl\Controller;

use Superb\WebapiSecurity\Helper\Data;

class GraphQl
{
    protected $helper;

    public function __construct(
        Data $helper
    ) {

        $this->helper = $helper;
    }

    public function aroundDispatch(
        $graphql,
        callable $dispatch,
        $request
    ) {
        if ($this->helper->isGraphqlDisabled()) {
            throw new \Exception(
                __('Request does not match any route.')
            );
        }
        return $dispatch($request);
    }
}
