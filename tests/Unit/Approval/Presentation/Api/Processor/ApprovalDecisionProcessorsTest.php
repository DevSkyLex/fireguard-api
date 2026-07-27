<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Approval\Application\UseCase\Command\Decision\ApproveApprovalRequest\{
  ApproveApprovalRequestCommand,
  ApproveApprovalRequestResult
};
use Approval\Application\UseCase\Command\Decision\RejectApprovalRequest\{
  RejectApprovalRequestCommand,
  RejectApprovalRequestResult
};
use Approval\Domain\Exception\{
  ApprovalRequestNotFoundException,
  ApprovalRequestNotPendingException,
  ApproverNotAuthorizedException,
  DeferredActionNoLongerApplicableException,
  SelfApprovalNotAllowedException
};
use Approval\Presentation\Api\Dto\Input\{ApproveApprovalRequestInput, RejectApprovalRequestInput};
use Approval\Presentation\Api\Factory\ApprovalRequestOutputFactory;
use Approval\Presentation\Api\Processor\{ApproveApprovalRequestProcessor, RejectApprovalRequestProcessor};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException
};
use Throwable;

/**
 * Test ApprovalDecisionProcessorsTest.
 *
 * Approving and rejecting are the two write ends of the four-eyes gate. Both
 * refuse the same way — unauthenticated caller, malformed route, self-approval,
 * an already-decided request — and each refusal has to keep its own HTTP
 * status so the client can tell "you may not" from "too late".
 *
 * @category Processor Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApproveApprovalRequestProcessor::class)]
#[CoversClass(RejectApprovalRequestProcessor::class)]
final class ApprovalDecisionProcessorsTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655471001';

  private const string REQUEST_ID = '550e8400-e29b-41d4-a716-446655471002';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655471003';
  // #endregion

  // #region Methods
  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function incompleteUriVariablesProvider(): iterable
  {
    yield 'no variables' => [[]];
    yield 'blank organizationId' => [['organizationId' => '', 'requestId' => self::REQUEST_ID]];
    yield 'missing requestId' => [['organizationId' => self::ORGANIZATION_ID]];
    yield 'blank requestId' => [['organizationId' => self::ORGANIZATION_ID, 'requestId' => '']];
  }

  /**
   * @return iterable<string, array{Throwable, class-string<Throwable>}>
   */
  public static function domainFailureProvider(): iterable
  {
    yield 'unknown request' => [
      ApprovalRequestNotFoundException::withId(self::REQUEST_ID),
      NotFoundHttpException::class,
    ];
    yield 'already decided' => [
      ApprovalRequestNotPendingException::withId(self::REQUEST_ID),
      ConflictHttpException::class,
    ];
    yield 'subject changed' => [
      DeferredActionNoLongerApplicableException::becauseSubjectChanged('equipment already decommissioned'),
      ConflictHttpException::class,
    ];
    yield 'approver below minimum role' => [
      ApproverNotAuthorizedException::belowMinimumRole('manager'),
      AccessDeniedHttpException::class,
    ];
    yield 'self approval' => [
      SelfApprovalNotAllowedException::create(),
      AccessDeniedHttpException::class,
    ];
    yield 'invalid argument' => [
      new InvalidArgumentException('Malformed request id.'),
      BadRequestHttpException::class,
    ];
  }

  #[Test]
  public function testApproveDispatchesTheDecisionAndReturnsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (ApproveApprovalRequestCommand $command): bool => self::ORGANIZATION_ID === $command->organizationId
        && self::REQUEST_ID === $command->requestId
        && self::USER_ID === $command->actorUserId
        && 'Approved after site visit.' === $command->decisionNote))
      ->willReturn($this->approveResult());

    $input = new ApproveApprovalRequestInput();
    $input->decisionNote = 'Approved after site visit.';

    $output = $this->approveProcessor($commandBus)->process($input, new Post(), $this->uriVariables());

    self::assertSame(self::REQUEST_ID, $output->id);
    self::assertSame('approved', $output->status);
    self::assertSame('Approved after site visit.', $output->decisionNote);
    self::assertSame('2026-01-10T00:00:00+00:00', $output->decidedAt);
  }

  #[Test]
  public function testRejectDispatchesTheDecisionAndReturnsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RejectApprovalRequestCommand $command): bool => self::REQUEST_ID === $command->requestId
        && self::USER_ID === $command->actorUserId
        && 'Not justified.' === $command->decisionNote))
      ->willReturn($this->rejectResult());

    $input = new RejectApprovalRequestInput();
    $input->decisionNote = 'Not justified.';

    $output = $this->rejectProcessor($commandBus)->process($input, new Post(), $this->uriVariables());

    self::assertSame(self::REQUEST_ID, $output->id);
    self::assertSame('rejected', $output->status);
    self::assertSame('Not justified.', $output->decisionNote);
  }

  #[Test]
  public function testADecisionNoteIsOptional(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (ApproveApprovalRequestCommand $command): bool => null === $command->decisionNote))
      ->willReturn($this->approveResult());

    $this->approveProcessor($commandBus)
      ->process(new ApproveApprovalRequestInput(), new Post(), $this->uriVariables());
  }

  #[Test]
  public function testApproveThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new ApproveApprovalRequestProcessor(
      commandBus: $commandBus,
      security: $security,
      outputFactory: new ApprovalRequestOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $processor->process(new ApproveApprovalRequestInput(), new Post(), $this->uriVariables());
  }

  #[Test]
  public function testRejectThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new RejectApprovalRequestProcessor(
      commandBus: $commandBus,
      security: $security,
      outputFactory: new ApprovalRequestOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new RejectApprovalRequestInput(), new Post(), $this->uriVariables());
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('incompleteUriVariablesProvider')]
  public function testApproveThrowsWhenUriVariablesAreIncomplete(array $uriVariables): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(BadRequestHttpException::class);

    $this->approveProcessor($commandBus)->process(new ApproveApprovalRequestInput(), new Post(), $uriVariables);
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('incompleteUriVariablesProvider')]
  public function testRejectThrowsWhenUriVariablesAreIncomplete(array $uriVariables): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(BadRequestHttpException::class);

    $this->rejectProcessor($commandBus)->process(new RejectApprovalRequestInput(), new Post(), $uriVariables);
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('domainFailureProvider')]
  public function testApproveMapsEachDomainFailureToItsHttpStatus(Throwable $failure, string $expected): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    $this->expectException($expected);

    $this->approveProcessor($commandBus)
      ->process(new ApproveApprovalRequestInput(), new Post(), $this->uriVariables());
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('domainFailureProvider')]
  public function testRejectMapsEachDomainFailureToItsHttpStatus(Throwable $failure, string $expected): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    $this->expectException($expected);

    $this->rejectProcessor($commandBus)
      ->process(new RejectApprovalRequestInput(), new Post(), $this->uriVariables());
  }

  #[Test]
  public function testApproveRethrowsAnUnrecognisedFailure(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new RuntimeException('database is down'));

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('database is down');

    $this->approveProcessor($commandBus)
      ->process(new ApproveApprovalRequestInput(), new Post(), $this->uriVariables());
  }

  /**
   * @return array<string, string>
   */
  private function uriVariables(): array
  {
    return ['organizationId' => self::ORGANIZATION_ID, 'requestId' => self::REQUEST_ID];
  }

  private function approveProcessor(CommandBusPort $commandBus): ApproveApprovalRequestProcessor
  {
    return new ApproveApprovalRequestProcessor(
      commandBus: $commandBus,
      security: $this->securityWithUser(),
      outputFactory: new ApprovalRequestOutputFactory(),
    );
  }

  private function rejectProcessor(CommandBusPort $commandBus): RejectApprovalRequestProcessor
  {
    return new RejectApprovalRequestProcessor(
      commandBus: $commandBus,
      security: $this->securityWithUser(),
      outputFactory: new ApprovalRequestOutputFactory(),
    );
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

  private function approveResult(): ApproveApprovalRequestResult
  {
    return new ApproveApprovalRequestResult(
      id: self::REQUEST_ID,
      organizationId: self::ORGANIZATION_ID,
      actionType: 'equipment_decommission',
      subjectId: '550e8400-e29b-41d4-a716-446655471004',
      status: 'approved',
      requestedByMemberId: 'member-1',
      requestedByUserId: '550e8400-e29b-41d4-a716-446655471005',
      decisionByMemberId: 'member-2',
      decisionByUserId: self::USER_ID,
      decisionNote: 'Approved after site visit.',
      expiresAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-10T00:00:00+00:00'),
      decidedAt: new DateTimeImmutable('2026-01-10T00:00:00+00:00'),
      executedAt: new DateTimeImmutable('2026-01-10T00:05:00+00:00'),
      executionError: null,
    );
  }

  private function rejectResult(): RejectApprovalRequestResult
  {
    return new RejectApprovalRequestResult(
      id: self::REQUEST_ID,
      organizationId: self::ORGANIZATION_ID,
      actionType: 'equipment_decommission',
      subjectId: '550e8400-e29b-41d4-a716-446655471004',
      status: 'rejected',
      requestedByMemberId: 'member-1',
      requestedByUserId: '550e8400-e29b-41d4-a716-446655471005',
      decisionByMemberId: 'member-2',
      decisionByUserId: self::USER_ID,
      decisionNote: 'Not justified.',
      expiresAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-10T00:00:00+00:00'),
      decidedAt: new DateTimeImmutable('2026-01-10T00:00:00+00:00'),
      executedAt: null,
      executionError: null,
    );
  }
  // #endregion
}
