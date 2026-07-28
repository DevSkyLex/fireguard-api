<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Delete;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Command\Inspection\CancelInspection\CancelInspectionCommand;
use Inspection\Domain\Exception\{InspectionAlreadyCancelledException, InspectionAlreadyClosedException, InspectionAlreadySubmittedException, InspectionNotFoundException};
use Inspection\Presentation\Api\Processor\Inspection\CancelInspectionProcessor;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

#[CoversClass(CancelInspectionProcessor::class)]
final class CancelInspectionProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new CancelInspectionProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessDispatchesCommandAndReturnsNull(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (CancelInspectionCommand $command): bool {
        return self::ORG_ID === $command->organizationId && self::INSP_ID === $command->inspectionId;
      }));

    $processor = new CancelInspectionProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    self::assertNull($processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    ));
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenInspectionMissing(): void
  {
    $processor = $this->makeAuthorizedProcessor(InspectionNotFoundException::withId(self::INSP_ID));

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessThrowsConflictWhenInspectionAlreadySubmitted(): void
  {
    $processor = $this->makeAuthorizedProcessor(InspectionAlreadySubmittedException::withId(self::INSP_ID));

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsClosedFromMessengerException(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      MessengerRuntimeException::wrap(InspectionAlreadyClosedException::withId(self::INSP_ID)),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenUriVariablesAreMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $processor = new CancelInspectionProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(data: null, operation: new Delete(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProcessThrowsAccessDeniedWhenWritePermissionIsMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new CancelInspectionProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessThrowsBadRequestOnInvalidArgument(): void
  {
    $processor = $this->makeAuthorizedProcessor(new InvalidArgumentException('Malformed inspection identifier.'));

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsNotFoundFromMessengerException(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      MessengerRuntimeException::wrap(InspectionNotFoundException::withId(self::INSP_ID)),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsAlreadyCancelledFromMessengerException(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      MessengerRuntimeException::wrap(InspectionAlreadyCancelledException::withId(self::INSP_ID)),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsAlreadySubmittedFromMessengerException(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      MessengerRuntimeException::wrap(InspectionAlreadySubmittedException::withId(self::INSP_ID)),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsInvalidArgumentFromMessengerException(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      MessengerRuntimeException::wrap(new InvalidArgumentException('Malformed inspection identifier.')),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $processor = $this->makeAuthorizedProcessor(
      MessengerRuntimeException::wrap(new RuntimeException('Transport is unavailable.')),
    );

    $this->expectException(MessengerRuntimeException::class);

    $processor->process(
      data: null,
      operation: new Delete(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  private function makeAuthorizedProcessor(Throwable $commandException): CancelInspectionProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($commandException);

    return new CancelInspectionProcessor(
      commandBus: $commandBus,
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
