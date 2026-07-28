<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Presentation\Api\Processor;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleView;
use Maintenance\Application\UseCase\Command\Schedule\SetMaintenanceScheduleOverride\{
  SetMaintenanceScheduleOverrideCommand,
  SetMaintenanceScheduleOverrideResult
};
use Maintenance\Domain\Exception\MaintenanceNotFoundException;
use Maintenance\Presentation\Api\Dto\Input\UpdateMaintenanceScheduleInput;
use Maintenance\Presentation\Api\Dto\Output\MaintenanceScheduleOutput;
use Maintenance\Presentation\Api\Factory\MaintenanceScheduleOutputFactory;
use Maintenance\Presentation\Api\Processor\MaintenanceScheduleProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test MaintenanceScheduleProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceScheduleProcessor::class)]
final class MaintenanceScheduleProcessorTest extends TestCase
{
  private const string SCHEDULE_ID = 'schedule-1';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  #[Test]
  public function testProcessDispatchesTheOverrideCommandAndMapsTheOutput(): void
  {
    $security = $this->securityWithUser();

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (SetMaintenanceScheduleOverrideCommand $command): bool => self::USER_ID === $command->userId
        && self::SCHEDULE_ID === $command->scheduleId
        && 'P90D' === $command->intervalOverride))
      ->willReturn(new SetMaintenanceScheduleOverrideResult($this->view()));

    $processor = new MaintenanceScheduleProcessor($commandBus, new MaintenanceScheduleOutputFactory(), $security);

    $input = new UpdateMaintenanceScheduleInput();
    $input->intervalOverride = 'P90D';

    $output = $processor->process($input, new Patch(), ['id' => self::SCHEDULE_ID]);

    self::assertInstanceOf(MaintenanceScheduleOutput::class, $output);
    self::assertSame(self::SCHEDULE_ID, $output->id);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissing(): void
  {
    $processor = new MaintenanceScheduleProcessor(
      $this->createStub(CommandBusPort::class),
      new MaintenanceScheduleOutputFactory(),
      $this->securityWithUser(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new UpdateMaintenanceScheduleInput(), new Patch(), []);
  }

  #[Test]
  public function testProcessMapsNotFoundExceptionToHttpNotFound(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MaintenanceNotFoundException::withId(self::SCHEDULE_ID));

    $processor = new MaintenanceScheduleProcessor($commandBus, new MaintenanceScheduleOutputFactory(), $this->securityWithUser());

    $this->expectException(NotFoundHttpException::class);

    $processor->process(new UpdateMaintenanceScheduleInput(), new Patch(), ['id' => self::SCHEDULE_ID]);
  }

  #[Test]
  public function testProcessLeavesAnUnrecognisedFailureUnmapped(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new RuntimeException('database is down'));

    $processor = new MaintenanceScheduleProcessor($commandBus, new MaintenanceScheduleOutputFactory(), $this->securityWithUser());

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('database is down');

    $processor->process(new UpdateMaintenanceScheduleInput(), new Patch(), ['id' => self::SCHEDULE_ID]);
  }

  #[Test]
  public function testProcessThrowsBadRequestForAnUnexpectedBody(): void
  {
    $processor = new MaintenanceScheduleProcessor(
      $this->createStub(CommandBusPort::class),
      new MaintenanceScheduleOutputFactory(),
      $this->securityWithUser(),
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid request body.');

    $processor->process(new stdClass(), new Patch(), ['id' => self::SCHEDULE_ID]);
  }

  #[Test]
  public function testProcessThrowsAccessDeniedWhenUnauthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new MaintenanceScheduleProcessor(
      $this->createStub(CommandBusPort::class),
      new MaintenanceScheduleOutputFactory(),
      $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $processor->process(new UpdateMaintenanceScheduleInput(), new Patch(), ['id' => self::SCHEDULE_ID]);
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }

  private function view(): MaintenanceScheduleView
  {
    return new MaintenanceScheduleView(
      id: self::SCHEDULE_ID,
      organizationId: '550e8400-e29b-41d4-a716-446655440001',
      equipmentId: '550e8400-e29b-41d4-a716-446655440002',
      facilityId: null,
      equipmentType: 'fire_extinguisher',
      intervalOverride: 'P90D',
      lastInspectionClosedAt: new DateTimeImmutable('2026-01-01'),
      nextDueAt: new DateTimeImmutable('2026-04-01'),
      dueStatus: 'up_to_date',
      lastRemindedAt: null,
      remindedFor: null,
      createdAt: new DateTimeImmutable('2026-01-01'),
      updatedAt: new DateTimeImmutable('2026-01-01'),
    );
  }
}
