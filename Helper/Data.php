<?php

namespace Superb\WebapiSecurity\Helper;

use Magento\Framework\HTTP\Header;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

class Data
{
    const SCHEMA_REQUEST_PROCESSOR_DISABLED = 'superb/webapi_security/schema_request_processor_disabled';
    const SOAP_API_DISABLED = 'superb/webapi_security/soap_api_disabled';
    const GRAPHQL_DISABLED = 'superb/webapi_security/graphql_disabled';
    const REST_PATH_FILTER_ENABLED = 'superb/webapi_security/rest_path_filter_enabled';
    const ALLOWED_REST_PATH = 'superb/webapi_security/allowed_rest_path';
    const CONDITIONALLY_ALLOWED_REST_PATH = 'superb/webapi_security/conditionally_allowed_rest_path';
    const WHITELISTS = 'superb/webapi_security/whitelists';
    const IP_CONDITION = 'ip';
    const USER_AGENT_CONDITION = 'user_agent';

    protected $deploymentConfig;
    protected $remoteAddress;
    protected $httpHeader;

    protected $allowedRestPath;
    protected $whitelists;
    protected $conditinallyAllowedPath;
    protected $allowedConiditions = [self::IP_CONDITION, self::USER_AGENT_CONDITION];

    public function __construct(
        DeploymentConfig $deploymentConfig,
        RemoteAddress $remoteAddress,
        Header $httpHeader
    ) {
        $this->deploymentConfig = $deploymentConfig;
        $this->remoteAddress = $remoteAddress;
        $this->httpHeader = $httpHeader;
    }

    public function isSchemaRequestProcessorDisabled()
    {
        return $this->deploymentConfig->get(self::SCHEMA_REQUEST_PROCESSOR_DISABLED);
    }

    public function isSoapApiDisabled()
    {
        return $this->deploymentConfig->get(self::SOAP_API_DISABLED);

    }

    public function isGraphqlDisabled()
    {
        return $this->deploymentConfig->get(self::GRAPHQL_DISABLED);
    }

    public function filterRoutes($routes, $httpMethod)
    {
        if (!$this->isRestPathFilterEnabled()) {
            return $routes;
        }
        /** @var \Magento\Webapi\Controller\Rest\Router\Route $route */
        $newRoutes = [];
        $ip = $this->remoteAddress->getRemoteAddress();
        $userAgent = $this->httpHeader->getHttpUserAgent();
        foreach ($routes as $route) {
            if ($this->isPathAllowed($route->getRoutePath(), $httpMethod) ||
                $this->isPathConditionallyAllowed($route->getRoutePath(), $httpMethod, $ip, $userAgent)
            ) {
                $newRoutes[] = $route;
            }
        }
        return $newRoutes;
    }

    protected function isRestPathFilterEnabled()
    {
        return $this->deploymentConfig->get(self::REST_PATH_FILTER_ENABLED);
    }

    protected function getAllowedRestPath()
    {
        if (null === $this->allowedRestPath) {
            $this->allowedRestPath = [];
            $arr = $this->deploymentConfig->get(self::ALLOWED_REST_PATH, []);
            $arr = array_merge($arr, $this->getAlwaysAllowed());
            foreach ($arr as $route => $methods) {
                if (is_array($methods)) {
                    foreach ($methods as $method) {
                        $this->allowedRestPath[$route][$method] = true;
                    }
                }
            }
        }
        return $this->allowedRestPath;
    }

    protected function getWhitelists()
    {
        if (null === $this->whitelists) {
            $this->whitelists = [];
            $arr = $this->deploymentConfig->get(self::WHITELISTS, []);
            foreach ($arr as $name => $list) {
                if (is_string($name) && is_array($list)) {
                    $newList = [];
                    foreach ($list as $item) {
                        if (is_string($item)) {
                            $newList[] = $item;
                        }
                    }
                    if ($newList) {
                        $this->whitelists[$name] = $newList;
                    }
                }
            }
        }
        return $this->whitelists;
    }

