<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Onboarding\Application\Port\Outbound\Internal\OrganizationOnboardingSessionRepositoryPort;
use Onboarding\Application\Service\OrganizationOnboardingFlowService;
use Onboarding\Presentation\Api\Dto\Input\Onboarding\StartOrganizationOnboardingInput;
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Processor\Onboarding\StartOrganizationOnboardingProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort, UuidGeneratorPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(StartOrganizationOnboardingProcessor::class)]
final class StartOrganizationOnboardingProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new StartOrganizationOnboardingProcessor(
      flowService: $this->buildFlowService(),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new StartOrganizationOnboardingInput(), new Post());
  }

  #[Test]
  public function testProcessStartsFlowWithoutReset(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440201';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    /**
     * @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->expects(self::never())->method('deleteByUserId');
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->method('save');

    $uuidFactory = $this->createStub(UuidGeneratorPort::class);
    $uuidFactory->method('generate')->willReturn('550e8400-e29b-41d4-a716-446655440299');

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new PaginatedResult(items: [], total: 0, limit: 100, offset: 0));

    $input = new StartOrganizationOnboardingInput();
    $input->reset = false;

    $processor = new StartOrganizationOnboardingProcessor(
      flowService: $this->buildFlowService(
        sessionRepository: $sessionRepository,
        queryBus: $queryBus,
        uuidFactory: $uuidFactory,
      ),
      security: $security,
    );

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(OrganizationOnboardingOutput::class, $output);
    self::assertSame('organization', $output->flow);
    self::assertSame('in_progress', $output->state);
  }

  #[Test]
  public function testProcessStartsFlowWithReset(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440202';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    /**
     * @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->expects(self::once())
      ->method('deleteByUserId')
      ->with($userId);
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->method('save');

    $uuidFactory = $this->createStub(UuidGeneratorPort::class);
    $uuidFactory->method('generate')->willReturn('550e8400-e29b-41d4-a716-446655440298');

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new PaginatedResult(items: [], total: 0, limit: 100, offset: 0));

    $input = new StartOrganizationOnboardingInput();
    $input->reset = true;

    $processor = new StartOrganizationOnboardingProcessor(
      flowService: $this->buildFlowService(
        sessionRepository: $sessionRepository,
        queryBus: $queryBus,
        uuidFactory: $uuidFactory,
      ),
      security: $security,
    );

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(OrganizationOnboardingOutput::class, $output);
    self::assertSame('in_progress', $output->state);
  }

  private function buildFlowService(
    ?OrganizationOnboardingSessionRepositoryPort $sessionRepository = null,
    ?QueryBusPort $queryBus = null,
    ?CommandBusPort $commandBus = null,
    ?UuidGeneratorPort $uuidFactory = null,
    ?TransactionManagerPort $transactionManager = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): OrganizationOnboardingFlowService {
    if (null === $transactionManager) {
      $transactionManager = $this->createStub(TransactionManagerPort::class);
      $transactionManager->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());
    }

    return new OrganizationOnboardingFlowService(
      sessionRepository: $sessionRepository ?? $this->createStub(OrganizationOnboardingSessionRepositoryPort::class),
      organizationDirectory: new \Organization\Infrastructure\Adapter\Onboarding\OrganizationOnboardingDirectoryAdapter($queryBus ?? $this->createStub(QueryBusPort::class), $commandBus ?? $this->createStub(CommandBusPort::class)),
      facilities: new \Facility\Infrastructure\Adapter\Onboarding\FacilityAvailabilityAdapter($queryBus ?? $this->createStub(QueryBusPort::class)),
      equipment: new \Equipment\Infrastructure\Adapter\Onboarding\EquipmentAvailabilityAdapter($queryBus ?? $this->createStub(QueryBusPort::class)),
      uuidFactory: $uuidFactory ?? $this->createStub(UuidGeneratorPort::class),
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
    );
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
}
