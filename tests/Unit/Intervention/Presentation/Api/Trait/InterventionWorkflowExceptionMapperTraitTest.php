<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Trait;

use Intervention\Domain\Exception\{
  InterventionAccessDeniedException,
  InterventionConflictException,
  InterventionNotFoundException,
  InterventionPreconditionFailedException,
  InterventionPreconditionRequiredException,
  InterventionValidationException
};
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversTrait, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException,
  PreconditionFailedHttpException,
  PreconditionRequiredHttpException,
  UnprocessableEntityHttpException
};
use Throwable;

/**
 * Test InterventionWorkflowExceptionMapperTraitTest.
 *
 * @category Trait Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(InterventionWorkflowExceptionMapperTrait::class)]
final class InterventionWorkflowExceptionMapperTraitTest extends TestCase
{
  // #region Methods
  /**
   * @return iterable<string, array{Throwable, class-string<Throwable>}>
   */
  public static function domainExceptionProvider(): iterable
  {
    yield 'access denied' => [new InterventionAccessDeniedException('nope'), AccessDeniedHttpException::class];

    yield 'not found' => [InterventionNotFoundException::withId('550e8400-e29b-41d4-a716-446655440000'), NotFoundHttpException::class];

    yield 'precondition required' => [new InterventionPreconditionRequiredException('if-match'), PreconditionRequiredHttpException::class];

    yield 'precondition failed' => [new InterventionPreconditionFailedException('stale'), PreconditionFailedHttpException::class];

    yield 'validation' => [new InterventionValidationException('invalid'), UnprocessableEntityHttpException::class];

    yield 'conflict' => [new InterventionConflictException('conflict'), ConflictHttpException::class];

    yield 'invalid argument' => [new InvalidArgumentException('bad iri'), BadRequestHttpException::class];
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('domainExceptionProvider')]
  public function testEachDomainExceptionMapsToItsHttpEquivalent(Throwable $exception, string $expected): void
  {
    $mapped = $this->map($exception);

    self::assertInstanceOf($expected, $mapped);
    self::assertSame($exception->getMessage(), $mapped->getMessage());
    self::assertSame($exception, $mapped->getPrevious());
  }

  #[Test]
  public function testItUnwrapsNestedCauses(): void
  {
    $wrapper = new RuntimeException('bus failure', 0, new InterventionConflictException('revision mismatch'));

    $mapped = $this->map($wrapper);

    self::assertInstanceOf(ConflictHttpException::class, $mapped);
    self::assertSame('revision mismatch', $mapped->getMessage());
    self::assertSame($wrapper, $mapped->getPrevious());
  }

  #[Test]
  public function testItReturnsAnUnknownExceptionUnchanged(): void
  {
    $exception = new RuntimeException('boom');

    self::assertSame($exception, $this->map($exception));
  }

  private function map(Throwable $exception): Throwable
  {
    $host = new class () {
      use InterventionWorkflowExceptionMapperTrait;
    };

    $method = new ReflectionMethod($host, 'mapWorkflowException');

    /** @var Throwable $result */
    $result = $method->invoke($host, $exception);

    return $result;
  }
  // #endregion
}
