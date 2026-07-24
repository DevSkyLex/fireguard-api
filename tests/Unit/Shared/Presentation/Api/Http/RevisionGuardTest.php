<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Http\RevisionGuard;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{PreconditionFailedHttpException, PreconditionRequiredHttpException};

final class RevisionGuardTest extends TestCase
{
  #[Test]
  public function itAcceptsThePersistedRevision(): void
  {
    $this->guard('"revision-7"')->assertMatches(7);
    self::addToAssertionCount(1);
  }

  #[Test]
  public function itExposesTheExpectedRevisionForApplicationCommands(): void
  {
    self::assertSame(7, $this->guard('"revision-7"')->expectedRevision());
  }

  #[Test]
  public function itRejectsAWildcardRevision(): void
  {
    $this->expectException(PreconditionFailedHttpException::class);
    $this->guard('*')->expectedRevision();
  }

  #[Test]
  public function itRejectsAStaleRevision(): void
  {
    $this->expectException(PreconditionFailedHttpException::class);
    $this->guard('"revision-6"')->assertMatches(7);
  }

  #[Test]
  public function itRequiresAConditionalMutation(): void
  {
    $this->expectException(PreconditionRequiredHttpException::class);
    $this->guard(null)->assertMatches(7);
  }

  private function guard(?string $ifMatch): RevisionGuard
  {
    $request = Request::create('/api/resource/id', 'PATCH');
    if (null !== $ifMatch) {
      $request->headers->set('If-Match', $ifMatch);
    }
    $stack = new RequestStack();
    $stack->push($request);

    return new RevisionGuard($stack);
  }
}
