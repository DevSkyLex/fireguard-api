<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\{Delete, Patch, Post};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Intervention\Application\Contract\Label\InterventionLabelView;
use Intervention\Application\UseCase\Command\Label\CreateInterventionLabel\{CreateInterventionLabelCommand, CreateInterventionLabelResult};
use Intervention\Application\UseCase\Command\Label\DeleteInterventionLabel\DeleteInterventionLabelCommand;
use Intervention\Application\UseCase\Command\Label\UpdateInterventionLabel\{UpdateInterventionLabelCommand, UpdateInterventionLabelResult};
use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionLabelInput, UpdateInterventionLabelInput};
use Intervention\Presentation\Api\Dto\Output\InterventionLabelOutput;
use Intervention\Presentation\Api\Factory\InterventionLabelOutputFactory;
use Intervention\Presentation\Api\Processor\InterventionLabelProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\VoidResult;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\MergePatchFields;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException};

/**
 * Test InterventionLabelProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionLabelProcessor::class)]
final class InterventionLabelProcessorTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string LABEL_ID = '550e8400-e29b-41d4-a716-446655441600';

  // #region Methods
  #[Test]
  public function testProcessCreatesALabelAndMapsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (CreateInterventionLabelCommand $command): bool {
        self::assertSame(self::USER_ID, $command->userId);
        self::assertSame(self::ORG_ID, $command->organizationId);
        self::assertSame('Urgent', $command->name);
        self::assertSame('#3b82f6', $command->color);

        return true;
      }))
      ->willReturn(new CreateInterventionLabelResult($this->view()));

    $input = new CreateInterventionLabelInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;
    $input->name = 'Urgent';
    $input->color = '#3b82f6';

    $output = $this->processor($commandBus, $this->mergePatchFields())->process($input, new Post());

    self::assertInstanceOf(InterventionLabelOutput::class, $output);
    self::assertSame(self::LABEL_ID, $output->id);
  }

  #[Test]
  public function testProcessAppliesOnlyTheFieldsPresentInTheMergePatchBody(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (UpdateInterventionLabelCommand $command): bool {
        self::assertSame(self::LABEL_ID, $command->labelId);
        self::assertTrue($command->hasColor);
        self::assertFalse($command->hasName);
        self::assertSame('#ff0000', $command->color);

        return true;
      }))
      ->willReturn(new UpdateInterventionLabelResult($this->view()));

    $input = new UpdateInterventionLabelInput();
    $input->color = '#ff0000';

    $output = $this->processor($commandBus, $this->mergePatchFields('{"color":"#ff0000"}'))
      ->process($input, new Patch(), ['id' => self::LABEL_ID]);

    self::assertInstanceOf(InterventionLabelOutput::class, $output);
  }

  #[Test]
  public function testProcessDeletesAndReturnsNull(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (DeleteInterventionLabelCommand $command): bool => self::LABEL_ID === $command->labelId
        && self::USER_ID === $command->userId))
      ->willReturn(new VoidResult());

    $output = $this->processor($commandBus, $this->mergePatchFields())
      ->process(null, new Delete(), ['id' => self::LABEL_ID]);

    self::assertNull($output);
  }

  #[Test]
  public function testProcessRequiresAnIdForPatch(): void
  {
    $processor = $this->processor($this->createStub(CommandBusPort::class), $this->mergePatchFields());

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new UpdateInterventionLabelInput(), new Patch(), []);
  }

  #[Test]
  public function testProcessRequiresAnIdForDelete(): void
  {
    $processor = $this->processor($this->createStub(CommandBusPort::class), $this->mergePatchFields());

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Delete(), []);
  }

  #[Test]
  public function testProcessRequiresAnAuthenticatedUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new InterventionLabelProcessor(
      $this->createStub(CommandBusPort::class),
      new InterventionLabelOutputFactory(),
      $security,
      $this->mergePatchFields(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), ['id' => self::LABEL_ID]);
  }

  #[Test]
  public function testProcessMapsADomainFailureToItsHttpEquivalent(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new InterventionConflictException('Duplicate label.'));

    $input = new CreateInterventionLabelInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;
    $input->name = 'Urgent';
    $input->color = '#3b82f6';

    $this->expectException(ConflictHttpException::class);

    $this->processor($commandBus, $this->mergePatchFields())->process($input, new Post());
  }

  private function processor(CommandBusPort $commandBus, MergePatchFields $mergePatchFields): InterventionLabelProcessor
  {
    return new InterventionLabelProcessor(
      $commandBus,
      new InterventionLabelOutputFactory(),
      $this->security(),
      $mergePatchFields,
    );
  }

  private function mergePatchFields(?string $jsonBody = null): MergePatchFields
  {
    $requestStack = new RequestStack();
    if (null !== $jsonBody) {
      $requestStack->push(Request::create('/api/intervention-labels/' . self::LABEL_ID, 'PATCH', content: $jsonBody));
    }

    return new MergePatchFields($requestStack);
  }

  private function security(): Security
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

  private function view(): InterventionLabelView
  {
    return new InterventionLabelView(
      self::LABEL_ID,
      self::ORG_ID,
      'Urgent',
      '#3b82f6',
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
