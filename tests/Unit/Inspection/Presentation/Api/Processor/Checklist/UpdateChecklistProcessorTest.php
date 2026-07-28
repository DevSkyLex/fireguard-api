<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\Checklist;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Command\Checklist\UpdateChecklist\UpdateChecklistCommand;
use Inspection\Application\UseCase\Query\Checklist\GetChecklist\{ChecklistItemResult, GetChecklistQuery, GetChecklistResult};
use Inspection\Domain\Exception\{ChecklistArchivedException, ChecklistInUseException, ChecklistNotFoundException, ChecklistReferenceCodeAlreadyExistsException};
use Inspection\Presentation\Api\Dto\Input\Checklist\{ChecklistItemInput, UpdateChecklistInput};
use Inspection\Presentation\Api\Dto\Output\Checklist\ChecklistOutput;
use Inspection\Presentation\Api\Processor\Checklist\UpdateChecklistProcessor;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Test UpdateChecklistProcessorTest.
 *
 * @category Presentation Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateChecklistProcessor::class)]
final class UpdateChecklistProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string CL_ID = '550e8400-e29b-41d4-a716-446655440030';

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new UpdateChecklistProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      requestStack: $this->makeRequestStack(['name' => 'Renamed']),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: new UpdateChecklistInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessThrowsWhenNoEditableFieldProvided(): void
  {
    $processor = $this->makeProcessor(requestStack: $this->makeRequestStack([]));

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: new UpdateChecklistInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessDispatchesCommandAndReturnsOutput(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (UpdateChecklistCommand $command): bool {
        return self::ORG_ID === $command->organizationId
          && self::CL_ID === $command->checklistId
          && 'Renamed Checklist' === $command->name
          && true === $command->hasName
          && false === $command->hasItems;
      }));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetChecklistQuery $query): bool {
        return self::ORG_ID === $query->organizationId && self::CL_ID === $query->checklistId;
      }))
      ->willReturn($this->makeGetChecklistResult());

    $input = new UpdateChecklistInput();
    $input->name = 'Renamed Checklist';

    $processor = new UpdateChecklistProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
      authorization: $authorization,
      security: $this->makeSecurity(),
      requestStack: $this->makeRequestStack(['name' => 'Renamed Checklist']),
    );

    $output = $processor->process(
      data: $input,
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );

    self::assertInstanceOf(ChecklistOutput::class, $output);
    self::assertSame(self::CL_ID, $output->id);
    self::assertSame(1, $output->itemCount);
    self::assertCount(1, $output->items);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenChecklistMissing(): void
  {
    $processor = $this->makeProcessor(
      commandException: ChecklistNotFoundException::withId(self::CL_ID),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessThrowsConflictWhenChecklistArchived(): void
  {
    $processor = $this->makeProcessor(
      commandException: ChecklistArchivedException::withId(self::CL_ID),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessThrowsConflictWhenChecklistInUse(): void
  {
    $processor = $this->makeProcessor(
      commandException: ChecklistInUseException::withId(self::CL_ID),
      requestStack: $this->makeRequestStack(['items' => []]),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessThrowsConflictWhenReferenceCodeAlreadyExists(): void
  {
    $processor = $this->makeProcessor(
      commandException: ChecklistReferenceCodeAlreadyExistsException::withReferenceCode('CHK-DUP'),
      requestStack: $this->makeRequestStack(['referenceCode' => 'CHK-DUP']),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsInUseFromMessengerException(): void
  {
    $processor = $this->makeProcessor(
      commandException: MessengerRuntimeException::wrap(ChecklistInUseException::withId(self::CL_ID)),
      requestStack: $this->makeRequestStack(['items' => []]),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenUriVariablesAreMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->makeProcessor()->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => ''],
    );
  }

  #[Test]
  public function testProcessThrowsAccessDeniedWhenWritePermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $processor = new UpdateChecklistProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $this->makeSecurity(),
      requestStack: $this->makeRequestStack(['name' => 'Renamed']),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenNoRequestIsAvailable(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->makeProcessor(requestStack: new RequestStack())->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessThrowsBadRequestOnAMalformedJsonPayload(): void
  {
    $request = new Request(content: '{not-json');
    $request->headers->set('CONTENT_TYPE', 'application/json');
    $stack = new RequestStack();
    $stack->push($request);

    $this->expectException(BadRequestHttpException::class);

    $this->makeProcessor(requestStack: $stack)->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessMapsSubmittedItemsIntoTheCommand(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (UpdateChecklistCommand $command): bool {
        return true === $command->hasItems
          && [[
            'label' => 'Check hose integrity',
            'description' => 'Look for cracks',
            'required' => false,
            'position' => 3,
          ]] === $command->items;
      }));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($this->makeGetChecklistResult());

    $item = new ChecklistItemInput();
    $item->label = 'Check hose integrity';
    $item->description = 'Look for cracks';
    $item->required = false;
    $item->position = 3;

    $input = new UpdateChecklistInput();
    $input->items = [$item];

    $processor = new UpdateChecklistProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
      authorization: $authorization,
      security: $this->makeSecurity(),
      requestStack: $this->makeRequestStack(['items' => [['label' => 'Check hose integrity']]]),
    );

    $output = $processor->process(
      data: $input,
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );

    self::assertInstanceOf(ChecklistOutput::class, $output);
  }

  #[Test]
  public function testProcessThrowsBadRequestOnInvalidArgument(): void
  {
    $processor = $this->makeProcessor(
      commandException: new InvalidArgumentException('Malformed checklist identifier.'),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsNotFoundFromMessengerException(): void
  {
    $processor = $this->makeProcessor(
      commandException: MessengerRuntimeException::wrap(ChecklistNotFoundException::withId(self::CL_ID)),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsArchivedFromMessengerException(): void
  {
    $processor = $this->makeProcessor(
      commandException: MessengerRuntimeException::wrap(ChecklistArchivedException::withId(self::CL_ID)),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsDuplicateReferenceCodeFromMessengerException(): void
  {
    $processor = $this->makeProcessor(
      commandException: MessengerRuntimeException::wrap(
        ChecklistReferenceCodeAlreadyExistsException::withReferenceCode('CHK-DUP'),
      ),
      requestStack: $this->makeRequestStack(['referenceCode' => 'CHK-DUP']),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsInvalidArgumentFromMessengerException(): void
  {
    $processor = $this->makeProcessor(
      commandException: MessengerRuntimeException::wrap(new InvalidArgumentException('Malformed checklist identifier.')),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $processor = $this->makeProcessor(
      commandException: MessengerRuntimeException::wrap(new RuntimeException('Transport is unavailable.')),
    );

    $this->expectException(MessengerRuntimeException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  private function makeProcessor(?Throwable $commandException = null, ?RequestStack $requestStack = null): UpdateChecklistProcessor
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    if (null !== $commandException) {
      $commandBus->method('dispatch')->willThrowException($commandException);
    }

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($this->makeGetChecklistResult());

    return new UpdateChecklistProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
      authorization: $authorization,
      security: $this->makeSecurity(),
      requestStack: $requestStack ?? $this->makeRequestStack(['name' => 'Renamed']),
    );
  }

  /**
   * @param array<string, mixed> $payload
   */
  private function makeRequestStack(array $payload): RequestStack
  {
    $request = new Request(content: json_encode($payload, JSON_THROW_ON_ERROR));
    $request->headers->set('CONTENT_TYPE', 'application/json');
    $stack = new RequestStack();
    $stack->push($request);

    return $stack;
  }

  private function makeInput(): UpdateChecklistInput
  {
    $input = new UpdateChecklistInput();
    $input->name = 'Renamed';

    return $input;
  }

  private function makeSecurity(): Security
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

  private function makeGetChecklistResult(): GetChecklistResult
  {
    $now = new DateTimeImmutable('2026-01-15T11:00:00+00:00');

    return new GetChecklistResult(
      checklistId: self::CL_ID,
      organizationId: self::ORG_ID,
      name: 'Renamed Checklist',
      version: '1.0',
      status: 'active',
      items: [
        new ChecklistItemResult(
          itemId: '550e8400-e29b-41d4-a716-446655440031',
          label: 'Check extinguisher pressure',
          position: 1,
          required: true,
          description: null,
        ),
      ],
      createdAt: $now,
      updatedAt: $now,
      referenceCode: null,
    );
  }
}
