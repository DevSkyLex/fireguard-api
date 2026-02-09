<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Presentation\Api\Processor\Tenant;

use ApiPlatform\Metadata\Operation;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Tenant\Application\UseCase\Command\Tenant\UpdateTenant\UpdateTenantCommand;
use Tenant\Application\UseCase\Query\Tenant\GetTenant\{GetTenantQuery, GetTenantResult};
use Tenant\Domain\ValueObject\TenantSettings;
use Tenant\Presentation\Api\Dto\Input\Tenant\TenantInput;
use Tenant\Presentation\Api\Dto\Output\Tenant\TenantOutput;
use Tenant\Presentation\Api\Processor\Tenant\UpdateTenantProcessor;

/**
 * Test UpdateTenantProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: UpdateTenantProcessor::class)]
final class UpdateTenantProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenIdIsNotString(): void
  {
    $processor = new UpdateTenantProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $this->createMock(QueryBusPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $processor->process(
      data: new TenantInput(),
      operation: $this->createMock(Operation::class),
      uriVariables: ['id' => []],
    );
  }

  #[Test]
  public function testProcessDispatchesAndReturnsOutput(): void
  {
    $input = new TenantInput();
    $input->name = 'Updated Tenant';
    $input->accessTokenTtl = 7200;
    $input->refreshTokenTtl = 172800;
    $input->requirePkce = true;
    $input->allowPublicClients = true;
    $input->allowedScopes = ['openid', 'profile'];
    $input->customIssuer = 'https://issuer.example.com';

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static function (UpdateTenantCommand $command): bool {
          if (null === $command->settings) {
            return false;
          }

          return 'tenant-123' === $command->tenantId
            && 'Updated Tenant' === $command->name
            && 7200 === $command->settings->accessTokenTtl
            && 172800 === $command->settings->refreshTokenTtl
            && true === $command->settings->requirePkce
            && true === $command->settings->allowPublicClients
            && ['openid', 'profile'] === $command->settings->allowedScopes
            && 'https://issuer.example.com' === $command->settings->customIssuer;
        },
      ));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetTenantQuery::class))
      ->willReturn(self::createTenantResult());

    $processor = new UpdateTenantProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    $output = $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
      uriVariables: ['id' => 'tenant-123'],
    );

    self::assertInstanceOf(TenantOutput::class, $output);
    self::assertSame('tenant-123', $output->id);
    self::assertSame('Updated Tenant', $output->name);
    self::assertTrue($output->isActive);
    self::assertSame(7200, $output->accessTokenTtl);
    self::assertSame(172800, $output->refreshTokenTtl);
    self::assertTrue($output->requirePkce);
    self::assertTrue($output->allowPublicClients);
    self::assertSame(['openid', 'profile'], $output->allowedScopes);
    self::assertSame('https://issuer.example.com', $output->customIssuer);
  }

  private static function createTenantResult(): GetTenantResult
  {
    return new GetTenantResult(
      tenantId: 'tenant-123',
      name: 'Updated Tenant',
      settings: new TenantSettings(
        accessTokenTtl: 7200,
        refreshTokenTtl: 172800,
        requirePkce: true,
        allowPublicClients: true,
        allowedScopes: ['openid', 'profile'],
        customIssuer: 'https://issuer.example.com',
      ),
      isActive: true,
      createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
