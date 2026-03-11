<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\Checklist;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Command\Checklist\ArchiveChecklist\ArchiveChecklistCommand;
use Inspection\Application\UseCase\Query\Checklist\GetChecklist\{ChecklistItemResult, GetChecklistQuery, GetChecklistResult};
use Inspection\Domain\Exception\{ChecklistArchivedException, ChecklistNotFoundException};
use Inspection\Presentation\Api\Dto\Output\Checklist\ChecklistOutput;
use Inspection\Presentation\Api\Processor\Checklist\ArchiveChecklistProcessor;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

#[CoversClass(ArchiveChecklistProcessor::class)]
final class ArchiveChecklistProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string CL_ID = '550e8400-e29b-41d4-a716-446655440030';

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new ArchiveChecklistProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessThrowsWhenUriVariablesMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $processor = new ArchiveChecklistProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProcessDispatchesCommandAndReturnsOutput(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (ArchiveChecklistCommand $command): bool {
        return self::ORG_ID === $command->organizationId
          && self::CL_ID === $command->checklistId;
      }));

    $now = new DateTimeImmutable('2026-01-15T11:00:00+00:00');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetChecklistQuery $query): bool {
        return self::ORG_ID === $query->organizationId
          && self::CL_ID === $query->checklistId;
      }))
      ->willReturn(new GetChecklistResult(
        checklistId: self::CL_ID,
        organizationId: self::ORG_ID,
        name: 'Fire Safety Checklist',
        version: '1.0',
        status: 'archived',
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
      ));

    $processor = new ArchiveChecklistProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );

    self::assertInstanceOf(ChecklistOutput::class, $output);
    self::assertSame(self::CL_ID, $output->id);
    self::assertSame('archived', $output->status);
    self::assertCount(1, $output->items);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenChecklistMissing(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      commandException: ChecklistNotFoundException::withId(self::CL_ID),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessThrowsConflictWhenAlreadyArchived(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      commandException: ChecklistArchivedException::withId(self::CL_ID),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsNotFoundFromMessengerException(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      commandException: MessengerRuntimeException::wrap(
        ChecklistNotFoundException::withId(self::CL_ID),
      ),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'checklistId' => self::CL_ID],
    );
  }

  private function makeAuthorizedProcessor(Throwable $commandException): ArchiveChecklistProcessor
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($commandException);

    return new ArchiveChecklistProcessor(
      commandBus: $commandBus,
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $authorization,
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
}
