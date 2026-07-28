<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Trait;

use InvalidArgumentException;
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingAttachmentNotFoundException, MessagingConflictException, MessagingNotFoundException, MessagingSubjectNotFoundException, MessagingValidationException};
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversTrait, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

/**
 * Test MessagingExceptionMapperTraitTest.
 *
 * Every Messaging processor, provider and controller derives its HTTP status
 * from this mapper, and the domain exception is normally buried under the
 * command bus' wrapper — so the walk down `getPrevious()` is what keeps a 403
 * or a 404 from surfacing as a 500.
 *
 * @category Trait Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(MessagingExceptionMapperTrait::class)]
final class MessagingExceptionMapperTraitTest extends TestCase
{
  /**
   * @return iterable<string, array{Throwable, class-string<Throwable>}>
   */
  public static function mappingProvider(): iterable
  {
    yield 'messaging access denied' => [new MessagingAccessDeniedException('Not a participant.'), AccessDeniedHttpException::class];
    yield 'organization access denied' => [OrganizationAccessDeniedException::missingPermission('organization.messaging.read'), AccessDeniedHttpException::class];
    yield 'conversation not found' => [MessagingNotFoundException::conversation('conversation-1'), NotFoundHttpException::class];
    yield 'subject not found' => [MessagingSubjectNotFoundException::withId('facility', 'facility-1'), NotFoundHttpException::class];
    yield 'attachment not found' => [MessagingAttachmentNotFoundException::withId('attachment-1'), NotFoundHttpException::class];
    yield 'validation failure' => [new MessagingValidationException('The body cannot be empty.'), UnprocessableEntityHttpException::class];
    yield 'conflict' => [new MessagingConflictException('The conversation already exists.'), ConflictHttpException::class];
    yield 'invalid argument' => [new InvalidArgumentException('Unknown subject type.'), BadRequestHttpException::class];
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('mappingProvider')]
  public function testItMapsADirectlyThrownException(Throwable $failure, string $expected): void
  {
    $mapped = $this->map($failure);

    self::assertInstanceOf($expected, $mapped);
    self::assertSame($failure->getMessage(), $mapped->getMessage());
    self::assertSame($failure, $mapped->getPrevious());
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('mappingProvider')]
  public function testItWalksThePreviousChain(Throwable $failure, string $expected): void
  {
    $wrapped = new RuntimeException('handler failed', 0, new RuntimeException('inner', 0, $failure));

    $mapped = $this->map($wrapped);

    self::assertInstanceOf($expected, $mapped);
    self::assertSame($failure->getMessage(), $mapped->getMessage());
    self::assertSame($wrapped, $mapped->getPrevious());
  }

  #[Test]
  public function testItReturnsAnUnknownExceptionUntouched(): void
  {
    $failure = new RuntimeException('database is down');

    self::assertSame($failure, $this->map($failure));
  }

  private function map(Throwable $exception): Throwable
  {
    $host = new class () {
      use MessagingExceptionMapperTrait;
    };

    $method = new ReflectionMethod($host, 'mapMessagingException');

    /** @var Throwable $mapped */
    $mapped = $method->invoke($host, $exception);

    return $mapped;
  }
}
