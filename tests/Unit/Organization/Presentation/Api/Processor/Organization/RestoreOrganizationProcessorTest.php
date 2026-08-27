<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\RestoreOrganization\RestoreOrganizationCommand;
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Presentation\Api\Processor\Organization\RestoreOrganizationProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

/**
 * Test RestoreOrganizationProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RestoreOrganizationProcessor::class)]
final class RestoreOrganizationProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $this->expectException(AccessDeniedHttpException::class);

    $this->createProcessor(
      security: $security,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      commandBus: $this->createStub(CommandBusPort::class),
    )->process(null, new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenIdentifierMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor(
      security: $this->securityFor(isPlatformAdmin: false),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      commandBus: $this->createStub(CommandBusPort::class),
    )->process(null, new Post(), []);
  }

  #[Test]
  public function testAMemberHoldingSettingsWriteRestores(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(RestoreOrganizationCommand::class));

    $this->createProcessor(
      security: $this->securityFor(isPlatformAdmin: false),
      authorization: $authorization,
      commandBus: $commandBus,
    )->process(null, new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testAPlatformAdministratorRestoresWithoutAnOrganizationPermission(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('hasPermission');

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch');

    $this->createProcessor(
      security: $this->securityFor(isPlatformAdmin: true),
      authorization: $authorization,
      commandBus: $commandBus,
    )->process(null, new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testAnArchivedOrganizationIsNotReopenedBySelfService(): void
  {
    // Archiving is terminal: entitlement inside the organization is not enough.
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('platform administrator');

    $this->createProcessor(
      security: $this->securityFor(isPlatformAdmin: false),
      authorization: $authorization,
      commandBus: $commandBus,
      status: 'archived',
    )->process(null, new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testAPlatformAdministratorReopensAnArchivedOrganization(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch');

    $this->createProcessor(
      security: $this->securityFor(isPlatformAdmin: true),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      commandBus: $commandBus,
      status: 'archived',
    )->process(null, new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testASuspendedOrganizationKeepsItsSelfServiceRestore(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch');

    $this->createProcessor(
      security: $this->securityFor(isPlatformAdmin: false),
      authorization: $authorization,
      commandBus: $commandBus,
      status: 'suspended',
    )->process(null, new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testANonAdminWithoutSettingsWriteIsRefused(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(AccessDeniedHttpException::class);

    $this->createProcessor(
      security: $this->securityFor(isPlatformAdmin: false),
      authorization: $authorization,
      commandBus: $commandBus,
    )->process(null, new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  private function createProcessor(
    Security $security,
    OrganizationAuthorizationPort $authorization,
    CommandBusPort $commandBus,
    string $status = 'active',
  ): RestoreOrganizationProcessor {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($this->organizationResult($status));

    return new RestoreOrganizationProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );
  }

  private function securityFor(bool $isPlatformAdmin): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: $isPlatformAdmin ? ['ROLE_USER', 'ROLE_ADMIN'] : ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));
    $security->method('isGranted')->willReturn($isPlatformAdmin);

    return $security;
  }

  private function organizationResult(string $status): GetOrganizationResult
  {
    return new GetOrganizationResult(
      id: self::ORGANIZATION_ID,
      name: 'Fireguard Test',
      slug: 'fireguard-nice',
      ownerUserId: self::USER_ID,
      createdByUserId: self::USER_ID,
      status: $status,
      isActive: 'active' === $status,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      planId: null,
      planName: null,
    );
  }
}