    public function getConditionallyAllowedRestPath()
    {
        if (null === $this->conditinallyAllowedPath) {
            $this->conditinallyAllowedPath = [];
            $arr = $this->deploymentConfig->get(self::CONDITIONALLY_ALLOWED_REST_PATH, []);
            foreach ($arr as $route => $value) {
                if (empty($value['methods']) ||
                    empty($value['conditions']) ||
                    !is_array($value['conditions'])
                ) {
                    continue;
                }
                $methods = [];
                foreach ($value['methods'] as $method) {
                    $methods[$method] = true;
                }
                $value['methods'] = $methods;

                $conditions = [];
                foreach ($value['conditions'] as $type => $conf) {
                    if (!in_array($type, $this->allowedConiditions)) {
                        continue;
                    }
                    $conditions[$type] = [];
                    if (is_string($conf) && !empty($this->getWhitelists()[$conf])) {
                        $conditions[$type] = $this->getWhitelists()[$conf];
                    } elseif (is_array($conf)) {
                        foreach ($conf as $item) {
                            if (is_string($item)) {
                                if (isset($this->getWhitelists()[$item])) {
                                    $conditions[$type] = array_merge(
                                        $conditions[$type],
                                        $this->getWhitelists()[$item]
                                    );
                                } else {
                                    $conditions[$type][] = $item;
                                }
                            }
                        }
                    }
                    if (empty($conditions[$type])) {
                        unset($conditions[$type]);
                    }
                }
                if (empty($conditions)) {
                    continue;
                }
                $value['conditions'] = $conditions;
                $this->conditinallyAllowedPath[$route] = $value;
            }
        }
        return $this->conditinallyAllowedPath;
    }

    public function isPathAllowed($route, $method)
    {
        foreach ($this->getAllowedRestPath() as $path => $methods) {
            if ($this->isPathMatch($route, $path) && !empty($methods[$method])) {
                return true;
            }
        }
        return false;
    }

    public function isPathConditionallyAllowed($route, $method, $clientIp, $clientUserAgent)
    {
        foreach ($this->getConditionallyAllowedRestPath() as $path => $config) {
            if (!empty($config['methods'][$method]) && $this->isPathMatch($route, $path)) {
                if (!empty($config['conditions'][self::IP_CONDITION]) && $clientIp) {
                    foreach ($config['conditions'][self::IP_CONDITION] as $cidr) {
                        if ($this->cidrMatch($clientIp, $cidr)) {
                            return true;
                        }
                    }
                }
                if (!empty($config['conditions'][self::USER_AGENT_CONDITION]) && $clientUserAgent) {
                    foreach ($config['conditions'][self::USER_AGENT_CONDITION] as $userAgent) {
                        if ($this->isUserAgentMatch($clientUserAgent, $userAgent)) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    protected function isPathMatch($route, $path)
    {
        $route = trim($route, '/');
        $path = trim($path, '/');
        return strpos($route, $path) === 0;
    }

    protected function cidrMatch($ip, $range)
    {
        $arr = explode('/', $range);
        $subnet = $arr[0] ?? null;
        $bits = $arr[1] ?? null;
        if (!$subnet) {
            return false;
        }
        if ($bits === null) {
            $bits = 32;
        }
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask; # nb: in case the supplied subnet wasn't correctly aligned
        return ($ip & $mask) == $subnet;
    }

    protected function isUserAgentMatch($clientUserAgent, $allowedUserAgent)
    {
        return strpos($clientUserAgent, $allowedUserAgent) > -1;
    }

    protected function getAlwaysAllowed()
    {
        return [
            'V1/guest-carts' => [
                'GET',
                'POST',
                'PUT',
                'DELETE'
            ],
            'V1/carts/mine' => [
                'GET',
                'POST',
                'PUT',
                'DELETE'
            ],
            'V1/customers/isEmailAvailable' => [
                'POST',
            ]
        ];
    }
}