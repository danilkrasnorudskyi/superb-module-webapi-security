[![Latest Stable Version](https://poser.pugx.org/superb-code/module-webapi-security/v/stable)](https://packagist.org/packages/superb-code/module-webapi-security)
[![Total Downloads](https://poser.pugx.org/superb-code/module-webapi-security/downloads)](https://packagist.org/packages/superb-code/module-webapi-security)

### Install via composer (recommend)

Run the following command in Magento 2 root folder:

```
composer require superb-code/module-webapi-security
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```


------

### Environment variables usage (app/etc/env.php)

1. `superb/webapi_security/schema_request_processor_disabled` - disable schema generate
2. `superb/webapi_security/soap_api_disabled` - disable SOAP API
3. `superb/webapi_security/graphql_disabled` - disable GraphQL API
4. `superb/webapi_security/rest_path_filter_enabled` - enable REST API path filter
5. `superb/webapi_security/allowed_rest_path` - list of allowed paths
6. `superb/webapi_security/conditionally_allowed_rest_path` - list of allowed path based on IP or User Agent
7. `superb/webapi_security/whitelists` - IP or User Agent lists


Full example below:
```
'superb' => [
    'webapi_security' => [
        'schema_request_processor_disabled' => 1,
        'soap_api_disabled' => 1,
        'graphql_disabled' => 1,
        'rest_path_filter_enabled' => 1,
        'allowed_rest_path' => [
            'V1/stripe' => ['POST'],
            'V1/is-place-order-allowed' => ['POST'],
        ],
        'conditionally_allowed_rest_path' => [
            'V1/klaviyo/reclaim' => [
                'methods' => ['GET','POST'],
                'conditions' => [
                    'ip' => ['klaviyo_ip_whitelist', '192.168.127.12'],
                    'user_agent' => ['klaviyo_user_agent_whitelist', 'Example user agent']
                ]
            ]
        ],
        'whitelists' => [
            'klaviyo_ip_whitelist' => [
                '207.211.192.0/24',
                '207.211.193.0/24',
                '207.211.194.0/24',
                '207.211.195.0/24',
                '207.211.196.0/24',
                '207.211.197.0/24',
                '207.211.198.0/24',
                '207.211.199.0/24',
                '207.211.200.0/24',
                '207.211.201.0/24',
                '207.211.202.0/24',
                '207.211.203.0/24',
                '207.211.204.0/24',
                '207.211.205.0/24',
                '207.211.206.0/24',
                '207.211.207.0/24',
                '172.23.0.1'
            ],
            'klaviyo_user_agent_whitelist' => [
                'Klaviyo'
            ]
        ]
    ]
]
```