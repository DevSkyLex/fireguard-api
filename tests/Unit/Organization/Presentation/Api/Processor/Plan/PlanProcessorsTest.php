<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Plan;

use ApiPlatform\Metadata\{Delete, Patch, Post};
use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\UseCase\Command\Plan\CreatePlan\{CreatePlanCommand, CreatePlanResult};
use Organization\Application\UseCase\Command\Plan\DeletePlan\{DeletePlanCommand, DeletePlanResult};
use Organization\Application\UseCase\Command\Plan\UpdatePlan\{UpdatePlanCommand, UpdatePlanResult};
use Organization\Application\UseCase\Query\Plan\GetPlan\GetPlanResult;
use Organization\Domain\Exception\{PlanKeyAlreadyExistsException, PlanNotFoundException};
use Organization\Presentation\Api\Dto\Input\Plan\{CreatePlanInput, UpdatePlanInput};
use Organization\Presentation\Api\Processor\Plan\{
  CreatePlanProcessor,
  DeletePlanProcessor,
  UpdatePlanProcessor
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\HttpKernel\Exception\{
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException
};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Test PlanProcessorsTest.
 *
 * Covers the plan write processors, including how each unwraps a
 * messenger-wrapped domain failure back into its HTTP status.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreatePlanProcessor::class)]
#[CoversClass(UpdatePlanProcessor::class)]
#[CoversClass(DeletePlanProcessor::class)]
final class PlanProcessorsTest extends TestCase
{
  // #region Constants
  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';
  // #endregion

