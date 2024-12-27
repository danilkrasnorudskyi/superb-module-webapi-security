<?php

namespace Superb\WebapiSecurity\Console;

use Magento\Framework\App\ObjectManager;
use Magento\Webapi\Model\Config\Converter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RestServiceList extends Command
{
    const FILTER = 'filter';
    const USER_AGENT = 'user-agent';
    const IP = 'ip';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filter = $input->getOption(self::FILTER);
        $userAgent = $input->getOption(self::USER_AGENT);
        $ip = $input->getOption(self::IP);
        $helper = ObjectManager::getInstance()->get(\Superb\WebapiSecurity\Helper\Data::class);
        $asyncConfig = ObjectManager::getInstance()->get(\Magento\WebapiAsync\Model\BulkServiceConfig::class);
        $config = ObjectManager::getInstance()->get(\Magento\Webapi\Model\Config::class);
        $asyncServiceRoutes = $asyncConfig->getServices()[Converter::KEY_ROUTES];
        $serviceRoutes = $config->getServices()[Converter::KEY_ROUTES];
        $serviceRoutes = array_merge($asyncServiceRoutes, $serviceRoutes);
        $data = [];
        foreach ($serviceRoutes as $route => $methods) {
            if ($filter && strpos(trim($route, '/'), $filter) !== 0) {
                continue;
            }
            if ($userAgent || $ip) {
                foreach (array_keys($methods) as $method) {
                    if ($helper->isPathAllowed($route, $method) ||
                        $helper->isPathConditionallyAllowed($route, $method, $ip, $userAgent)
                    ) {
                        $data[$route][] = $method;
                    }
                }
            } else {
                $data[$route] = array_keys($methods);
            }
        }
        $strlen = strlen('POST,GET,PUT,DELETE');
        foreach ($data as $route => $methods) {
            $methods = implode(',', $methods);
            $spacer = str_repeat(' ', $strlen - strlen($methods) - 1);
            $output->writeln('<info>' . $methods . $spacer . ' - ' . $route . '</info>');
        }

    }

    /** * {@inheritdoc} */
    protected function configure()
    {
        $options = [
            new InputOption(
                self::FILTER,
                null,
                InputOption::VALUE_OPTIONAL,
                'Filter'

            ),
            new InputOption(
                self::USER_AGENT,
                null,
                InputOption::VALUE_OPTIONAL,
                'User agent'

            ),
            new InputOption(
                self::IP,
                null,
                InputOption::VALUE_OPTIONAL,
                'IP'

            ),
        ];
        $this->setName('superb:webapi-security:rest-service-list');
        $this->setDescription('');
        $this->setDefinition($options);
        parent::configure();
    }
}
