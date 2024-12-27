<?php

namespace Superb\WebapiSecurity\Plugin\Magento\Webapi\Controller;

use Superb\WebapiSecurity\Helper\Data;

class Soap
{
    protected $helper;

    public function __construct(
        Data $helper
    ) {

        $this->helper = $helper;
    }

    public function aroundDispatch(
        $soap,
        callable $dispatch,
        $request
    ) {
        if ($this->helper->isSoapApiDisabled()) {
            throw new \Exception(
                __('Request does not match any route.')
            );
        }
        return $dispatch($request);
    }
}
