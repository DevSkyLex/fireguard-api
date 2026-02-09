<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Presentation\Api\Processor\Tenant;

use ApiPlatform\Metadata\Post;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Tenant\Application\UseCase\Command\Tenant\CreateTenant\{CreateTenantCommand, CreateTenantResult};
use Tenant\Presentation\Api\Dto\Input\Tenant\TenantInput;
use Tenant\Presentation\Api\Dto\Output\Tenant\TenantOutput;
use Tenant\Presentation\Api\Processor\Tenant\CreateTenantProcessor;

/**
 * Test CreateTenantProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: CreateTenantProcessor::class)]
final class CreateTenantProcessorTest extends TestCase
{
  // #region Methods
  /**
   * Method testProcessCreatesTenantAndReturnsOutput.
   *
   * Test that process creates a tenant and returns proper output.
   */
  #[Test]
  public function testProcessCreatesTenantAndReturnsOutput(): void
  {
    $tenantId = '123e4567-e89b-12d3-a456-426614174000';

    /** @var CommandBusPort&\PHPUnit\Framework\MockObject\MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(function (CreateTenantCommand $command): bool {
        if (null === $command->settings) {
          return false;
        }

        return 'Test Tenant' === $command->name
          && 7200 === $command->settings->accessTokenTtl
          && 172800 === $command->settings->refreshTokenTtl
          && true === $command->settings->requirePkce
          && false === $command->settings->allowPublicClients
          && ['openid', 'profile'] === $command->settings->allowedScopes
          && 'https://issuer.example.com' === $command->settings->customIssuer;
      }))
      ->willReturn(new CreateTenantResult(tenantId: $tenantId));

    // Mock Security with authenticated user
    $user = $this->createMock(UserInterface::class);
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($user);

    $input = new TenantInput();
    $input->name = 'Test Tenant';
    $input->accessTokenTtl = 7200;
    $input->refreshTokenTtl = 172800;
    $input->requirePkce = true;
    $input->allowPublicClients = false;
    $input->allowedScopes = ['openid', 'profile'];
    $input->customIssuer = 'https://issuer.example.com';

    $processor = new CreateTenantProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: [],
      context: [],
    );

    self::assertInstanceOf(TenantOutput::class, $output);
    self::assertEquals($tenantId, $output->id);
    self::assertEquals('Test Tenant', $output->name);
    self::assertTrue($output->isActive);
    self::assertEquals(7200, $output->accessTokenTtl);
    self::assertTrue($output->requirePkce);
    self::assertFalse($output->allowPublicClients);
    self::assertSame(['openid', 'profile'], $output->allowedScopes);
    self::assertSame('https://issuer.example.com', $output->customIssuer);
  }

  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new CreateTenantProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      security: $security,
    );

    $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);

    $processor->process(
      data: new TenantInput(),
      operation: new Post(),
      uriVariables: [],
      context: [],
    );
  }
  // #endregion
}
