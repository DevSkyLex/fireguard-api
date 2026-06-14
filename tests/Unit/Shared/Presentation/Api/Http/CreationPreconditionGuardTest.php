<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Http;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Http\CreationPreconditionGuard;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{PreconditionFailedHttpException, PreconditionRequiredHttpException};

#[CoversClass(CreationPreconditionGuard::class)]
final class CreationPreconditionGuardTest extends TestCase
{
  #[Test]
  public function testAcceptsCreateOnlyPrecondition(): void
  {
    $this->guard('*')->assertCreateOnly();

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testRejectsMissingPrecondition(): void
  {
    $this->expectException(PreconditionRequiredHttpException::class);

    $this->guard()->assertCreateOnly();
  }

  #[Test]
  public function testRejectsDifferentPrecondition(): void
  {
    $this->expectException(PreconditionFailedHttpException::class);

    $this->guard('"revision-1"')->assertCreateOnly();
  }

  private function guard(?string $ifNoneMatch = null): CreationPreconditionGuard
  {
    $request = Request::create('/api/equipment/client-id', 'PUT');
    if (null !== $ifNoneMatch) {
      $request->headers->set('If-None-Match', $ifNoneMatch);
    }
    $stack = new RequestStack();
    $stack->push($request);

    return new CreationPreconditionGuard($stack);
  }
}
