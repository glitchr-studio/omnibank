<?php

namespace Omnibank\Bridge\Symfony;

use Omnibank\Bridge\BridgeGatewayFactory;
use Omnibank\Files\FilesGatewayFactory;
use Omnibank\GatewayFactoryInterface;
use Omnibank\GatewayInterface;
use Omnibank\Powens\PowensGatewayFactory;
use Omnibank\Qonto\QontoGatewayFactory;
use Omnibank\Registry;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Omnibank in a Symfony application: the provider packages installed
 * (omnibank/files, omnibank/qonto, omnibank/powens, omnibank/bridge)
 * registered, the application's bank accesses built from configuration,
 * Omnibank\Registry autowired, and each gateway injectable by its name:
 *
 *     omnibank:
 *         gateways:
 *             statements: { factory: files, options: { debtor_name: 'Nakaya SAS' } }
 *             treasury:   { factory: qonto, options: { login: '%env(QONTO_LOGIN)%', secret_key: '%env(QONTO_SECRET_KEY)%' } }
 *
 *     public function __construct(GatewayInterface $treasury) {}
 *
 * An application's own factories (a GatewayFactoryInterface) are registered
 * too, autoconfigured.
 */
final class OmnibankBundle extends AbstractBundle
{
    protected string $extensionAlias = 'omnibank';

    /** The provider packages this bundle knows, registered when installed. */
    private const FACTORIES = [FilesGatewayFactory::class, QontoGatewayFactory::class, PowensGatewayFactory::class, BridgeGatewayFactory::class];

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('gateways')
                    ->info('The application\'s bank accesses, by name: a factory (files, qonto, powens, bridge...) and its options.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('factory')->isRequired()->cannotBeEmpty()->end()
                            ->variableNode('options')->defaultValue([])->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /** @param array{gateways: array<string, array{factory: string, options: array<string, mixed>}>} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(GatewayFactoryInterface::class)->addTag('omnibank.gateway_factory');

        $services = $container->services();
        foreach (self::FACTORIES as $factory) {
            if (class_exists($factory) && is_subclass_of($factory, GatewayFactoryInterface::class)) {
                $services->set($factory)->args([service('http_client')->nullOnInvalid()])->tag('omnibank.gateway_factory');
            }
        }

        $services->set(Registry::class)
            ->args([tagged_iterator('omnibank.gateway_factory'), $config['gateways']])
            ->public();

        foreach (array_keys($config['gateways']) as $name) {
            $id = 'omnibank.gateway.'.$name;
            $services->set($id, GatewayInterface::class)->factory([service(Registry::class), 'get'])->args([$name]);
            $builder->registerAliasForArgument($id, GatewayInterface::class, $name);
        }
    }
}
