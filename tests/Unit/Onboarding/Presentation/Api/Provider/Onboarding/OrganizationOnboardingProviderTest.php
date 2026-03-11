<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Presentation\Api\Provider\Onboarding;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Onboarding\Application\Port\Outbound\OrganizationOnboardingSessionRepositoryPort;
use Onboarding\Application\Service\OrganizationOnboardingFlowService;
use Onboarding\Presentation\Api\Provider\Onboarding\OrganizationOnboardingProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(OrganizationOnboardingProvider::class)]
final class OrganizationOnboardingProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new OrganizationOnboardingProvider(
      flowService: $this->createUnusedFlowService(),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get());
  }

  #[Test]
  public function testProvideDelegatesToFlowService(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655441622';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    $provider = new OrganizationOnboardingProvider(
      flowService: $this->createFlowServiceForNoOrganization(),
      security: $security,
    );

    $output = $provider->provide(new Get());

    self::assertSame('in_progress', $output->state);
    self::assertSame('create_organization', $output->nextStep);
    self::assertCount(2, $output->steps);
    self::assertSame('create_organization', $output->steps[0]->key);
    self::assertSame('invite_members', $output->steps[1]->key);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }

  private function createFlowServiceForNoOrganization(): OrganizationOnboardingFlowService
  {
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->expects(self::once())
      ->method('findByUserId')
      ->willReturn(null);
    $sessionRepository->expects(self::once())
      ->method('save');

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 100, offset: 0));

    $commandBus = $this->createMock(CommandBusPort::class);

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('generateRaw')
      ->willReturn('550e8400-e29b-41d4-a716-446655441699');

    return new OrganizationOnboardingFlowService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      commandBus: $commandBus,
      uuidFactory: $uuidFactory,
      transactionManager: $this->createMock(TransactionManagerPort::class),
      eventDispatcher: $this->createMock(EventDispatcherInterface::class),
    );
  }

  private function createUnusedFlowService(): OrganizationOnboardingFlowService
  {
    return new OrganizationOnboardingFlowService(
      sessionRepository: $this->createMock(OrganizationOnboardingSessionRepositoryPort::class),
      queryBus: $this->createMock(QueryBusPort::class),
      commandBus: $this->createMock(CommandBusPort::class),
      uuidFactory: $this->createMock(UuidFactory::class),
      transactionManager: $this->createMock(TransactionManagerPort::class),
      eventDispatcher: $this->createMock(EventDispatcherInterface::class),
    );
  }
}
