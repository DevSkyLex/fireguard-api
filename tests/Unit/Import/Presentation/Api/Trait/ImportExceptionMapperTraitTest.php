<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Presentation\Api\Trait;

use Import\Domain\Exception\{ImportAccessDeniedException, ImportJobNotFoundException};
use Import\Presentation\Api\Trait\ImportExceptionMapperTrait;
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversTrait, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

/**
 * Test ImportExceptionMapperTraitTest.
 *
 * A refused import must surface its real status even when the command bus
 * wrapped it: an authorization failure that leaks as a 500 hides a
 * tenant-isolation breach behind a generic error.
 *
 * @category Trait Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(ImportExceptionMapperTrait::class)]
final class ImportExceptionMapperTraitTest extends TestCase
{
  /**
   * @return iterable<string, array{Throwable, class-string<Throwable>}>
   */
  public static function domainFailureProvider(): iterable
  {
    yield 'import access denied' => [
      ImportAccessDeniedException::missingPermission('organization.facilities.read'),
      AccessDeniedHttpException::class,
    ];
    yield 'organization access denied' => [
      OrganizationAccessDeniedException::missingPermission('organization.import.read'),
      AccessDeniedHttpException::class,
    ];
    yield 'import job not found' => [
      ImportJobNotFoundException::withId('550e8400-e29b-41d4-a716-446655497001'),
      NotFoundHttpException::class,
    ];
    yield 'invalid argument' => [
      new InvalidArgumentException('Malformed organization IRI.'),
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
      use ImportExceptionMapperTrait;

      public function map(Throwable $exception): Throwable
      {
        return $this->mapImportException($exception);
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
      use ImportExceptionMapperTrait;

      public function map(Throwable $exception): Throwable
      {
        return $this->mapImportException($exception);
      }
    };

    $domain = ImportJobNotFoundException::withId('550e8400-e29b-41d4-a716-446655497002');
    $wrapper = new RuntimeException('Handler failed.', 0, $domain);

    $mapped = $mapper->map($wrapper);

    self::assertInstanceOf(NotFoundHttpException::class, $mapped);
    // The original top-level exception is kept as the cause so the trace
    // still shows the bus frame the failure travelled through.
    self::assertSame($wrapper, $mapped->getPrevious());
  }

  #[Test]
  public function testItReturnsAnUnrecognisedFailureUnchanged(): void
  {
    $mapper = new class () {
      use ImportExceptionMapperTrait;

      public function map(Throwable $exception): Throwable
      {
        return $this->mapImportException($exception);
      }
    };

    $failure = new RuntimeException('database is down');

    self::assertSame($failure, $mapper->map($failure));
  }
}
