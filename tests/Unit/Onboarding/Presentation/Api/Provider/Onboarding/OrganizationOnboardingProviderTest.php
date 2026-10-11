<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Presentation\Api\Provider\Onboarding;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Onboarding\Application\Port\Outbound\Internal\OrganizationOnboardingSessionRepositoryPort;
use Onboarding\Application\Service\OrganizationOnboardingFlowService;
use Onboarding\Presentation\Api\Provider\Onboarding\OrganizationOnboardingProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort, UuidGeneratorPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
    self::assertCount(5, $output->steps);
    self::assertSame('create_organization', $output->steps[0]->key);
    self::assertSame('select_plan', $output->steps[1]->key);
    self::assertSame('invite_members', $output->steps[2]->key);
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
    $queryBus->expects(self::exactly(2))
      ->method('ask')
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 100, offset: 0));

    $commandBus = $this->createStub(CommandBusPort::class);

    $uuidFactory = $this->createMock(UuidGeneratorPort::class);
    $uuidFactory->expects(self::once())
      ->method('generate')
      ->willReturn('550e8400-e29b-41d4-a716-446655441699');

    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());

    return new OrganizationOnboardingFlowService(
      sessionRepository: $sessionRepository,
      organizationDirectory: new \Organization\Infrastructure\Adapter\Onboarding\OrganizationOnboardingDirectoryAdapter($queryBus, $commandBus),
      facilities: new \Facility\Infrastructure\Adapter\Onboarding\FacilityAvailabilityAdapter($queryBus),
      equipment: new \Equipment\Infrastructure\Adapter\Onboarding\EquipmentAvailabilityAdapter($queryBus),
      uuidFactory: $uuidFactory,
      transactionManager: $transactions,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );
  }

  private function createUnusedFlowService(): OrganizationOnboardingFlowService
  {
    return new OrganizationOnboardingFlowService(
      sessionRepository: $this->createStub(OrganizationOnboardingSessionRepositoryPort::class),
      organizationDirectory: new \Organization\Infrastructure\Adapter\Onboarding\OrganizationOnboardingDirectoryAdapter($this->createStub(QueryBusPort::class), $this->createStub(CommandBusPort::class)),
      facilities: new \Facility\Infrastructure\Adapter\Onboarding\FacilityAvailabilityAdapter($this->createStub(QueryBusPort::class)),
      equipment: new \Equipment\Infrastructure\Adapter\Onboarding\EquipmentAvailabilityAdapter($this->createStub(QueryBusPort::class)),
      uuidFactory: $this->createStub(UuidGeneratorPort::class),
      transactionManager: $this->createStub(TransactionManagerPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );
  }
}
