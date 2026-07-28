<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Presentation\Api\Trait;

use Approval\Domain\Exception\{
  ApprovalRequestNotFoundException,
  ApprovalRequestNotPendingException,
  ApproverNotAuthorizedException,
  DeferredActionNoLongerApplicableException,
  SelfApprovalNotAllowedException
};
use Approval\Presentation\Api\Trait\ApprovalExceptionMapperTrait;
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversTrait, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException
};
use Throwable;

/**
 * Test ApprovalExceptionMapperTraitTest.
 *
 * The four-eyes gate's refusals are security decisions: a self-approval
 * attempt or an unauthorized approver must come back as 403, and a request
 * already decided as 409 — never collapsed into a generic 500 that a client
 * could retry blindly.
 *
 * @category Trait Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(ApprovalExceptionMapperTrait::class)]
final class ApprovalExceptionMapperTraitTest extends TestCase
{
  private const string REQUEST_ID = '550e8400-e29b-41d4-a716-446655498001';

  /**
   * @return iterable<string, array{Throwable, class-string<Throwable>}>
   */
  public static function domainFailureProvider(): iterable
  {
    yield 'organization access denied' => [
      OrganizationAccessDeniedException::missingPermission('organization.approval.read'),
      AccessDeniedHttpException::class,
    ];
    yield 'self approval' => [
      SelfApprovalNotAllowedException::create(),
      AccessDeniedHttpException::class,
    ];
    yield 'approver below minimum role' => [
      ApproverNotAuthorizedException::belowMinimumRole('manager'),
      AccessDeniedHttpException::class,
    ];
    yield 'request not found' => [
      ApprovalRequestNotFoundException::withId(self::REQUEST_ID),
      NotFoundHttpException::class,
    ];
    yield 'request already decided' => [
      ApprovalRequestNotPendingException::withId(self::REQUEST_ID),
      ConflictHttpException::class,
    ];
    yield 'deferred action no longer applicable' => [
      DeferredActionNoLongerApplicableException::becauseSubjectChanged('equipment already decommissioned'),
      ConflictHttpException::class,
    ];
    yield 'invalid argument' => [
      new InvalidArgumentException('Unknown action type.'),
      BadRequestHttpException::class,
    ];
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('domainFailureProvider')]
  public function testItMapsEachDomainFailureToItsHttpStatus(Throwable $failure, string $expected): void
  {
    $mapper = new class () {
      use ApprovalExceptionMapperTrait;

      public function map(Throwable $exception): Throwable
      {
        return $this->mapApprovalException($exception);
      }
    };

    $mapped = $mapper->map($failure);

    self::assertInstanceOf($expected, $mapped);
    self::assertSame($failure->getMessage(), $mapped->getMessage());
  }

  #[Test]
  public function testItUnwrapsABusWrappedFailure(): void
  {
    $mapper = new class () {
      use ApprovalExceptionMapperTrait;

      public function map(Throwable $exception): Throwable
      {
        return $this->mapApprovalException($exception);
      }
    };

    $domain = SelfApprovalNotAllowedException::create();
    $wrapper = new RuntimeException('Handling failed.', 0, new RuntimeException('inner', 0, $domain));

    $mapped = $mapper->map($wrapper);

    self::assertInstanceOf(AccessDeniedHttpException::class, $mapped);
    self::assertSame($wrapper, $mapped->getPrevious());
  }

  #[Test]
  public function testItReturnsAnUnrecognisedFailureUnchanged(): void
  {
    $mapper = new class () {
      use ApprovalExceptionMapperTrait;

      public function map(Throwable $exception): Throwable
      {
        return $this->mapApprovalException($exception);
      }
    };

    $failure = new RuntimeException('database is down');

    self::assertSame($failure, $mapper->map($failure));
  }
}
