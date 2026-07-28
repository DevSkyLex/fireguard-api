<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Presentation\Api\Trait;

use Calendar\Domain\Exception\{CalendarEventNotFoundException, CalendarEventValidationException};
use Calendar\Presentation\Api\Trait\CalendarExceptionMapperTrait;
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversTrait, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

/**
 * Test CalendarExceptionMapperTraitTest.
 *
 * @category Trait Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(CalendarExceptionMapperTrait::class)]
final class CalendarExceptionMapperTraitTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testItMapsAnOrganizationDenialToAccessDenied(): void
  {
    $mapped = $this->map(OrganizationAccessDeniedException::missingPermission('calendar.read'));

    self::assertInstanceOf(AccessDeniedHttpException::class, $mapped);
  }

  #[Test]
  public function testItMapsAMissingEventToNotFound(): void
  {
    $mapped = $this->map(CalendarEventNotFoundException::withId('event-1'));

    self::assertInstanceOf(NotFoundHttpException::class, $mapped);
  }

  #[Test]
  public function testItMapsAValidationFailureToUnprocessableEntity(): void
  {
    $exception = CalendarEventValidationException::endBeforeStart();

    $mapped = $this->map($exception);

    self::assertInstanceOf(UnprocessableEntityHttpException::class, $mapped);
    self::assertSame($exception->getMessage(), $mapped->getMessage());
  }

  #[Test]
  public function testItMapsAnInvalidArgumentToBadRequest(): void
  {
    $mapped = $this->map(new InvalidArgumentException('Malformed date.'));

    self::assertInstanceOf(BadRequestHttpException::class, $mapped);
    self::assertSame('Malformed date.', $mapped->getMessage());
  }

  #[Test]
  public function testItUnwrapsANestedDomainException(): void
  {
    $wrapped = new RuntimeException('Handling failed.', 0, CalendarEventNotFoundException::withId('event-9'));

    $mapped = $this->map($wrapped);

    self::assertInstanceOf(NotFoundHttpException::class, $mapped);
    self::assertSame($wrapped, $mapped->getPrevious());
  }

  #[Test]
  public function testItReturnsUnknownExceptionsUnchanged(): void
  {
    $exception = new RuntimeException('Something else went wrong.');

    self::assertSame($exception, $this->map($exception));
  }
  // #endregion

  // #region Helpers
  private function map(Throwable $exception): Throwable
  {
    $mapper = new class () {
      use CalendarExceptionMapperTrait;

      public function __invoke(Throwable $exception): Throwable
      {
        return $this->mapCalendarException($exception);
      }
    };

    return $mapper($exception);
  }
  // #endregion
}
