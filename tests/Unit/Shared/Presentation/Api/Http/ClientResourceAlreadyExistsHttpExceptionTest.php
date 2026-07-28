<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Http;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Http\ClientResourceAlreadyExistsHttpException;

#[CoversClass(ClientResourceAlreadyExistsHttpException::class)]
final class ClientResourceAlreadyExistsHttpExceptionTest extends TestCase
{
  #[Test]
  public function testExposesAStableProblemContract(): void
  {
    $exception = new ClientResourceAlreadyExistsHttpException(412);

    self::assertSame('/problems/client-resource-already-exists', $exception->getType());
    self::assertSame('Client resource already exists', $exception->getTitle());
    self::assertSame(412, $exception->getStatus());
    self::assertSame('A resource with this client identifier already exists.', $exception->getDetail());
  }

  #[Test]
  public function testExposesNoOccurrenceUri(): void
  {
    $exception = new ClientResourceAlreadyExistsHttpException(409);

    // getInstance() is the RFC 7807 "instance" member; this exception never
    // points at a specific occurrence, so it must stay absent.
    /** @phpstan-ignore staticMethod.alreadyNarrowedType */
    self::assertNull($exception->getInstance());
  }
}
