<?php
declare(strict_types=1);

namespace BehatRemoteCodeCoverage;

use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class RemoteCodeCoverageExtension implements Extension
{
    public function process(ContainerBuilder $container)
    {
    }

    public function getConfigKey()
    {
        return 'remote_code_coverage';
    }

    public function initialize(ExtensionManager $extensionManager)
    {
    }

    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->children()
                ->scalarNode('target_directory')
                    ->isRequired()
                    ->info('The directory where the generated coverage files should be stored.')
                ->end()
                ->enumNode('split_by')
                    ->defaultValue('suite')
                    ->values(['suite', 'feature', 'scenario'])
                    ->info('The strategy to save/split coverage files by (suite, feature or scenario).')
                ->end()
                ->scalarNode('base_url')
                    ->defaultNull()
                    ->info('The base url of the php application, leave null to use mink base url.')
                ->end()
            ->end();
    }

    public function load(ContainerBuilder $container, array $config)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));
        $loader->load('services.yml');

        $container->setParameter('remote_code_coverage.target_directory', $config['target_directory']);
        $container->setParameter('remote_code_coverage.split_by', $config['split_by']);
        $container->setParameter('remote_code_coverage.base_url', $config['base_url'] ?: '%mink.base_url%');
    }
}
