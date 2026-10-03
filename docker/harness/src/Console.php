<?php

namespace Omnibank\Harness;

use Omnibank\Exception\InvalidConfigException;
use Omnibank\Exception\OmnibankException;
use Omnibank\GatewayFactoryInterface;
use Omnibank\GatewayInterface;
use Omnibank\Model\Account;
use Omnibank\Model\Connection;
use Omnibank\Model\Consent;
use Omnibank\Registry;
use Omnibank\Request;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * The console that exercises every provider with the keys in .env:
 * gateways, connect, accounts, balances, transactions. Each gateway's
 * connection (its user, its tokens) is kept in /harness/connections between
 * runs, as an application would keep it. Results are printed whole, as JSON.
 */
final class Console
{
    private const CONNECTIONS = '/harness/connections';

    /** @var array<string, array{factory: string, needs: list<string>, options: array<string, mixed>}> */
    private array $config;

    /** @var array<string, GatewayFactoryInterface> */
    private array $factories = [];

    private Registry $registry;

    private function __construct()
    {
        $this->config = require __DIR__.'/../config/gateways.php';
        $http = HttpClient::create();
        foreach (require __DIR__.'/../plugins.php' as [, $class]) {
            if (class_exists($class)) {
                $factory = new $class($http);
                $this->factories[$factory->getName()] = $factory;
            }
        }
        $configured = array_filter($this->config, static fn (array $g) => !array_filter($g['needs'], static fn (string $key) => false === getenv($key) || '' === getenv($key)));
        $this->registry = new Registry($this->factories, array_map(static fn (array $g) => ['factory' => $g['factory'], 'options' => array_filter($g['options'], static fn ($v) => null !== $v)], $configured));
    }

