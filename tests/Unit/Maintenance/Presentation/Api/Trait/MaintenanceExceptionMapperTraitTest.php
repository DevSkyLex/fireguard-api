<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Presentation\Api\Trait;

use InvalidArgumentException;
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceNotFoundException, MaintenanceValidationException};
use Maintenance\Presentation\Api\Trait\MaintenanceExceptionMapperTrait;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversTrait, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException,
  UnprocessableEntityHttpException
};
use Throwable;

/**
 * Test MaintenanceExceptionMapperTraitTest.
 *
 * A rejected periodicity is a 422 the UI can render field-by-field, while a
 * cross-tenant schedule lookup is a 403 — collapsing either into a 500 would
 * both break the form and hide an isolation failure.
 *
 * @category Trait Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(MaintenanceExceptionMapperTrait::class)]
final class MaintenanceExceptionMapperTraitTest extends TestCase
{
  private const string SCHEDULE_ID = '550e8400-e29b-41d4-a716-446655500001';

  /**
   * @return iterable<string, array{Throwable, class-string<Throwable>}>
   */
  public static function domainFailureProvider(): iterable
  {
    yield 'maintenance access denied' => [
      new MaintenanceAccessDeniedException('Schedule belongs to another organization.'),
      AccessDeniedHttpException::class,
    ];
    yield 'organization access denied' => [
      OrganizationAccessDeniedException::missingPermission('organization.maintenance.read'),
      AccessDeniedHttpException::class,
    ];
    yield 'schedule not found' => [
      MaintenanceNotFoundException::withId(self::SCHEDULE_ID),
      NotFoundHttpException::class,
    ];
    yield 'validation failure' => [
      new MaintenanceValidationException('Periodicity must not exceed ten years.'),
      UnprocessableEntityHttpException::class,
    ];
    yield 'invalid argument' => [
      new InvalidArgumentException('Malformed schedule identifier.'),
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
      use MaintenanceExceptionMapperTrait;

      public function map(Throwable $exception): Throwable
      {
        return $this->mapMaintenanceException($exception);
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
      use MaintenanceExceptionMapperTrait;

      public function map(Throwable $exception): Throwable
      {
        return $this->mapMaintenanceException($exception);
      }
    };

    $domain = new MaintenanceValidationException('Periodicity must be at least one month.');
    $wrapper = new RuntimeException('Handling failed.', 0, $domain);

    $mapped = $mapper->map($wrapper);

    self::assertInstanceOf(UnprocessableEntityHttpException::class, $mapped);
    self::assertSame('Periodicity must be at least one month.', $mapped->getMessage());
  }

  #[Test]
  public function testItReturnsAnUnrecognisedFailureUnchanged(): void
  {
    $mapper = new class () {
      use MaintenanceExceptionMapperTrait;

      public function map(Throwable $exception): Throwable
      {
        return $this->mapMaintenanceException($exception);
      }
    };

    $failure = new RuntimeException('database is down');

    self::assertSame($failure, $mapper->map($failure));
  }
}
