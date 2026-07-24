<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\{Delete, Patch, Post};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Intervention\Application\Contract\Recurrence\InterventionRecurrenceView;
use Intervention\Application\UseCase\Command\Recurrence\CreateInterventionRecurrence\{CreateInterventionRecurrenceCommand, CreateInterventionRecurrenceResult};
use Intervention\Application\UseCase\Command\Recurrence\DeleteInterventionRecurrence\DeleteInterventionRecurrenceCommand;
use Intervention\Application\UseCase\Command\Recurrence\UpdateInterventionRecurrence\{UpdateInterventionRecurrenceCommand, UpdateInterventionRecurrenceResult};
use Intervention\Domain\Exception\InterventionNotFoundException;
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionRecurrenceInput, UpdateInterventionRecurrenceInput};
use Intervention\Presentation\Api\Dto\Output\InterventionRecurrenceOutput;
use Intervention\Presentation\Api\Factory\InterventionRecurrenceOutputFactory;
use Intervention\Presentation\Api\Processor\InterventionRecurrenceProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\VoidResult;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\MergePatchFields;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, NotFoundHttpException};

/**
 * Test InterventionRecurrenceProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionRecurrenceProcessor::class)]
final class InterventionRecurrenceProcessorTest extends TestCase
{
  private const string RECURRENCE_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441401';

  #[Test]
  public function testProcessCreatesARecurrenceAndMapsTheOutput(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(CreateInterventionRecurrenceCommand::class))
      ->willReturn(new CreateInterventionRecurrenceResult($this->view()));

    $processor = new InterventionRecurrenceProcessor($commandBus, new InterventionRecurrenceOutputFactory(), $this->securityWithUser(), $this->emptyMergePatchFields());

    $input = new CreateInterventionRecurrenceInput();
    $input->organization = '/api/organizations/550e8400-e29b-41d4-a716-446655440001';
    $input->template = '/api/intervention-templates/550e8400-e29b-41d4-a716-446655440002';
    $input->name = 'Quarterly audit';
    $input->frequency = 'quarterly';
    $input->interval = 1;
    $input->anchorDate = '2026-01-01T00:00:00+00:00';
    $input->timezone = 'UTC';

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(InterventionRecurrenceOutput::class, $output);
    self::assertSame(self::RECURRENCE_ID, $output->id);
  }

  #[Test]
  public function testProcessAppliesOnlyTheFieldsPresentInTheMergePatchBody(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (UpdateInterventionRecurrenceCommand $command): bool {
        self::assertTrue($command->hasIsActive);
        self::assertFalse($command->isActive);
        self::assertFalse($command->hasName);
        self::assertFalse($command->hasFrequency);

        return true;
      }))
      ->willReturn(new UpdateInterventionRecurrenceResult($this->view()));

    $mergePatchFields = $this->mergePatchFieldsFor('{"isActive":false}');
    $processor = new InterventionRecurrenceProcessor($commandBus, new InterventionRecurrenceOutputFactory(), $this->securityWithUser(), $mergePatchFields);

    $input = new UpdateInterventionRecurrenceInput();
    $input->isActive = false;

    $output = $processor->process($input, new Patch(), ['id' => self::RECURRENCE_ID]);

    self::assertInstanceOf(InterventionRecurrenceOutput::class, $output);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissingForPatch(): void
  {
    $processor = new InterventionRecurrenceProcessor(
      $this->createStub(CommandBusPort::class),
      new InterventionRecurrenceOutputFactory(),
      $this->securityWithUser(),
      $this->emptyMergePatchFields(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new UpdateInterventionRecurrenceInput(), new Patch(), []);
  }

  #[Test]
  public function testProcessMapsNotFoundExceptionToHttpNotFound(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(InterventionNotFoundException::withId(self::RECURRENCE_ID));

    $processor = new InterventionRecurrenceProcessor($commandBus, new InterventionRecurrenceOutputFactory(), $this->securityWithUser(), $this->emptyMergePatchFields());

    $this->expectException(NotFoundHttpException::class);

    $processor->process(new UpdateInterventionRecurrenceInput(), new Patch(), ['id' => self::RECURRENCE_ID]);
  }

  #[Test]
  public function testProcessDeletesAndReturnsNull(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (DeleteInterventionRecurrenceCommand $command): bool => self::RECURRENCE_ID === $command->recurrenceId
        && self::USER_ID === $command->userId))
      ->willReturn(new VoidResult());

    $processor = new InterventionRecurrenceProcessor($commandBus, new InterventionRecurrenceOutputFactory(), $this->securityWithUser(), $this->emptyMergePatchFields());

    $output = $processor->process(null, new Delete(), ['id' => self::RECURRENCE_ID]);

    self::assertNull($output);
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

  private function emptyMergePatchFields(): MergePatchFields
  {
    return new MergePatchFields(new RequestStack());
  }

  private function mergePatchFieldsFor(string $jsonBody): MergePatchFields
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/intervention-recurrences/' . self::RECURRENCE_ID, 'PATCH', content: $jsonBody));

    return new MergePatchFields($requestStack);
  }

  private function view(): InterventionRecurrenceView
  {
    return new InterventionRecurrenceView(
      self::RECURRENCE_ID,
      '550e8400-e29b-41d4-a716-446655440001',
      '550e8400-e29b-41d4-a716-446655440002',
      'Quarterly audit',
      null,
      null,
      'quarterly',
      1,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      'UTC',
      7,
      new DateTimeImmutable('2026-04-01T00:00:00+00:00'),
      null,
      true,
      null,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
}
