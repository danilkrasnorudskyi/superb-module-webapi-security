<?php

namespace Superb\WebapiSecurity\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Header;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Serialize\Serializer\Json;

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

    protected $scopeConfig;
    protected $json;
    protected $remoteAddress;
    protected $httpHeader;

    protected $allowedRestPath;
    protected $whitelists;
    protected $conditinallyAllowedPath;
    protected $allowedConiditions = [self::IP_CONDITION, self::USER_AGENT_CONDITION];

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json $json,
        RemoteAddress $remoteAddress,
        Header $httpHeader
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->json = $json;
        $this->remoteAddress = $remoteAddress;
        $this->httpHeader = $httpHeader;
    }

    public function isSchemaRequestProcessorDisabled()
    {
        return $this->scopeConfig->isSetFlag(self::SCHEMA_REQUEST_PROCESSOR_DISABLED);
    }

    public function isSoapApiDisabled()
    {
        return $this->scopeConfig->isSetFlag(self::SOAP_API_DISABLED);
    }

    public function isGraphqlDisabled()
    {
        return $this->scopeConfig->isSetFlag(self::GRAPHQL_DISABLED);
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
        return $this->scopeConfig->isSetFlag(self::REST_PATH_FILTER_ENABLED);
    }

    /**
     * Rows of a serialized dynamic-rows config field
     */
    protected function getRows($path)
    {
        $value = $this->scopeConfig->getValue($path);
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        try {
            $rows = $this->json->unserialize($value);
        } catch (\InvalidArgumentException $e) {
            return [];
        }
        return is_array($rows) ? $rows : [];
    }

    /**
     * Split a comma/newline separated string into trimmed non-empty items
     */
    protected function splitList($value)
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $items = [];
        foreach (preg_split('/[\s,]+/', (string)$value) as $item) {
            if ($item !== '') {
                $items[] = $item;
            }
        }
        return $items;
    }

    protected function getMethods($value)
    {
        $methods = [];
        foreach ($this->splitList($value) as $method) {
            $methods[strtoupper($method)] = true;
        }
        return $methods;
    }

    protected function getAllowedRestPath()
    {
        if (null === $this->allowedRestPath) {
            $this->allowedRestPath = [];
            foreach ($this->getRows(self::ALLOWED_REST_PATH) as $row) {
                $path = trim((string)($row['path'] ?? ''));
                if ($path === '') {
                    continue;
                }
                $this->allowedRestPath[$path] = ($this->allowedRestPath[$path] ?? [])
                    + $this->getMethods($row['methods'] ?? []);
            }
            foreach ($this->getAlwaysAllowed() as $path => $methods) {
                $this->allowedRestPath[$path] = ($this->allowedRestPath[$path] ?? []) + $this->getMethods($methods);
            }
        }
        return $this->allowedRestPath;
    }

    protected function getWhitelists()
    {
        if (null === $this->whitelists) {
            $this->whitelists = [];
            foreach ($this->getRows(self::WHITELISTS) as $row) {
                $name = trim((string)($row['name'] ?? ''));
                $values = $this->splitList($row['values'] ?? '');
                if ($name !== '' && $values) {
                    $this->whitelists[$name] = array_merge($this->whitelists[$name] ?? [], $values);
                }
            }
        }
        return $this->whitelists;
    }

    public function getConditionallyAllowedRestPath()
    {
        if (null === $this->conditinallyAllowedPath) {
            $this->conditinallyAllowedPath = [];
            foreach ($this->getRows(self::CONDITIONALLY_ALLOWED_REST_PATH) as $row) {
                $path = trim((string)($row['path'] ?? ''));
                $methods = $this->getMethods($row['methods'] ?? []);
                if ($path === '' || !$methods) {
                    continue;
                }
                $conditions = [];
                foreach ($this->allowedConiditions as $type) {
                    $values = [];
                    // each item is either a whitelist name or a literal value
                    foreach ($this->splitList($row[$type] ?? '') as $item) {
                        if (isset($this->getWhitelists()[$item])) {
                            $values = array_merge($values, $this->getWhitelists()[$item]);
                        } else {
                            $values[] = $item;
                        }
                    }
                    if ($values) {
                        $conditions[$type] = $values;
                    }
                }
                if (!$conditions) {
                    continue;
                }
                // list, not keyed by path: the same path may have several rows with different conditions
                $this->conditinallyAllowedPath[] = [
                    'path' => $path,
                    'methods' => $methods,
                    'conditions' => $conditions,
                ];
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
        foreach ($this->getConditionallyAllowedRestPath() as $config) {
            if (!empty($config['methods'][$method]) && $this->isPathMatch($route, $config['path'])) {
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
        if ($ip === false || $subnet === false) {
            return false;
        }
        $mask = -1 << (32 - (int)$bits);
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
