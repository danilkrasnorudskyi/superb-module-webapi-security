<?php

namespace Superb\WebapiSecurity\Console;

use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Serialize\Serializer\Json;
use Superb\WebapiSecurity\Helper\Data;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Copy the legacy env.php "superb/webapi_security" array into store config
 */
class MigrateConfig extends Command
{
    const DRY_RUN = 'dry-run';
    const FORCE = 'force';

    const FLAG_PATHS = [
        Data::SCHEMA_REQUEST_PROCESSOR_DISABLED,
        Data::SOAP_API_DISABLED,
        Data::GRAPHQL_DISABLED,
        Data::REST_PATH_FILTER_ENABLED,
        Data::LOG_BLOCKED_REQUESTS,
    ];

    protected $deploymentConfig;
    protected $configWriter;
    protected $configCollectionFactory;
    protected $json;

    public function __construct(
        DeploymentConfig $deploymentConfig,
        WriterInterface $configWriter,
        CollectionFactory $configCollectionFactory,
        Json $json
    ) {
        $this->deploymentConfig = $deploymentConfig;
        $this->configWriter = $configWriter;
        $this->configCollectionFactory = $configCollectionFactory;
        $this->json = $json;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('superb:webapi-security:migrate-config');
        $this->setDescription('Copy superb/webapi_security settings from app/etc/env.php into store config');
        $this->setDefinition([
            new InputOption(self::DRY_RUN, null, InputOption::VALUE_NONE, 'Print the values without saving'),
            new InputOption(self::FORCE, null, InputOption::VALUE_NONE, 'Overwrite values already present in store config'),
        ]);
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dryRun = $input->getOption(self::DRY_RUN);
        $force = $input->getOption(self::FORCE);

        if (!$this->deploymentConfig->get('superb/webapi_security')) {
            $output->writeln('<comment>No superb/webapi_security section found in app/etc/env.php, nothing to migrate.</comment>');
            return Command::SUCCESS;
        }

        $values = $this->collectValues();
        $existing = $this->getExistingPaths(array_keys($values));
        $saved = 0;
        foreach ($values as $path => $value) {
            if (isset($existing[$path]) && !$force) {
                $output->writeln(sprintf('<comment>skip  %s (already set in store config, use --force to overwrite)</comment>', $path));
                continue;
            }
            $output->writeln(sprintf('<info>%s %s = %s</info>', $dryRun ? 'would set' : 'set', $path, $value));
            if (!$dryRun) {
                $this->configWriter->save($path, $value, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);
                $saved++;
            }
        }

        if ($saved) {
            $output->writeln(sprintf('<info>Saved %d value(s).</info>', $saved));
            $output->writeln('<comment>Run "bin/magento cache:clean config" to apply.</comment>');
            $output->writeln('<comment>Remove the "superb" => ["webapi_security" => [...]] block from app/etc/env.php, it is no longer read.</comment>');
        }
        return Command::SUCCESS;
    }

    /**
     * @return array path => value as stored in core_config_data
     */
    protected function collectValues()
    {
        $values = [];
        foreach (self::FLAG_PATHS as $path) {
            $flag = $this->deploymentConfig->get($path);
            if ($flag !== null) {
                $values[$path] = $flag ? '1' : '0';
            }
        }

        $rows = [];
        foreach ((array)$this->deploymentConfig->get(Data::ALLOWED_REST_PATH, []) as $path => $methods) {
            if (is_string($path) && is_array($methods)) {
                $rows[] = ['path' => $path, 'methods' => array_values($methods)];
            }
        }
        if ($rows) {
            $values[Data::ALLOWED_REST_PATH] = $this->serializeRows($rows);
        }

        $rows = [];
        foreach ((array)$this->deploymentConfig->get(Data::CONDITIONALLY_ALLOWED_REST_PATH, []) as $path => $config) {
            if (!is_string($path) || empty($config['methods']) || !is_array($config['methods'])) {
                continue;
            }
            $conditions = is_array($config['conditions'] ?? null) ? $config['conditions'] : [];
            $rows[] = [
                'path' => $path,
                'methods' => array_values($config['methods']),
                Data::IP_CONDITION => $this->joinList($conditions[Data::IP_CONDITION] ?? [], ', '),
                Data::USER_AGENT_CONDITION => $this->joinList($conditions[Data::USER_AGENT_CONDITION] ?? [], ', '),
            ];
        }
        if ($rows) {
            $values[Data::CONDITIONALLY_ALLOWED_REST_PATH] = $this->serializeRows($rows);
        }

        $rows = [];
        foreach ((array)$this->deploymentConfig->get(Data::WHITELISTS, []) as $name => $list) {
            if (is_string($name) && is_array($list)) {
                $rows[] = ['name' => $name, 'values' => $this->joinList($list, "\n")];
            }
        }
        if ($rows) {
            $values[Data::WHITELISTS] = $this->serializeRows($rows);
        }

        return $values;
    }

    /**
     * A condition may be a single whitelist name or a list of names/literals
     */
    protected function joinList($value, $glue)
    {
        $items = [];
        foreach ((array)$value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $items[] = trim($item);
            }
        }
        return implode($glue, $items);
    }

    protected function serializeRows(array $rows)
    {
        $keyed = [];
        foreach ($rows as $i => $row) {
            $keyed['row_' . ($i + 1)] = $row;
        }
        return $this->json->serialize($keyed);
    }

    /**
     * @return array path => true for paths that already have a default-scope row in core_config_data
     */
    protected function getExistingPaths(array $paths)
    {
        $collection = $this->configCollectionFactory->create();
        $collection->addFieldToFilter('path', ['in' => $paths])
            ->addFieldToFilter('scope', ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
            ->addFieldToFilter('scope_id', 0);
        $existing = [];
        foreach ($collection as $item) {
            $existing[$item->getPath()] = true;
        }
        return $existing;
    }
}
