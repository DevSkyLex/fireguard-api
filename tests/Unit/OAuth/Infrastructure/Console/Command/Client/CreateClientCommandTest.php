<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Console\Command\Client;

use OAuth\Application\Port\Outbound\Client\ClientRepositoryPort;
use OAuth\Domain\Model\Client\Client;
use OAuth\Infrastructure\Console\Command\Client\CreateClientCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Helper\TestEventIdProvider;

use function preg_replace;

/**
 * Test CreateClientCommandTest.
 *
 * @category Console Command Tests
 */
#[CoversClass(className: CreateClientCommand::class)]
final class CreateClientCommandTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testExecuteCreatesClientWithProvidedIdAndSecret(): void
  {
    $clientRepository = $this->createMock(ClientRepositoryPort::class);
    $clientRepository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(Client::class));

    $uuidFactory = $this->createUuidFactory('123e4567-e89b-12d3-a456-426614174000');

    $command = new CreateClientCommand(
      clientRepository: $clientRepository,
      uuidFactory: $uuidFactory,
      eventIdProvider: new TestEventIdProvider(),
    );

    $tester = new CommandTester($command);

    $tester->execute([
      'name' => 'Test Client',
      '--id' => '123e4567-e89b-12d3-a456-426614174001',
      '--secret' => 'plain-secret',
      '--redirect-uri' => ['https://example.com/callback'],
      '--grant-type' => ['CLIENT_CREDENTIALS'],
      '--scope' => ['openid', 'read'],
    ]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString(
      'OAuth2 client created successfully',
      $tester->getDisplay(),
    );
  }

  #[Test]
  public function testExecuteWarnsOnInvalidCustomId(): void
  {
    $clientRepository = $this->createMock(ClientRepositoryPort::class);
    $clientRepository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(Client::class));

    $uuidFactory = $this->createUuidFactory('123e4567-e89b-12d3-a456-426614174000');

    $command = new CreateClientCommand(
      clientRepository: $clientRepository,
      uuidFactory: $uuidFactory,
      eventIdProvider: new TestEventIdProvider(),
    );

    $tester = new CommandTester($command);

    $tester->execute([
      'name' => 'Test Client',
      '--id' => 'not-a-uuid',
      '--secret' => 'plain-secret',
      '--grant-type' => ['CLIENT_CREDENTIALS'],
      '--scope' => ['openid'],
    ]);

    $display = $tester->getDisplay();
    $normalizedDisplay = preg_replace('/\s+/', ' ', $display) ?? $display;

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString(
      'Provided ID "not-a-uuid" is not a valid UUID.',
      $display,
    );
    self::assertStringContainsString(
      'Generated: 123e4567-e89b-12d3-a456-426614174000',
      $normalizedDisplay,
    );
  }

  private function createUuidFactory(string $uuid): UuidFactory
  {
    $generator = new class ($uuid) implements UuidGeneratorPort {
      public function __construct(private string $uuid)
      {
      }

      public function generate(): string
      {
        return $this->uuid;
      }
    };

    return new UuidFactory(generator: $generator);
  }
  // #endregion
}
