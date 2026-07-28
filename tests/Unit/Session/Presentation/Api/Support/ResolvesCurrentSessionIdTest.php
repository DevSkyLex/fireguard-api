<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Presentation\Api\Support;

use PHPUnit\Framework\Attributes\{CoversTrait, Test};
use PHPUnit\Framework\TestCase;
use Session\Presentation\Api\Support\ResolvesCurrentSessionId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Test ResolvesCurrentSessionIdTest.
 *
 * "This device" in the session list is decided by this resolution, so it has
 * to degrade to an empty string rather than throw when the context carries
 * no usable request — an unresolvable current session must simply mark no
 * row as current.
 *
 * @category Support Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(ResolvesCurrentSessionId::class)]
final class ResolvesCurrentSessionIdTest extends TestCase
{
  #[Test]
  public function testItReturnsTheIdOfTheSessionBackingTheRequest(): void
  {
    $session = new Session(new MockArraySessionStorage());
    $session->setId('session-id-42');

    $request = new Request();
    $request->setSession($session);

    $resolver = new class () {
      use ResolvesCurrentSessionId;

      /**
       * @param array<string, mixed> $context the provider/processor context
       */
      public function resolve(array $context): string
      {
        return $this->resolveCurrentSessionId($context);
      }
    };

    self::assertSame('session-id-42', $resolver->resolve(['request' => $request]));
  }

  #[Test]
  public function testItReturnsAnEmptyStringWhenTheContextCarriesNoRequest(): void
  {
    $resolver = new class () {
      use ResolvesCurrentSessionId;

      /**
       * @param array<string, mixed> $context the provider/processor context
       */
      public function resolve(array $context): string
      {
        return $this->resolveCurrentSessionId($context);
      }
    };

    self::assertSame('', $resolver->resolve([]));
  }

  #[Test]
  public function testItIgnoresAContextRequestThatIsNotARequestObject(): void
  {
    $resolver = new class () {
      use ResolvesCurrentSessionId;

      /**
       * @param array<string, mixed> $context the provider/processor context
       */
      public function resolve(array $context): string
      {
        return $this->resolveCurrentSessionId($context);
      }
    };

    self::assertSame('', $resolver->resolve(['request' => 'not-a-request']));
  }

  #[Test]
  public function testItReturnsAnEmptyStringWhenTheSessionHasNoIdYet(): void
  {
    $request = new Request();
    $request->setSession(new Session(new MockArraySessionStorage()));

    $resolver = new class () {
      use ResolvesCurrentSessionId;

      /**
       * @param array<string, mixed> $context the provider/processor context
       */
      public function resolve(array $context): string
      {
        return $this->resolveCurrentSessionId($context);
      }
    };

    self::assertSame('', $resolver->resolve(['request' => $request]));
  }
}
