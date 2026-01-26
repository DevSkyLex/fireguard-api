<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Console\Command\Client;

use OAuth\Application\Port\Outbound\Client\ClientRepositoryPort;
use OAuth\Domain\Model\Client\Client;
use OAuth\Domain\ValueObject\Client\{ClientId, ClientName, ClientSecret, RedirectUri};
use OAuth\Domain\ValueObject\Scope\{Scope, Scopes};
use OAuth\Domain\ValueObject\Security\{GrantType, GrantTypes};
use OAuth\Infrastructure\Console\Command\Client\DeleteClientCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Helper\TestEventIdProvider;

use function password_hash;

use const PASSWORD_BCRYPT;

/**
 * Test DeleteClientCommandTest.
 *
 * @category Console Command Tests
 */
#[CoversClass(className: DeleteClientCommand::class)]
final class DeleteClientCommandTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testExecuteFailsWhenClientNotFound(): void
  {
    $clientRepository = $this->createMock(ClientRepositoryPort::class);
    $clientRepository->expects(self::once())
      ->method('findById')
      ->with(self::isInstanceOf(ClientId::class))
      ->willReturn(null);
    $clientRepository->expects(self::never())
      ->method('delete');

    $command = new DeleteClientCommand(clientRepository: $clientRepository);
    $tester = new CommandTester($command);

    $tester->execute([
      'client-id' => '123e4567-e89b-12d3-a456-426614174000',
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString(
      'not found',
      $tester->getDisplay(),
    );
  }

  #[Test]
  public function testExecuteCancelsWhenNotForced(): void
  {
    $client = $this->createClient();

    $clientRepository = $this->createMock(ClientRepositoryPort::class);
    $clientRepository->expects(self::once())
      ->method('findById')
      ->willReturn($client);
    $clientRepository->expects(self::never())
      ->method('delete');

    $command = new DeleteClientCommand(clientRepository: $clientRepository);
    $tester = new CommandTester($command);
    $tester->setInputs(['no']);

    $tester->execute([
      'client-id' => $client->id()->value,
    ]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString(
      'Operation cancelled.',
      $tester->getDisplay(),
    );
  }

  #[Test]
  public function testExecuteDeletesWhenForced(): void
  {
    $client = $this->createClient();

    $clientRepository = $this->createMock(ClientRepositoryPort::class);
    $clientRepository->expects(self::once())
      ->method('findById')
      ->willReturn($client);
    $clientRepository->expects(self::once())
      ->method('delete')
      ->with($client);

    $command = new DeleteClientCommand(clientRepository: $clientRepository);
    $tester = new CommandTester($command);

    $tester->execute([
      'client-id' => $client->id()->value,
      '--force' => true,
    ]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString(
      'deleted successfully',
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