  // #region Methods
  #[Test]
  public function testCreateDispatchesTheCommandAndReturnsTheStoredPlan(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (CreatePlanCommand $command): bool => 'pro' === $command->key
        && 'Pro' === $command->name
        && ['members' => 50] === $command->limits
        && true === $command->isActive
        && false === $command->isDefault
        && 3 === $command->sortOrder))
      ->willReturn(new CreatePlanResult(self::PLAN_ID));

    $output = new CreatePlanProcessor($commandBus, $this->queryBus())
      ->process($this->createInput(), new Post());

    self::assertSame(self::PLAN_ID, $output->id);
    self::assertSame('pro', $output->key);
  }

  #[Test]
  public function testCreateMapsADuplicateKeyToHttp409(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(PlanKeyAlreadyExistsException::withKey('pro'));

    $this->expectException(ConflictHttpException::class);

    new CreatePlanProcessor($commandBus, $this->queryBus())->process($this->createInput(), new Post());
  }

  #[Test]
  public function testCreateMapsAnInvalidValueToHttp400(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new InvalidArgumentException('Limits must be positive.'));

    $this->expectException(BadRequestHttpException::class);

    new CreatePlanProcessor($commandBus, $this->queryBus())->process($this->createInput(), new Post());
  }

  #[Test]
  public function testCreateUnwrapsADuplicateKeyWrappedByTheMessengerBus(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($this->wrapped(
      PlanKeyAlreadyExistsException::withKey('pro'),
      new CreatePlanCommand(key: 'pro', name: 'Pro', limits: []),
    ));

    $this->expectException(ConflictHttpException::class);

    new CreatePlanProcessor($commandBus, $this->queryBus())->process($this->createInput(), new Post());
  }

  #[Test]
  public function testCreateUnwrapsAnInvalidValueWrappedByTheMessengerBus(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($this->wrapped(
      new InvalidValueException('Plan key is malformed.'),
      new CreatePlanCommand(key: 'pro', name: 'Pro', limits: []),
    ));

    $this->expectException(BadRequestHttpException::class);

    new CreatePlanProcessor($commandBus, $this->queryBus())->process($this->createInput(), new Post());
  }

  #[Test]
  public function testCreateRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new RuntimeException('database is down')),
    );

    $this->expectException(MessengerRuntimeException::class);

    new CreatePlanProcessor($commandBus, $this->queryBus())->process($this->createInput(), new Post());
  }

  #[Test]
  public function testCreateMapsAMissingPlanOnTheReadBackToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new CreatePlanResult(self::PLAN_ID));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(PlanNotFoundException::withId(self::PLAN_ID));

    $this->expectException(NotFoundHttpException::class);

    new CreatePlanProcessor($commandBus, $queryBus)->process($this->createInput(), new Post());
  }

  #[Test]
  public function testUpdateDispatchesTheCommandAndReturnsTheStoredPlan(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (UpdatePlanCommand $command): bool => self::PLAN_ID === $command->planId
        && 'Pro renamed' === $command->name
        && ['members' => 80] === $command->limits))
      ->willReturn(new UpdatePlanResult(self::PLAN_ID));

    $input = new UpdatePlanInput();
    $input->name = 'Pro renamed';
    $input->limits = ['members' => 80];

    $output = new UpdatePlanProcessor($commandBus, $this->queryBus())
      ->process($input, new Patch(), ['id' => self::PLAN_ID]);

    self::assertSame(self::PLAN_ID, $output->id);
  }

  #[Test]
  public function testUpdateThrowsWhenThePlanIdIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    new UpdatePlanProcessor($this->createStub(CommandBusPort::class), $this->queryBus())
      ->process(new UpdatePlanInput(), new Patch(), []);
  }

  #[Test]
  public function testUpdateMapsAMissingPlanToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(PlanNotFoundException::withId(self::PLAN_ID));

    $this->expectException(NotFoundHttpException::class);

    new UpdatePlanProcessor($commandBus, $this->queryBus())
      ->process(new UpdatePlanInput(), new Patch(), ['id' => self::PLAN_ID]);
  }

  #[Test]
  public function testUpdateUnwrapsAMissingPlanWrappedByTheMessengerBus(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($this->wrapped(
      PlanNotFoundException::withId(self::PLAN_ID),
      new UpdatePlanCommand(planId: self::PLAN_ID),
    ));

    $this->expectException(NotFoundHttpException::class);

    new UpdatePlanProcessor($commandBus, $this->queryBus())
      ->process(new UpdatePlanInput(), new Patch(), ['id' => self::PLAN_ID]);
  }

  #[Test]
  public function testUpdateMapsAnInvalidValueToHttp400(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new InvalidValueException('Sort order must be positive.'));

    $this->expectException(BadRequestHttpException::class);

    new UpdatePlanProcessor($commandBus, $this->queryBus())
      ->process(new UpdatePlanInput(), new Patch(), ['id' => self::PLAN_ID]);
  }

  #[Test]
  public function testDeleteDispatchesTheCommandAndReturnsNull(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (DeletePlanCommand $command): bool => self::PLAN_ID === $command->planId))
      ->willReturn(new DeletePlanResult(self::PLAN_ID));

    new DeletePlanProcessor($commandBus)->process(null, new Delete(), ['id' => self::PLAN_ID]);
  }

  #[Test]
  public function testDeleteThrowsWhenThePlanIdIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    new DeletePlanProcessor($this->createStub(CommandBusPort::class))->process(null, new Delete(), []);
  }

  #[Test]
  public function testDeleteMapsAMissingPlanToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(PlanNotFoundException::withId(self::PLAN_ID));

    $this->expectException(NotFoundHttpException::class);

    new DeletePlanProcessor($commandBus)->process(null, new Delete(), ['id' => self::PLAN_ID]);
  }

  #[Test]
  public function testDeleteMapsAnInvalidArgumentToHttp409(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new InvalidArgumentException('The default plan cannot be deleted.'));

    $this->expectException(ConflictHttpException::class);

    new DeletePlanProcessor($commandBus)->process(null, new Delete(), ['id' => self::PLAN_ID]);
  }

  #[Test]
  public function testDeleteUnwrapsAConflictWrappedByTheMessengerBus(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($this->wrapped(
      new InvalidArgumentException('The plan is still assigned.'),
      new DeletePlanCommand(self::PLAN_ID),
    ));

    $this->expectException(ConflictHttpException::class);

    new DeletePlanProcessor($commandBus)->process(null, new Delete(), ['id' => self::PLAN_ID]);
  }

  private function wrapped(Throwable $domainFailure, object $message): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(
      new HandlerFailedException(new Envelope($message), [$domainFailure]),
    );
  }

  private function createInput(): CreatePlanInput
  {
    $input = new CreatePlanInput();
    $input->key = 'pro';
    $input->name = 'Pro';
    $input->limits = ['members' => 50];
    $input->sortOrder = 3;

    return $input;
  }

  private function queryBus(): QueryBusPort
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($this->planResult());

    return $queryBus;
  }

  private function planResult(): GetPlanResult
  {
    return new GetPlanResult(
      id: self::PLAN_ID,
      key: 'pro',
      name: 'Pro',
      limits: ['members' => 50],
      quotas: [],
      isActive: true,
      isDefault: false,
      sortOrder: 3,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
