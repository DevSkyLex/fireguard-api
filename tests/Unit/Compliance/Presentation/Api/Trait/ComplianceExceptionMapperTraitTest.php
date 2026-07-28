<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Presentation\Api\Trait;

use Compliance\Domain\Exception\{ComplianceAccessDeniedException, ComplianceExportNotEntitledException, ComplianceNotFoundException};
use Compliance\Presentation\Api\Trait\ComplianceExceptionMapperTrait;
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversTrait, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

/**
 * Test ComplianceExceptionMapperTraitTest.
 *
 * The safety register is a regulatory document: refusing it because the
 * caller lacks the permission, or because the plan does not include the
 * export, must both read as 403 rather than an opaque 500 the client would
 * retry.
 *
 * @category Trait Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(ComplianceExceptionMapperTrait::class)]
final class ComplianceExceptionMapperTraitTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655499001';

  /**
   * @return iterable<string, array{Throwable, class-string<Throwable>}>
   */
  public static function domainFailureProvider(): iterable
  {
    yield 'compliance access denied' => [
      new ComplianceAccessDeniedException('Compliance register is out of reach.'),
      AccessDeniedHttpException::class,
    ];
    yield 'organization access denied' => [
      OrganizationAccessDeniedException::missingPermission('organization.compliance.read'),
      AccessDeniedHttpException::class,
    ];
    yield 'export not entitled' => [
      ComplianceExportNotEntitledException::planTooLow(self::ORGANIZATION_ID),
      AccessDeniedHttpException::class,
    ];
    yield 'facility not found' => [
      ComplianceNotFoundException::facilityNotFound('550e8400-e29b-41d4-a716-446655499002'),
      NotFoundHttpException::class,
    ];
    yield 'invalid argument' => [
      new InvalidArgumentException('Malformed facility identifier.'),
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
      use ComplianceExceptionMapperTrait;

      public function map(Throwable $exception): Throwable
      {
        return $this->mapComplianceException($exception);
      }
    };

    $mapped = $mapper->map($failure);

    self::assertInstanceOf($expected, $mapped);
    self::assertSame($failure->getMessage(), $mapped->getMessage());
    self::assertSame($failure, $mapped->getPrevious());
  }

  #[Test]
  public function testItUnwrapsABusWrappedFailure(): void
  {
    $mapper = new class () {
      use ComplianceExceptionMapperTrait;

      public function map(Throwable $exception): Throwable
      {
        return $this->mapComplianceException($exception);
      }
    };

    $domain = ComplianceNotFoundException::facilityNotFound('550e8400-e29b-41d4-a716-446655499003');
    $wrapper = new RuntimeException('Handling failed.', 0, $domain);

    self::assertInstanceOf(NotFoundHttpException::class, $mapper->map($wrapper));
  }

  #[Test]
  public function testItReturnsAnUnrecognisedFailureUnchanged(): void
  {
    $mapper = new class () {
      use ComplianceExceptionMapperTrait;

      public function map(Throwable $exception): Throwable
      {
        return $this->mapComplianceException($exception);
      }
    };

    $failure = new RuntimeException('database is down');

    self::assertSame($failure, $mapper->map($failure));
  }
}
