<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Presentation\Api\Processor;

use ApiPlatform\Metadata\{Delete, Patch, Post};
use DateTimeImmutable;
use Maintenance\Application\Contract\Plan\{MaintenancePlanDetails, MaintenancePlanState};
use Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\{ManageMaintenancePlanCommand, ManageMaintenancePlanResult};
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceNotFoundException};
use Maintenance\Presentation\Api\Dto\Input\{ChangeMaintenancePlanInput, GenerateMaintenancePlanInput};
use Maintenance\Presentation\Api\Dto\Output\{GenerateMaintenancePlanOutput, MaintenancePlanEngineOutput, MaintenancePlanOutput};
use Maintenance\Presentation\Api\Factory\MaintenancePlanOutputFactory;
use Maintenance\Presentation\Api\Operation\MaintenancePlanOperations;
use Maintenance\Presentation\Api\Processor\MaintenancePlanProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use stdClass;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

/**
 * Class MaintenancePlanProcessorTest
 *
 * Verifies explicit write actions, immutable identity and strict date transport.
 *
 * @category Tests
 */
#[CoversClass(MaintenancePlanProcessor::class)]
final class MaintenancePlanProcessorTest extends TestCase
{
  // #region Constants
  /**
   * Constant ORGANIZATION
   */
  private const string ORGANIZATION = '018fa001-1111-7111-8111-111111111111';

  /**
   * Constant ACTOR
   */
  private const string ACTOR = '018fa002-1111-7111-8111-111111111111';

  /**
   * Constant PLAN
   */
  private const string PLAN = '018fa003-1111-7111-8111-111111111111';

  /**
   * Constant EQUIPMENT
   */
  private const string EQUIPMENT = '018fa004-1111-7111-8111-111111111111';
  // #endregion

