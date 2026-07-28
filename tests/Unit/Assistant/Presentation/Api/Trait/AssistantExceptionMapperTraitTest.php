<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Presentation\Api\Trait;

use Assistant\Domain\Exception\{AssistantMessageIllegalStatusTransitionException, AssistantThreadNotFoundException, AssistantValidationException};
use Assistant\Domain\ValueObject\AssistantMessageStatus;
use Assistant\Presentation\Api\Trait\AssistantExceptionMapperTrait;
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversTrait, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

/**
 * Test AssistantExceptionMapperTraitTest.
 *
 * @category Trait Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(AssistantExceptionMapperTrait::class)]
final class AssistantExceptionMapperTraitTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testItMapsAnOrganizationDenialToAccessDenied(): void
  {
    $mapped = $this->map(OrganizationAccessDeniedException::missingPermission('assistant.read'));

    self::assertInstanceOf(AccessDeniedHttpException::class, $mapped);
  }

  #[Test]
  public function testItMapsAMissingThreadToNotFound(): void
  {
    $mapped = $this->map(AssistantThreadNotFoundException::withId('thread-1'));

    self::assertInstanceOf(NotFoundHttpException::class, $mapped);
  }

  #[Test]
  public function testItMapsAnIllegalTransitionToConflict(): void
  {
    $mapped = $this->map(AssistantMessageIllegalStatusTransitionException::forTransition(
      AssistantMessageStatus::COMPLETE,
      AssistantMessageStatus::STREAMING,
      'message-1',
    ));

    self::assertInstanceOf(ConflictHttpException::class, $mapped);
  }

  #[Test]
  public function testItMapsAValidationFailureToUnprocessableEntity(): void
  {
    $exception = AssistantValidationException::blankBody();

    $mapped = $this->map($exception);

    self::assertInstanceOf(UnprocessableEntityHttpException::class, $mapped);
    self::assertSame($exception->getMessage(), $mapped->getMessage());
  }

  #[Test]
  public function testItMapsAnInvalidArgumentToBadRequest(): void
  {
    $mapped = $this->map(new InvalidArgumentException('Malformed identifier.'));

    self::assertInstanceOf(BadRequestHttpException::class, $mapped);
    self::assertSame('Malformed identifier.', $mapped->getMessage());
  }

  #[Test]
  public function testItUnwrapsANestedDomainException(): void
  {
    $wrapped = new RuntimeException(
      'Handling failed.',
      0,
      AssistantThreadNotFoundException::withId('thread-9'),
    );

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
      use AssistantExceptionMapperTrait;

      public function __invoke(Throwable $exception): Throwable
      {
        return $this->mapAssistantException($exception);
      }
    };

    return $mapper($exception);
  }
  // #endregion
}