    public static function create(): Application
    {
        $self = new self();
        $gateway = new InputArgument('gateway', InputArgument::REQUIRED);
        $path = new InputOption('path', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A statement file (CAMT.053, OFX, CSV) for the files gateway, mounted in the container');
        $account = new InputOption('account', null, InputOption::VALUE_REQUIRED, 'The account\'s id; the first one when not given');
        $app = new Application('omnibank', '1.x');
        $app->addCommand($self->command('gateways', 'Which providers are installed and which are configured from .env', [], fn ($in, $out) => $self->gateways($out)));
        $app->addCommand($self->command('connect', 'The page to send the user to (a Webview, a Connect session); the connection kept', [
            $gateway,
            new InputOption('return-url', 'r', InputOption::VALUE_REQUIRED, 'Where the provider sends the user back', getenv('HARNESS_RETURN_URL') ?: 'https://example.org/bank/back'),
            new InputOption('set', 's', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '"key=value" put in the state first: connection_id=77 (Powens), item_id=123 or user_email=... (Bridge)'),
            new InputOption('renew', null, InputOption::VALUE_NONE, 'The consent needs renewal: the reconnect page'),
            new InputOption('forget', null, InputOption::VALUE_NONE, 'Start from a new connection'),
        ], fn ($in, $out) => $self->connect($in, $out)));
        $app->addCommand($self->command('accounts', 'The accounts the connection reaches', [$gateway, $path], fn ($in, $out) => $self->print($out, $self->gateway($in)->accounts($self->connection($in)))));
        $app->addCommand($self->command('balances', 'An account\'s balances', [$gateway, $path, $account], function ($in, $out) use ($self) {
            $connection = $self->connection($in);
            $self->print($out, $self->gateway($in)->balances($connection, $self->account($in, $connection)));
        }));
        $app->addCommand($self->command('transactions', 'An account\'s booked transactions, oldest first', [
            $gateway, $path, $account,
            new InputOption('since', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD'),
            new InputOption('until', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD'),
        ], function ($in, $out) use ($self) {
            $connection = $self->connection($in);
            $self->print($out, $self->gateway($in)->transactions(
                $connection,
                $self->account($in, $connection),
                $in->getOption('since') ? new \DateTimeImmutable($in->getOption('since')) : null,
                $in->getOption('until') ? new \DateTimeImmutable($in->getOption('until')) : null,
            ));
        }));

        return $app;
    }

    /** @param list<InputArgument|InputOption> $definition */
    private function command(string $name, string $description, array $definition, \Closure $code): Command
    {
        $command = new Command($name);
        $command->setDescription($description)->setDefinition($definition);
        $command->setCode(function (InputInterface $in, OutputInterface $out) use ($code): int {
            try {
                $code($in, $out);

                return Command::SUCCESS;
            } catch (InvalidConfigException $e) {
                $out->writeln('<error>'.$e->getMessage().'</error>');

                return Command::INVALID;
            } catch (OmnibankException $e) {
                $out->writeln('<error>'.$e->getMessage().'</error>');

                return Command::FAILURE;
            }
        });

        return $command;
    }

    private function gateways(OutputInterface $out): void
    {
        $requests = ['connect' => Request\Connect::class, 'accounts' => Request\FetchAccounts::class, 'balances' => Request\FetchBalances::class, 'transactions' => Request\FetchTransactions::class, 'transfer' => Request\Transfer::class, 'notify' => Request\Notify::class];
        $table = new Table($out);
        $table->setHeaders(['Gateway', 'Factory', 'Installed', 'Configured', 'Connected', 'Does']);
        foreach ($this->config as $name => $gateway) {
            $installed = isset($this->factories[$gateway['factory']]);
            $missing = array_filter($gateway['needs'], static fn (string $key) => false === getenv($key) || '' === getenv($key));
            $does = '';
            if ($installed && !$missing) {
                $g = $this->registry->get($name);
                $does = implode(' ', array_keys(array_filter($requests, static fn (string $class) => $g->supports($class))));
            }
            $connection = $this->load($name);
            $table->addRow([$name, $gateway['factory'], $installed ? '<info>yes</info>' : '<comment>no</comment>', $missing ? '<comment>needs '.implode(', ', $missing).'</comment>' : ($installed ? '<info>yes</info>' : ''), $connection->state ? $connection->consent->status->value : '', $does]);
        }
        $table->render();
    }

    private function connect(InputInterface $in, OutputInterface $out): void
    {
        $name = $in->getArgument('gateway');
        $connection = $in->getOption('forget') ? new Connection() : $this->load($name);
        $state = $connection->state;
        foreach ((array) $in->getOption('set') as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $state[trim($key)] = trim($value);
        }
        $connection = $connection->withState($state);
        if ($in->getOption('renew')) {
            $connection = $connection->withConsent(Consent::needsRenewal());
        }

        $result = $this->gateway($in)->connect($connection, (string) $in->getOption('return-url'));
        $this->save($name, $result->connection);
        if ($result->url) {
            $out->writeln('<info>Open:</info> '.$result->url);
        } else {
            $out->writeln('<info>Nothing to open: connected.</info>');
        }
        $out->writeln('<comment>Connection kept in '.self::CONNECTIONS.'/'.$name.'.json</comment>');
    }

    private function gateway(InputInterface $in): GatewayInterface
    {
        return $this->registry->get($in->getArgument('gateway'));
    }

    /** The gateway's kept connection, the --path files in its state. */
    private function connection(InputInterface $in): Connection
    {
        $connection = $this->load($in->getArgument('gateway'));
        $paths = $in->hasOption('path') ? (array) $in->getOption('path') : [];
        if ($paths) {
            $statements = [];
            foreach ($paths as $path) {
                if (!is_file($path)) {
                    throw new InvalidConfigException(\sprintf('No file at "%s" in the container: mount its directory (docker compose run -v ...).', $path));
                }
                $statements[] = ['name' => basename($path), 'content' => (string) file_get_contents($path)];
            }
            $connection = $connection->withState(['statements' => $statements] + $connection->state);
        }

        return $connection;
    }

    private function account(InputInterface $in, Connection $connection): Account
    {
        $accounts = $this->gateway($in)->accounts($connection);
        $wanted = $in->getOption('account');
        foreach ($accounts as $account) {
            if (null === $wanted || $account->id === $wanted || $account->iban === $wanted) {
                return $account;
            }
        }

        throw new InvalidConfigException(null === $wanted ? 'No account.' : \sprintf('No account "%s"; there are: %s.', $wanted, implode(', ', array_map(static fn (Account $a) => $a->id, $accounts)) ?: 'none'));
    }

    private function load(string $name): Connection
    {
        $file = self::CONNECTIONS.'/'.$name.'.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return \is_array($data) ? Connection::fromArray($data) : new Connection();
    }

    private function save(string $name, Connection $connection): void
    {
        if (!is_dir(self::CONNECTIONS)) {
            mkdir(self::CONNECTIONS, 0700, true);
        }
        file_put_contents(self::CONNECTIONS.'/'.$name.'.json', json_encode($connection->toArray(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }

    private function print(OutputInterface $out, mixed $result): void
    {
        $out->writeln((string) json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR));
    }
}
