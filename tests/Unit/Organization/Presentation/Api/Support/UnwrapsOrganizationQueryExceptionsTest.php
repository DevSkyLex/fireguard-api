<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Support;

use InvalidArgumentException;
use LogicException;
use Organization\Presentation\Api\Support\UnwrapsOrganizationQueryExceptions;
use PHPUnit\Framework\Attributes\{CoversTrait, Test};
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Tests\Unit\Organization\Presentation\Api\Support\Double\QueryExceptionUnwrapper;

/**
 * Test UnwrapsOrganizationQueryExceptions.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(UnwrapsOrganizationQueryExceptions::class)]
final class UnwrapsOrganizationQueryExceptionsTest extends TestCase
{
  #[Test]
  public function testReturnsTheExceptionItselfWhenItAlreadyMatches(): void
  {
    $exception = new InvalidArgumentException('boom');

    self::assertSame($exception, $this->unwrapper()->find($exception, InvalidArgumentException::class));
  }

  #[Test]
  public function testFindsAMatchInsideAHandlerFailure(): void
  {
    $expected = new InvalidArgumentException('boom');
    $wrapper = new HandlerFailedException(new Envelope(new stdClass()), [$expected]);

    self::assertSame($expected, $this->unwrapper()->find($wrapper, InvalidArgumentException::class));
  }

  #[Test]
  public function testFollowsThePreviousExceptionChain(): void
  {
    $expected = new InvalidArgumentException('boom');
    $wrapper = new LogicException('outer', 0, new LogicException('middle', 0, $expected));

    self::assertSame($expected, $this->unwrapper()->find($wrapper, InvalidArgumentException::class));
  }

  #[Test]
  public function testReturnsNullWhenNothingMatches(): void
  {
    $wrapper = new HandlerFailedException(new Envelope(new stdClass()), [new LogicException('nope')]);

    self::assertNull($this->unwrapper()->find($wrapper, InvalidArgumentException::class));
  }

  /**
   * Builds a host object exposing the trait helper.
   */
  private function unwrapper(): QueryExceptionUnwrapper
  {
    return new QueryExceptionUnwrapper();
  }
}
