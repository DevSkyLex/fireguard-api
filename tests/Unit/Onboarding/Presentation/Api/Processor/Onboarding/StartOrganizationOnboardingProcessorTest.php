<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Onboarding\Application\Port\Outbound\OrganizationOnboardingSessionRepositoryPort;
use Onboarding\Application\Service\OrganizationOnboardingFlowService;
use Onboarding\Presentation\Api\Dto\Input\Onboarding\StartOrganizationOnboardingInput;
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Processor\Onboarding\StartOrganizationOnboardingProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->expects(self::never())->method('deleteByUserId');
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn('550e8400-e29b-41d4-a716-446655440299');

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

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->expects(self::once())
      ->method('deleteByUserId')
      ->with($userId);
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn('550e8400-e29b-41d4-a716-446655440298');

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
    ?UuidFactory $uuidFactory = null,
    ?TransactionManagerPort $transactionManager = null,
    ?EventDispatcherInterface $eventDispatcher = null,
  ): OrganizationOnboardingFlowService {
    return new OrganizationOnboardingFlowService(
      sessionRepository: $sessionRepository ?? $this->createStub(OrganizationOnboardingSessionRepositoryPort::class),
      queryBus: $queryBus ?? $this->createStub(QueryBusPort::class),
      commandBus: $commandBus ?? $this->createStub(CommandBusPort::class),
      uuidFactory: $uuidFactory ?? $this->createStub(UuidFactory::class),
      transactionManager: $transactionManager ?? $this->createStub(TransactionManagerPort::class),
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherInterface::class),
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
