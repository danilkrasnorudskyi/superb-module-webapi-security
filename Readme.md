# Web API Security

Settings live in **Stores > Configuration > Security > Web API Security** (global scope).

1. Disable Schema Generation - `superb/webapi_security/schema_request_processor_disabled`
2. Disable SOAP API - `superb/webapi_security/soap_api_disabled`
3. Disable GraphQL API - `superb/webapi_security/graphql_disabled`
4. Enable REST Path Filter - `superb/webapi_security/rest_path_filter_enabled`
5. Allowed REST Paths - `superb/webapi_security/allowed_rest_path` (path prefix + methods)
6. Conditionally Allowed REST Paths - `superb/webapi_security/conditionally_allowed_rest_path` (path prefix + methods + IP/CIDR list + User-Agent list)
7. Whitelists - `superb/webapi_security/whitelists` (named IP/CIDR or User-Agent lists)

`V1/guest-carts`, `V1/carts/mine` and `V1/customers/isEmailAvailable` are always allowed when the filter is on.

IP/CIDR, User-Agent and whitelist values are separated by commas or new lines. An IP/CIDR or User-Agent
item in a condition may be a whitelist name, in which case the whitelist values are used.

The array fields are stored as JSON (`Magento\Config\Model\Config\Backend\Serialized\ArraySerialized`):

```json
{"row1":{"path":"V1/klaviyo/reclaim","methods":["GET","POST"],"ip":"klaviyo_ip_whitelist, 192.168.127.12","user_agent":"Klaviyo"}}
```

## Migrating from env.php

Versions before 1.1.0 read the same paths from the `superb/webapi_security` array in `app/etc/env.php`.
Copy them into store config once, then remove the block from `env.php`:

```bash
bin/magento superb:webapi-security:migrate-config [--dry-run] [--force]
```

`--force` overwrites values already present in `core_config_data`.

```bash
bin/magento superb:webapi-security:rest-service-list [--filter=V1/klaviyo] [--ip=1.2.3.4] [--user-agent=Klaviyo]
```
