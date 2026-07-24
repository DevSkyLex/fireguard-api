<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\{
  ChangeOrganizationPlanCommand,
  ChangeOrganizationPlanResult
};
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Domain\Exception\OrganizationPlanUsageExceededException;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use Organization\Presentation\Api\Dto\Input\Organization\ChangeOrganizationPlanInput;
use Organization\Presentation\Api\Processor\Organization\ChangeOrganizationPlanProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(ChangeOrganizationPlanProcessor::class)]
final class ChangeOrganizationPlanProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function testProcessThrowsConflictWhenUsageExceedsPlanLimits(): void
  {
    $input = new ChangeOrganizationPlanInput();
    $input->planId = self::PLAN_ID;

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(OrganizationPlanUsageExceededException::withViolations(
        ['members' => ['usage' => 60, 'limit' => 50]],
      ));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('Current usage exceeds the selected plan limits');

    $processor->process($input, new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsConflictWhenUsageExceededIsWrappedInMessengerRuntimeException(): void
  {
    $input = new ChangeOrganizationPlanInput();
    $input->planId = self::PLAN_ID;

    $usageConflict = OrganizationPlanUsageExceededException::withViolations(
      ['members' => ['usage' => 60, 'limit' => 50]],
    );
    $handlerFailure = new HandlerFailedException(
      new Envelope(new ChangeOrganizationPlanCommand(organizationId: self::ORGANIZATION_ID, planId: self::PLAN_ID)),
      [$usageConflict],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('Current usage exceeds the selected plan limits');

    $processor->process($input, new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessForwardsAcknowledgeOveruseToTheCommand(): void
  {
    $input = new ChangeOrganizationPlanInput();
    $input->planId = self::PLAN_ID;
    $input->acknowledgeOveruse = true;

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $command): bool => $command instanceof ChangeOrganizationPlanCommand
        && self::ORGANIZATION_ID === $command->organizationId
        && self::PLAN_ID === $command->planId
        && true === $command->acknowledgeOveruse))
      ->willReturn(new ChangeOrganizationPlanResult(
        organizationId: self::ORGANIZATION_ID,
        planId: self::PLAN_ID,
      ));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($this->organizationResult());

    $processor = $this->createProcessor($commandBus, $queryBus);

    $output = $processor->process($input, new Patch(), ['id' => self::ORGANIZATION_ID]);

    self::assertSame(self::ORGANIZATION_ID, $output->id);
    self::assertSame(self::PLAN_ID, $output->planId);
  }

  private function createProcessor(CommandBusPort $commandBus, ?QueryBusPort $queryBus = null): ChangeOrganizationPlanProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->defaultPlan());

    return new ChangeOrganizationPlanProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus ?? $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      planRepository: $planRepository,
      security: $security,
    );
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }

  private function defaultPlan(): Plan
  {
    return Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('free'),
      name: 'Free',
      limits: ['members' => 50],
      isDefault: true,
    );
  }

  private function organizationResult(): GetOrganizationResult
  {
    return new GetOrganizationResult(
      id: self::ORGANIZATION_ID,
      name: 'Fireguard Test',
      slug: 'fireguard-test',
      ownerUserId: self::USER_ID,
      createdByUserId: self::USER_ID,
      status: 'active',
      isActive: true,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      planId: self::PLAN_ID,
      planName: 'Free',
    );
  }
}
