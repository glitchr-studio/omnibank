<?php

namespace Omnibank\Tests\Bridge;

use Omnibank\Bridge\Symfony\OmnibankBundle;
use Omnibank\GatewayInterface;
use Omnibank\Model\Connection;
use Omnibank\Registry;
use Omnibank\Tests\StubFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;

final class OmnibankBundleTest extends TestCase
{
    public function testTheGatewaysConfiguredAreBuiltAndInjectableByName(): void
    {
        $container = $this->container(['gateways' => [
            'treasury' => ['factory' => 'stub', 'options' => ['token' => 't']],
            'savings' => ['factory' => 'stub', 'options' => ['token' => 's']],
        ]]);
        $container->compile();

        $registry = $container->get(Registry::class);
        self::assertSame(['treasury', 'savings'], $registry->names());
        self::assertContains('stub', $registry->factories());

        $books = $container->get(Books::class);
        self::assertSame('t_1', $books->treasury->connect(new Connection(), 'https://app.example')->connection->get('user'), 'injected by its name');
        self::assertSame('s_1', $books->savings->connect(new Connection(), 'https://app.example')->connection->get('user'));
    }

    public function testEveryInstalledOmnibankFactoryIsRegistered(): void
    {
        $container = $this->container(['gateways' => []]);
        $container->compile();

        $installed = array_filter(['files' => \Omnibank\Files\FilesGatewayFactory::class, 'qonto' => \Omnibank\Qonto\QontoGatewayFactory::class, 'powens' => \Omnibank\Powens\PowensGatewayFactory::class, 'bridge' => \Omnibank\Bridge\BridgeGatewayFactory::class], 'class_exists');
        $factories = $container->get(Registry::class)->factories();
        self::assertContains('stub', $factories, 'the application\'s own, autoconfigured');
        foreach (array_keys($installed) as $name) {
            self::assertContains($name, $factories);
        }
    }

    public function testAGatewayNeedsAFactory(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->container(['gateways' => ['treasury' => ['options' => []]]])->compile();
    }

    private function container(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class);
        // An application's own provider, autoconfigured.
        $container->register(StubFactory::class)->setAutoconfigured(true);
        if (isset($config['gateways']['treasury'], $config['gateways']['savings'])) {
            $container->register(Books::class)->setAutowired(true)->setPublic(true);
        }
        $bundle = new OmnibankBundle();
        $container->registerExtension($bundle->getContainerExtension());
        $container->loadFromExtension('omnibank', $config);

        return $container;
    }
}

final class Books
{
    public function __construct(public readonly GatewayInterface $treasury, public readonly GatewayInterface $savings)
    {
    }
}
