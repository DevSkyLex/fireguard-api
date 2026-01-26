<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Console\Command\Token;

use OAuth\Application\Port\Outbound\Client\ClientRepositoryPort;
use OAuth\Application\UseCase\Command\Token\IssueToken\{IssueTokenCommand, IssueTokenResult};
use OAuth\Domain\Model\Client\Client;
use OAuth\Domain\ValueObject\Client\{ClientId, ClientName, ClientSecret, RedirectUri};
use OAuth\Domain\ValueObject\Scope\{Scope, Scopes};
use OAuth\Domain\ValueObject\Security\{GrantType, GrantTypes};
use OAuth\Infrastructure\Console\Command\Token\GenerateTokenCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Helper\TestEventIdProvider;

use function password_hash;

use const PASSWORD_BCRYPT;

/**
 * Test GenerateTokenCommandTest.
 *
 * @category Console Command Tests
 */
#[CoversClass(className: GenerateTokenCommand::class)]
final class GenerateTokenCommandTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testExecuteFailsWhenClientMissing(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $clientRepository = $this->createMock(ClientRepositoryPort::class);
    $clientRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $command = new GenerateTokenCommand(
      commandBus: $commandBus,
      clientRepository: $clientRepository,
    );

    $tester = new CommandTester($command);

    $tester->execute([
      'client-id' => '123e4567-e89b-12d3-a456-426614174000',
      'client-secret' => 'plain-secret',
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString(
      'not found',
      $tester->getDisplay(),
    );
  }

  #[Test]
  public function testExecuteGeneratesTokenSuccessfully(): void
  {
    $client = $this->createClient();
    $result = new IssueTokenResult(
      accessToken: 'access-token-value',
      tokenType: 'Bearer',
      expiresIn: 3600,
      refreshToken: 'refresh-token-value',
      scope: 'openid profile',
    );

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(function (IssueTokenCommand $command): bool {
        return 'client_credentials' === $command->grantType
          && '123e4567-e89b-12d3-a456-426614174000' === $command->clientId
          && 'plain-secret' === $command->clientSecret;
      }))
      ->willReturn($result);

    $clientRepository = $this->createMock(ClientRepositoryPort::class);
    $clientRepository->expects(self::once())
      ->method('findById')
      ->willReturn($client);

    $command = new GenerateTokenCommand(
      commandBus: $commandBus,
      clientRepository: $clientRepository,
    );

    $tester = new CommandTester($command);

    $tester->execute([
      'client-id' => '123e4567-e89b-12d3-a456-426614174000',
      'client-secret' => 'plain-secret',
      '--scope' => 'openid profile',
    ]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString(
      'Access token generated successfully',
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
