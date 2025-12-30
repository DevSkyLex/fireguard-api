<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Console\Command\Client;

use OAuth\Application\Port\Outbound\Client\ClientRepositoryPort;
use OAuth\Domain\Model\Client\Client;
use OAuth\Domain\ValueObject\Client\ClientId;
use OAuth\Domain\ValueObject\Client\ClientName;
use OAuth\Domain\ValueObject\Client\ClientSecret;
use OAuth\Domain\ValueObject\Client\RedirectUri;
use OAuth\Domain\ValueObject\Scope\Scope;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Domain\ValueObject\Security\GrantType;
use OAuth\Domain\ValueObject\Security\GrantTypes;
use Shared\Application\Factory\UuidFactory;
use Shared\Domain\Service\EventIdProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function array_filter;
use function array_map;
use function implode;
use function is_array;
use function is_string;
use function password_hash;
use function sprintf;
use function strtoupper;

use const PASSWORD_BCRYPT;

/**
 * Command CreateClientCommand.
 *
 * @category Console Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(
  name: 'app:client:create',
  description: 'Create a new OAuth2 client',
  aliases: ['client:create'],
)]
final class CreateClientCommand extends Command
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the command with the
   * client repository.
   *
   * @since 1.0.0
   */
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository,
    private readonly UuidFactory $uuidFactory,
    private readonly EventIdProvider $eventIdProvider,
  ) {
    parent::__construct();
  }
  // #endregion

  // #region Methods
  /**
   * Configure.
   *
   * Configures the command by adding
   * arguments and options.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  protected function configure(): void
  {
    $this
      ->addArgument(
        name: 'name',
        mode: InputArgument::REQUIRED,
        description: 'The client name',
      )
      ->addOption(
        name: 'id',
        shortcut: 'i',
        mode: InputOption::VALUE_REQUIRED,
        description: 'Custom client ID (UUID generated if not provided)',
      )
      ->addOption(
        name: 'secret',
        mode: InputOption::VALUE_REQUIRED,
        description: 'Custom client secret (random generated if not provided)',
      )
      ->addOption(
        name: 'redirect-uri',
        shortcut: 'r',
        mode: InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
        description: 'Redirect URIs (can be specified multiple times)',
        default: [],
      )
      ->addOption(
        name: 'grant-type',
        shortcut: 'g',
        mode: InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
        description: 'Grant types: client_credentials, authorization_code, refresh_token',
        default: ['client_credentials', 'authorization_code', 'refresh_token'],
      )
      ->addOption(
        name: 'scope',
        shortcut: 's',
        mode: InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
        description: 'Scopes: openid, profile, email, read, write',
        default: ['openid', 'profile', 'email', 'read', 'write'],
      )
      ->setHelp(
        <<<'HELP'
The <info>%command.name%</info> command creates a new OAuth2 client:

  <info>php %command.full_name% "My Application"</info>

With custom redirect URIs:

  <info>php %command.full_name% "My App" -r https://myapp.com/callback -r https://myapp.com/oauth</info>

With specific grant types:

  <info>php %command.full_name% "My App" -g client_credentials -g authorization_code</info>

With specific scopes:

  <info>php %command.full_name% "My App" -s openid -s profile -s read</info>

HELP
      );
  }

  /**
   * Execute.
   *
   * Executes the command with the given
   * input and output.
   *
   * @since 1.0.0
   *
   * @param InputInterface $input the input interface
   * @param OutputInterface $output the output interface
   *
   * @return int the exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);

    $nameArg = $input->getArgument('name');
    $name = is_string($nameArg) ? $nameArg : '';
    $redirectUrisOption = $input->getOption('redirect-uri');
    $redirectUris = is_array($redirectUrisOption) ? array_filter($redirectUrisOption, 'is_string') : [];
    $grantTypeOption = $input->getOption('grant-type');
    $grantTypeStrings = is_array($grantTypeOption) ? array_filter($grantTypeOption, 'is_string') : [];
    $scopeOption = $input->getOption('scope');
    $scopeStrings = is_array($scopeOption) ? array_filter($scopeOption, 'is_string') : [];

    try {
      // Generate client ID (use provided ID if valid UUID, otherwise generate)
      $customId = $input->getOption('id');
      if (is_string($customId) && '' !== $customId) {
        try {
          $clientId = new ClientId($customId);
        } catch (Throwable) {
          // If invalid UUID format, generate a new one and warn user
          $clientId = $this->uuidFactory->create(ClientId::class);
          $io->warning(sprintf(
            'Provided ID "%s" is not a valid UUID. Generated: %s',
            $customId,
            $clientId->value,
          ));
        }
      } else {
        $clientId = $this->uuidFactory->create(ClientId::class);
      }

      // Generate or use provided secret
      $customSecret = $input->getOption('secret');
      $plainSecret = is_string($customSecret) && '' !== $customSecret ? $customSecret : ClientSecret::generateRandomPlain(32);
      // password_hash with PASSWORD_BCRYPT always returns non-empty-string
      $hashedSecret = password_hash($plainSecret, PASSWORD_BCRYPT);
      $clientSecret = new ClientSecret($hashedSecret);

      // Parse redirect URIs (skip if empty - for client_credentials grant)
      $redirectUriObjects = [];
      foreach ($redirectUris as $uri) {
        if ('' !== $uri) {
          $redirectUriObjects[] = new RedirectUri($uri);
        }
      }

      // Parse grant types
      $grantTypes = array_map(
        fn (string $gt) => GrantType::from($gt),
        $grantTypeStrings,
      );

      // Parse scopes
      $scopes = array_map(
        fn (string $s) => Scope::from(strtoupper($s)),
        $scopeStrings,
      );

      // Create client
      $client = Client::register(
        id: $clientId,
        name: new ClientName($name),
        secret: $clientSecret,
        redirectUris: $redirectUriObjects,
        grantTypes: new GrantTypes(...$grantTypes),
        scopes: new Scopes(...$scopes),
        eventIdProvider: $this->eventIdProvider,
      );

      // Clear events
      $client->releaseEvents();

      // Save
      $this->clientRepository->save($client);

      $io->success('OAuth2 client created successfully!');

      $io->table(
        ['Property', 'Value'],
        [
          ['Client ID', $clientId->value],
          ['Client Secret', $plainSecret],
          ['Name', $name],
          ['Redirect URIs', implode(', ', $redirectUris)],
          ['Grant Types', implode(', ', $grantTypeStrings)],
          ['Scopes', implode(', ', $scopeStrings)],
        ],
      );

      $io->warning('Save the Client Secret now! It cannot be retrieved later.');

      return Command::SUCCESS;
    } catch (Throwable $e) {
      $io->error(sprintf('Failed to create client: %s', $e->getMessage()));

      return Command::FAILURE;
    }
  }
  // #endregion
}
