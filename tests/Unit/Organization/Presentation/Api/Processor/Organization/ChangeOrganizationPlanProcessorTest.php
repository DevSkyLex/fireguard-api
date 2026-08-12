<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\{
  ChangeOrganizationPlanCommand,
  ChangeOrganizationPlanResult
};
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationQuery, GetOrganizationResult};
use Organization\Domain\Exception\{
  OrganizationNotFoundException,
  OrganizationPlanUsageExceededException,
  PlanNotFoundException
};
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationSettings, PlanId, PlanKey};
use Organization\Presentation\Api\Dto\Input\Organization\ChangeOrganizationPlanInput;
use Organization\Presentation\Api\Processor\Organization\ChangeOrganizationPlanProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException
};
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

  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new ChangeOrganizationPlanProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      planRepository: $this->createStub(PlanRepositoryPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $processor->process($this->planInput(), new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenOrganizationIdentifierIsMissing(): void
  {
    $processor = $this->createProcessor($this->createStub(CommandBusPort::class));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Organization identifier is required.');

    $processor->process($this->planInput(), new Patch(), []);
  }

  #[Test]
  public function testProcessThrowsWhenOrganizationIdentifierIsNotAString(): void
  {
    $processor = $this->createProcessor($this->createStub(CommandBusPort::class));

    $this->expectException(BadRequestHttpException::class);

    $processor->process($this->planInput(), new Patch(), ['id' => 42]);
  }

  #[Test]
  public function testProcessThrowsWhenSettingsWritePermissionIsMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.settings.write')
      ->willReturn(false);

    $processor = new ChangeOrganizationPlanProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      planRepository: $this->createStub(PlanRepositoryPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.settings.write permission.');

    $processor->process($this->planInput(), new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenPlanDoesNotExist(): void
  {
    $processor = $this->createProcessorWithPlan($this->createStub(CommandBusPort::class), null);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Plan not found.');

    $processor->process($this->planInput(), new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsConflictWhenPlanIsNotTheDefaultOne(): void
  {
    $paidPlan = Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('pro'),
      name: 'Pro',
      limits: ['members' => 500],
      isDefault: false,
    );

    $processor = $this->createProcessorWithPlan($this->createStub(CommandBusPort::class), $paidPlan);

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('Paid plans must be subscribed to through billing.');

    $processor->process($this->planInput(), new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenOrganizationIsMissing(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationNotFoundException::withId(self::ORGANIZATION_ID));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(NotFoundHttpException::class);

    $processor->process($this->planInput(), new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenCommandArgumentIsInvalid(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(new InvalidArgumentException('Plan identifier is malformed.'));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Plan identifier is malformed.');

    $processor->process($this->planInput(), new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenPlanNotFoundIsWrappedInMessengerRuntimeException(): void
  {
    $handlerFailure = new HandlerFailedException(
      new Envelope(new ChangeOrganizationPlanCommand(organizationId: self::ORGANIZATION_ID, planId: self::PLAN_ID)),
      [PlanNotFoundException::withId(self::PLAN_ID)],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(NotFoundHttpException::class);

    $processor->process($this->planInput(), new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenInvalidArgumentIsWrappedInMessengerRuntimeException(): void
  {
    $handlerFailure = new HandlerFailedException(
      new Envelope(new ChangeOrganizationPlanCommand(organizationId: self::ORGANIZATION_ID, planId: self::PLAN_ID)),
      [new InvalidArgumentException('Plan identifier is malformed.')],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Plan identifier is malformed.');

    $processor->process($this->planInput(), new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessRethrowsMessengerFailureWhenNoDomainExceptionIsRecognised(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap(new RuntimeException('Bus transport is down.')));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(MessengerRuntimeException::class);

    $processor->process($this->planInput(), new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenRefreshedOrganizationCannotBeRead(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new ChangeOrganizationPlanResult(
      organizationId: self::ORGANIZATION_ID,
      planId: self::PLAN_ID,
    ));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(OrganizationNotFoundException::withId(self::ORGANIZATION_ID));

    $processor = $this->createProcessor($commandBus, $queryBus);

    $this->expectException(NotFoundHttpException::class);

    $processor->process($this->planInput(), new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenTheRefreshedReadFailsWrappedInMessengerRuntimeException(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new ChangeOrganizationPlanResult(
      organizationId: self::ORGANIZATION_ID,
      planId: self::PLAN_ID,
    ));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new GetOrganizationQuery(self::ORGANIZATION_ID)),
      [OrganizationNotFoundException::withId(self::ORGANIZATION_ID)],
    )));

    $processor = $this->createProcessor($commandBus, $queryBus);

    $this->expectException(NotFoundHttpException::class);

    $processor->process($this->planInput(), new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessMapsEveryOrganizationFieldOntoTheOutput(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new ChangeOrganizationPlanResult(
      organizationId: self::ORGANIZATION_ID,
      planId: self::PLAN_ID,
    ));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetOrganizationResult(
      id: self::ORGANIZATION_ID,
      name: 'Fireguard Test',
      slug: 'fireguard-test',
      ownerUserId: self::USER_ID,
      createdByUserId: self::USER_ID,
      status: 'active',
      isActive: true,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      description: 'Test organization',
      logoUrl: 'https://cdn.example.test/logo.png',
      memberCount: 12,
      settings: OrganizationSettings::default(),
      planId: self::PLAN_ID,
      planName: 'Free',
      country: 'FR',
      legalType: 'limited_liability_company',
      legalName: 'Fireguard Test SARL',
      registrationNumber: 'RCS PARIS 812345678',
      vatNumber: 'FR12345678901',
    ));

    $processor = $this->createProcessor($commandBus, $queryBus);

    $output = $processor->process($this->planInput(), new Patch(), ['id' => self::ORGANIZATION_ID]);

    self::assertSame('fireguard-test', $output->slug);
    self::assertSame('Test organization', $output->description);
    self::assertSame('https://cdn.example.test/logo.png', $output->logoUrl);
    self::assertSame(12, $output->memberCount);
    self::assertSame('FR', $output->country);
    self::assertSame('limited_liability_company', $output->legalType);
    self::assertSame('Fireguard Test SARL', $output->legalName);
    self::assertSame('RCS PARIS 812345678', $output->registrationNumber);
    self::assertSame('FR12345678901', $output->vatNumber);
    self::assertNotNull($output->settings);
    self::assertSame('2026-01-01T00:00:00+00:00', $output->createdAt);
    self::assertSame('2026-02-01T00:00:00+00:00', $output->updatedAt);
  }

  private function planInput(): ChangeOrganizationPlanInput
  {
    $input = new ChangeOrganizationPlanInput();
    $input->planId = self::PLAN_ID;

    return $input;
  }

  private function createProcessor(CommandBusPort $commandBus, ?QueryBusPort $queryBus = null): ChangeOrganizationPlanProcessor
  {
    return $this->createProcessorWithPlan($commandBus, $this->defaultPlan(), $queryBus);
  }

  private function createProcessorWithPlan(CommandBusPort $commandBus, ?Plan $plan, ?QueryBusPort $queryBus = null): ChangeOrganizationPlanProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($plan);

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
