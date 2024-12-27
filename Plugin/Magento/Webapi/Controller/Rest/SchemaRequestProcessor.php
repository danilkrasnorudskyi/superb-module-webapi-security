<?php

namespace Superb\WebapiSecurity\Plugin\Magento\Webapi\Controller\Rest;

use Superb\WebapiSecurity\Helper\Data;

class SchemaRequestProcessor
{
    protected $helper;
    public function __construct(
        Data $helper
    ) {

        $this->helper = $helper;
    }

    public function aroundProcess(
        $processor,
        callable $process,
        $request
    ) {
        if ($this->helper->isSchemaRequestProcessorDisabled()) {
            throw new \Magento\Framework\Webapi\Exception(
                __('Request does not match any route.'),
                0,
                \Magento\Framework\Webapi\Exception::HTTP_NOT_FOUND
            );
        }
        return $process($request);
    }
}
