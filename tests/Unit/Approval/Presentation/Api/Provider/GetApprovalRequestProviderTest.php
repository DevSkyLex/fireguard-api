<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use Approval\Application\UseCase\Query\Request\GetApprovalRequest\{
  GetApprovalRequestQuery,
  GetApprovalRequestResult
};
use Approval\Domain\Exception\{
  ApprovalRequestNotFoundException,
  ApprovalRequestNotPendingException,
  ApproverNotAuthorizedException,
  DeferredActionNoLongerApplicableException,
  SelfApprovalNotAllowedException
};
use Approval\Presentation\Api\Factory\ApprovalRequestOutputFactory;
use Approval\Presentation\Api\Provider\GetApprovalRequestProvider;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException
};
use Throwable;

/**
 * Test GetApprovalRequestProviderTest.
 *
 * The four-eyes gate is a security control, so this endpoint's refusal paths
 * matter as much as its happy path: an approval request must never leak to an
 * unauthenticated caller, and each domain refusal has to surface as its own
 * HTTP status rather than a generic 500.
 *
 * @category Provider Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetApprovalRequestProvider::class)]
#[CoversClass(ApprovalRequestOutputFactory::class)]
final class GetApprovalRequestProviderTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655470001';

  private const string REQUEST_ID = '550e8400-e29b-41d4-a716-446655470002';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655470003';
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
  public function testProvideReturnsTheMappedApprovalRequest(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetApprovalRequestQuery $query): bool => self::ORGANIZATION_ID === $query->organizationId
        && self::REQUEST_ID === $query->requestId
        && self::USER_ID === $query->userId))
      ->willReturn($this->approvalResult());

    $output = $this->createProvider($queryBus)->provide(new Get(), $this->uriVariables());

    self::assertSame(self::REQUEST_ID, $output->id);
    self::assertSame('equipment_decommission', $output->actionType);
    self::assertSame('pending', $output->status);
    self::assertSame('2026-02-01T00:00:00+00:00', $output->expiresAt);
    self::assertNull($output->decidedAt);
    self::assertNull($output->executedAt);
  }

  #[Test]
  public function testProvideMapsADecidedRequestTimestamps(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($this->approvalResult(decided: true));

    $output = $this->createProvider($queryBus)->provide(new Get(), $this->uriVariables());

    self::assertSame('approved', $output->status);
    self::assertSame('2026-01-10T00:00:00+00:00', $output->decidedAt);
    self::assertSame('2026-01-11T00:00:00+00:00', $output->executedAt);
    self::assertSame('member-2', $output->decisionByMemberId);
    self::assertSame('Looks good.', $output->decisionNote);
  }

  #[Test]
  public function testProvideThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetApprovalRequestProvider(
      queryBus: $queryBus,
      security: $security,
      outputFactory: new ApprovalRequestOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $provider->provide(new Get(), $this->uriVariables());
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('incompleteUriVariablesProvider')]
  public function testProvideThrowsWhenUriVariablesAreIncomplete(array $uriVariables): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $this->expectException(BadRequestHttpException::class);

    $this->createProvider($queryBus)->provide(new Get(), $uriVariables);
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('domainFailureProvider')]
  public function testProvideMapsEachDomainFailureToItsHttpStatus(
    Throwable $failure,
    string $expected,
  ): void {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($failure);

    $this->expectException($expected);

    $this->createProvider($queryBus)->provide(new Get(), $this->uriVariables());
  }

  #[Test]
  public function testProvideRethrowsAnUnrecognisedFailure(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('database is down'));

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('database is down');

    $this->createProvider($queryBus)->provide(new Get(), $this->uriVariables());
  }

  /**
   * @return array<string, string>
   */
  private function uriVariables(): array
  {
    return ['organizationId' => self::ORGANIZATION_ID, 'requestId' => self::REQUEST_ID];
  }

  private function createProvider(QueryBusPort $queryBus): GetApprovalRequestProvider
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

    return new GetApprovalRequestProvider(
      queryBus: $queryBus,
      security: $security,
      outputFactory: new ApprovalRequestOutputFactory(),
    );
  }

  private function approvalResult(bool $decided = false): GetApprovalRequestResult
  {
    return new GetApprovalRequestResult(
      id: self::REQUEST_ID,
      organizationId: self::ORGANIZATION_ID,
      actionType: 'equipment_decommission',
      subjectId: '550e8400-e29b-41d4-a716-446655470004',
      status: $decided ? 'approved' : 'pending',
      requestedByMemberId: 'member-1',
      requestedByUserId: self::USER_ID,
      decisionByMemberId: $decided ? 'member-2' : null,
      decisionByUserId: $decided ? '550e8400-e29b-41d4-a716-446655470005' : null,
      decisionNote: $decided ? 'Looks good.' : null,
      expiresAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
      decidedAt: $decided ? new DateTimeImmutable('2026-01-10T00:00:00+00:00') : null,
      executedAt: $decided ? new DateTimeImmutable('2026-01-11T00:00:00+00:00') : null,
      executionError: null,
    );
  }
  // #endregion
}
