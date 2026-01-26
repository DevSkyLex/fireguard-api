<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Console\Command\Client;

use OAuth\Application\Port\Outbound\Client\ClientRepositoryPort;
use OAuth\Domain\Model\Client\Client;
use OAuth\Domain\ValueObject\Client\{ClientId, ClientName, ClientSecret, RedirectUri};
use OAuth\Domain\ValueObject\Scope\{Scope, Scopes};
use OAuth\Domain\ValueObject\Security\{GrantType, GrantTypes};
use OAuth\Infrastructure\Console\Command\Client\ListClientsCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Helper\TestEventIdProvider;

use function password_hash;

use const PASSWORD_BCRYPT;

/**
 * Test ListClientsCommandTest.
 *
 * @category Console Command Tests
 */
#[CoversClass(className: ListClientsCommand::class)]
final class ListClientsCommandTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testExecuteOutputsNoClientsMessage(): void
  {
    $clientRepository = $this->createMock(ClientRepositoryPort::class);
    $clientRepository->expects(self::once())
      ->method('findAll')
      ->willReturn([]);

    $command = new ListClientsCommand(clientRepository: $clientRepository);
    $tester = new CommandTester($command);

    $tester->execute([]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString(
      'No OAuth2 clients found.',
      $tester->getDisplay(),
    );
  }

  #[Test]
  public function testExecuteListsClients(): void
  {
    $client = $this->createClient();

    $clientRepository = $this->createMock(ClientRepositoryPort::class);
    $clientRepository->expects(self::once())
      ->method('findAll')
      ->willReturn([$client]);

    $command = new ListClientsCommand(clientRepository: $clientRepository);
    $tester = new CommandTester($command);

    $tester->execute([]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString(
      'Found 1 client(s).',
      $tester->getDisplay(),
    );
  }

  private function createClient(): Client
  {
    $hashedSecret = password_hash('test-secret', PASSWORD_BCRYPT);

    return Client::register(
      id: new ClientId(value: '123e4567-e89b-12d3-a456-426614174000'),
      name: new ClientName(value: 'Test Client'),
      secret: new ClientSecret(value: $hashedSecret),
      redirectUris: [new RedirectUri(value: 'https://example.com/callback')],
      grantTypes: new GrantTypes(GrantType::AUTHORIZATION_CODE),
      scopes: new Scopes(Scope::READ),
      eventIdProvider: new TestEventIdProvider(),
    );
  }
  // #endregion
}