  // #region Methods
  /**
   * Method testCreateDispatchesPreparedPlanAndMapsOutput
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCreateDispatchesPreparedPlanAndMapsOutput(): void
  {
    $input = new ChangeMaintenancePlanInput();
    $input->equipmentId = self::EQUIPMENT;
    $input->name = 'Monthly control';
    $input->operationKind = 'control';
    $input->interval = 'P1M';
    $input->anchorAt = '2026-01-31T08:00:00+02:00';
    $input->nextDueAt = '2026-02-28T08:00:00+02:00';
    $input->active = false;
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageMaintenancePlanCommand $command): bool => 'create' === $command->action && self::ORGANIZATION === $command->organizationId && self::ACTOR === $command->actorUserId && null === $command->planId && self::EQUIPMENT === $command->equipmentId && 'Monthly control' === $command->name && 'control' === $command->operationKind && 'P1M' === $command->interval && '2026-01-31T08:00:00+02:00' === $command->anchorAt?->format('c') && '2026-02-28T08:00:00+02:00' === $command->nextDueAt?->format('c') && false === $command->active && false === $command->retry))->willReturn(new ManageMaintenancePlanResult(details: $this->details()));
    $processor = new MaintenancePlanProcessor($bus, $this->actor(), new MaintenancePlanOutputFactory());

    $output = $processor->process($input, new Post(name: MaintenancePlanOperations::CREATE), ['organizationId' => self::ORGANIZATION]);

    self::assertInstanceOf(MaintenancePlanOutput::class, $output);
    self::assertSame(self::PLAN, $output->id);
    self::assertFalse($output->active);
    self::assertSame('Monthly control', $output->name);
  }

  /**
   * Method testPatchDispatchesOnlyProvidedMutableFields
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPatchDispatchesOnlyProvidedMutableFields(): void
  {
    $input = new ChangeMaintenancePlanInput();
    $input->name = 'Updated service';
    $input->interval = 'P2M';
    $input->anchorAt = '2026-01-31T08:00:00Z';
    $input->nextDueAt = '2026-03-31T08:00:00Z';
    $input->active = true;
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageMaintenancePlanCommand $command): bool => 'update' === $command->action && self::PLAN === $command->planId && null === $command->equipmentId && null === $command->operationKind && 'Updated service' === $command->name && 'P2M' === $command->interval && '2026-01-31T08:00:00+00:00' === $command->anchorAt?->format('c') && '2026-03-31T08:00:00+00:00' === $command->nextDueAt?->format('c') && true === $command->active))->willReturn(new ManageMaintenancePlanResult(details: $this->details()));
    $processor = new MaintenancePlanProcessor($bus, $this->actor(), new MaintenancePlanOutputFactory());

    $output = $processor->process($input, new Patch(name: MaintenancePlanOperations::UPDATE), ['organizationId' => self::ORGANIZATION, 'id' => self::PLAN]);

    self::assertInstanceOf(MaintenancePlanOutput::class, $output);
    self::assertSame(self::PLAN, $output->id);
  }

  /**
   * Method testGenerationDispatchesExplicitRetryAndMapsReceipt
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testGenerationDispatchesExplicitRetryAndMapsReceipt(): void
  {
    $input = new GenerateMaintenancePlanInput();
    $input->retry = true;
    $input->name = 'Repair attempt';
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageMaintenancePlanCommand $command): bool => 'generate' === $command->action && self::ORGANIZATION === $command->organizationId && self::ACTOR === $command->actorUserId && self::PLAN === $command->planId && true === $command->retry && 'Repair attempt' === $command->name && null === $command->equipmentId && null === $command->anchorAt))->willReturn(new ManageMaintenancePlanResult(occurrenceId: 'occurrence-1', interventionId: 'intervention-1', number: 42, workItemsCount: 1, replayed: true));
    $processor = new MaintenancePlanProcessor($bus, $this->actor(), new MaintenancePlanOutputFactory());

    $output = $processor->process($input, new Post(name: MaintenancePlanOperations::GENERATE), ['organizationId' => self::ORGANIZATION, 'id' => self::PLAN]);

    self::assertInstanceOf(GenerateMaintenancePlanOutput::class, $output);
    self::assertSame('occurrence-1', $output->occurrenceId);
    self::assertSame('intervention-1', $output->interventionId);
    self::assertSame(42, $output->number);
    self::assertSame(1, $output->workItemsCount);
    self::assertTrue($output->replayed);
  }

  /**
   * Method testDefaultGenerationDoesNotRequestRetry
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testDefaultGenerationDoesNotRequestRetry(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageMaintenancePlanCommand $command): bool => 'generate' === $command->action && false === $command->retry && null === $command->name))->willReturn(new ManageMaintenancePlanResult(occurrenceId: 'occurrence-1', interventionId: 'intervention-1', number: 7, workItemsCount: 1));
    $processor = new MaintenancePlanProcessor($bus, $this->actor(), new MaintenancePlanOutputFactory());

    $output = $processor->process(new GenerateMaintenancePlanInput(), new Post(name: MaintenancePlanOperations::GENERATE), ['organizationId' => self::ORGANIZATION, 'id' => self::PLAN]);

    self::assertInstanceOf(GenerateMaintenancePlanOutput::class, $output);
    self::assertFalse($output->replayed);
  }

  /**
   * Method testEngineActionsAreExplicitAndMapPreparedCount
   *
   * @access public
   *
   * @param string $operation the API metadata name
   * @param string $action the dispatched engine action
   * @param string $mode the returned authority
   *
   * @return void
   */
  #[Test]
  #[DataProvider('engineActions')]
  public function testEngineActionsAreExplicitAndMapPreparedCount(string $operation, string $action, string $mode): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageMaintenancePlanCommand $command): bool => $action === $command->action && self::ORGANIZATION === $command->organizationId && self::ACTOR === $command->actorUserId && null === $command->planId))->willReturn(new ManageMaintenancePlanResult(mode: $mode, preparedCount: 18));
    $processor = new MaintenancePlanProcessor($bus, $this->actor(), new MaintenancePlanOutputFactory());

    $output = $processor->process(null, new Post(name: $operation), ['organizationId' => self::ORGANIZATION]);

    self::assertInstanceOf(MaintenancePlanEngineOutput::class, $output);
    self::assertSame($mode, $output->mode);
    self::assertSame(18, $output->preparedCount);
  }

  /**
   * Method engineActions
   *
   * @access public
   *
   * @return iterable<string, array{string, string, string}> engine actions
   */
  public static function engineActions(): iterable
  {
    yield 'prepare while keeping legacy authority' => [MaintenancePlanOperations::PREPARE, 'prepare_legacy', 'legacy'];
    yield 'activate plan authority' => [MaintenancePlanOperations::ACTIVATE, 'activate', 'plans'];
  }

  /**
   * Method testArchiveDispatchesCommandAndReturnsNoBody
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testArchiveDispatchesCommandAndReturnsNoBody(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageMaintenancePlanCommand $command): bool => 'archive' === $command->action && self::PLAN === $command->planId && self::ORGANIZATION === $command->organizationId && self::ACTOR === $command->actorUserId))->willReturn(new ManageMaintenancePlanResult());
    $processor = new MaintenancePlanProcessor($bus, $this->actor(), new MaintenancePlanOutputFactory());

    self::assertNull($processor->process(null, new Delete(name: MaintenancePlanOperations::ARCHIVE), ['organizationId' => self::ORGANIZATION, 'id' => self::PLAN]));
  }

  /**
   * Method testPatchRejectsEquipmentAndOperationKindChanges
   *
   * @access public
   *
   * @param string $field the immutable field
   *
   * @return void
   */
  #[Test]
  #[DataProvider('immutableFields')]
  public function testPatchRejectsEquipmentAndOperationKindChanges(string $field): void
  {
    $input = new ChangeMaintenancePlanInput();
    if ('equipmentId' === $field) {
      $input->equipmentId = self::EQUIPMENT;
    } else {
      $input->operationKind = 'maintenance';
    }
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $processor = new MaintenancePlanProcessor($bus, $this->actor(), new MaintenancePlanOutputFactory());
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('immutable');

    $processor->process($input, new Patch(name: MaintenancePlanOperations::UPDATE), ['organizationId' => self::ORGANIZATION, 'id' => self::PLAN]);
  }

  /**
   * Method immutableFields
   *
   * @access public
   *
   * @return iterable<string, array{string}> immutable identity fields
   */
  public static function immutableFields(): iterable
  {
    yield 'equipment' => ['equipmentId'];
    yield 'operation' => ['operationKind'];
  }

  /**
   * Method testMalformedCalendarDatesAreRejectedBeforeDispatch
   *
   * @access public
   *
   * @param string $field the calendar field
   * @param string $value the malformed date
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidDates')]
  public function testMalformedCalendarDatesAreRejectedBeforeDispatch(string $field, string $value): void
  {
    $input = new ChangeMaintenancePlanInput();
    if ('anchorAt' === $field) {
      $input->anchorAt = $value;
    } else {
      $input->nextDueAt = $value;
    }
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $processor = new MaintenancePlanProcessor($bus, $this->actor(), new MaintenancePlanOutputFactory());
    $this->expectException(BadRequestHttpException::class);

    $processor->process($input, new Patch(name: MaintenancePlanOperations::UPDATE), ['organizationId' => self::ORGANIZATION, 'id' => self::PLAN]);
  }

  /**
   * Method invalidDates
   *
   * @access public
   *
   * @return iterable<string, array{string, string}> invalid calendar payloads
   */
  public static function invalidDates(): iterable
  {
    yield 'missing offset' => ['anchorAt', '2026-01-31T08:00:00'];
    yield 'date only' => ['nextDueAt', '2026-01-31'];
    yield 'relative date' => ['anchorAt', 'tomorrow'];
    yield 'February overflow' => ['nextDueAt', '2026-02-30T08:00:00Z'];
    yield 'non-leap February' => ['anchorAt', '2026-02-29T08:00:00Z'];
    yield 'invalid month' => ['nextDueAt', '2026-13-01T08:00:00+00:00'];
    yield 'invalid hour' => ['anchorAt', '2026-01-31T25:00:00Z'];
    yield 'trailing content' => ['nextDueAt', '2026-01-31T08:00:00Z garbage'];
  }

  /**
   * Method testChangeActionsRequireTheirInputDto
   *
   * @access public
   *
   * @param string $operation the change operation
   *
   * @return void
   */
  #[Test]
  #[DataProvider('changeActions')]
  public function testChangeActionsRequireTheirInputDto(string $operation): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $processor = new MaintenancePlanProcessor($bus, $this->actor(), new MaintenancePlanOutputFactory());
    $this->expectException(BadRequestHttpException::class);

    $processor->process(new stdClass(), new Post(name: $operation), ['organizationId' => self::ORGANIZATION, 'id' => self::PLAN]);
  }

  /**
   * Method changeActions
   *
   * @access public
   *
   * @return iterable<string, array{string}> input-requiring write operations
   */
  public static function changeActions(): iterable
  {
    yield 'create' => [MaintenancePlanOperations::CREATE];
    yield 'update' => [MaintenancePlanOperations::UPDATE];
  }

  /**
   * Method testUnknownActionDoesNotDispatch
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnknownActionDoesNotDispatch(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $processor = new MaintenancePlanProcessor($bus, $this->actor(), new MaintenancePlanOutputFactory());
    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Post(name: 'unsupported'), ['organizationId' => self::ORGANIZATION]);
  }

  /**
   * Method testUnauthenticatedActorNeverDispatchesWrite
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnauthenticatedActorNeverDispatchesWrite(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $processor = new MaintenancePlanProcessor($bus, $this->actor(null), new MaintenancePlanOutputFactory());
    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new ChangeMaintenancePlanInput(), new Post(name: MaintenancePlanOperations::CREATE), ['organizationId' => self::ORGANIZATION]);
  }

  /**
   * Method testInvalidRouteNeverDispatchesWrite
   *
   * @access public
   *
   * @param array<string, mixed> $variables the invalid route values
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidRoutes')]
  public function testInvalidRouteNeverDispatchesWrite(array $variables): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::never())->method('dispatch');
    $processor = new MaintenancePlanProcessor($bus, $this->actor(), new MaintenancePlanOutputFactory());
    $this->expectException(BadRequestHttpException::class);

    $processor->process(new ChangeMaintenancePlanInput(), new Post(name: MaintenancePlanOperations::CREATE), $variables);
  }

  /**
   * Method invalidRoutes
   *
   * @access public
   *
   * @return iterable<string, array{array<string, mixed>}> invalid route variables
   */
  public static function invalidRoutes(): iterable
  {
    yield 'missing organization' => [[]];
    yield 'non-string organization' => [['organizationId' => 123]];
    yield 'non-string plan' => [['organizationId' => self::ORGANIZATION, 'id' => ['bad']]];
  }

  /**
   * Method testDomainDenialPropagatesForCentralMapping
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testDomainDenialPropagatesForCentralMapping(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->willThrowException(new MaintenanceAccessDeniedException('Missing manage permission.'));
    $processor = new MaintenancePlanProcessor($bus, $this->actor(), new MaintenancePlanOutputFactory());
    $this->expectException(MaintenanceAccessDeniedException::class);

    $processor->process(new GenerateMaintenancePlanInput(), new Post(name: MaintenancePlanOperations::GENERATE), ['organizationId' => self::ORGANIZATION, 'id' => self::PLAN]);
  }

  /**
   * Method testOutsideOrganizationFailurePropagatesForCentralMapping
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testOutsideOrganizationFailurePropagatesForCentralMapping(): void
  {
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->willThrowException(MaintenanceNotFoundException::withId(self::PLAN));
    $processor = new MaintenancePlanProcessor($bus, $this->actor(), new MaintenancePlanOutputFactory());
    $this->expectException(MaintenanceNotFoundException::class);

    $processor->process(null, new Delete(name: MaintenancePlanOperations::ARCHIVE), ['organizationId' => self::ORGANIZATION, 'id' => self::PLAN]);
  }

  /**
   * Method actor
   *
   * @access private
   *
   * @param ?string $id the authenticated user identifier
   *
   * @return CurrentActorPort the mocked actor
   */
  private function actor(?string $id = self::ACTOR): CurrentActorPort
  {
    $actor = $this->createMock(CurrentActorPort::class);
    $actor->expects(self::once())->method('userId')->willReturn($id);

    return $actor;
  }

  /**
   * Method details
   *
   * @access private
   *
   * @return MaintenancePlanDetails the command's typed output fixture
   */
  private function details(): MaintenancePlanDetails
  {
    return new MaintenancePlanDetails(new MaintenancePlanState(
      self::PLAN,
      self::ORGANIZATION,
      self::EQUIPMENT,
      null,
      'fire_extinguisher',
      'Monthly control',
      'control',
      'P1M',
      'fixed',
      new DateTimeImmutable('2026-01-31T08:00:00+02:00'),
      new DateTimeImmutable('2026-02-28T08:00:00+02:00'),
      false,
      null,
      null,
      null,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ), null);
  }
  // #endregion
}
