<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Presentation\Api\Trait;

use Billing\Domain\Exception\{BillingCustomerNotFoundException, NoActiveSubscriptionException};
use Billing\Presentation\Api\Trait\ResolvesMessengerFailure;
use PHPUnit\Framework\Attributes\{CoversTrait, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Test ResolvesMessengerFailureTest.
 *
 * @category Trait Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(ResolvesMessengerFailure::class)]
final class ResolvesMessengerFailureTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64b01';

  #[Test]
  public function testItFindsTheExceptionItself(): void
  {
    $exception = NoActiveSubscriptionException::forOrganization(self::ORGANIZATION_ID);

    self::assertSame($exception, $this->resolve($exception, NoActiveSubscriptionException::class));
  }

  #[Test]
  public function testItWalksThePreviousChain(): void
  {
    $domain = NoActiveSubscriptionException::forOrganization(self::ORGANIZATION_ID);

    self::assertSame($domain, $this->resolve(MessengerRuntimeException::wrap($domain), NoActiveSubscriptionException::class));
  }

  #[Test]
  public function testItUnwrapsHandlerWrappedExceptions(): void
  {
    $domain = BillingCustomerNotFoundException::forOrganization(self::ORGANIZATION_ID);
    $handlerFailure = new HandlerFailedException(new Envelope(new stdClass()), [$domain]);

    self::assertSame(
      $domain,
      $this->resolve(MessengerRuntimeException::wrap($handlerFailure), BillingCustomerNotFoundException::class),
    );
  }

  #[Test]
  public function testItAcceptsSeveralCandidateClasses(): void
  {
    $domain = BillingCustomerNotFoundException::forOrganization(self::ORGANIZATION_ID);

    self::assertSame(
      $domain,
      $this->resolve($domain, NoActiveSubscriptionException::class, BillingCustomerNotFoundException::class),
    );
  }

  #[Test]
  public function testItReturnsNullWhenNothingMatches(): void
  {
    self::assertNull($this->resolve(new RuntimeException('Boom.'), NoActiveSubscriptionException::class));
  }

  /**
   * Method resolve.
   *
   * @param Throwable $exception the caught exception
   * @param class-string ...$classes the exception classes to look for
   *
   * @return ?Throwable the first matching exception, or null
   */
  private function resolve(Throwable $exception, string ...$classes): ?Throwable
  {
    $resolver = new class () {
      use ResolvesMessengerFailure;

      /**
       * Method resolve.
       *
       * @param Throwable $exception the caught exception
       * @param class-string ...$classes the exception classes to look for
       *
       * @return ?Throwable the first matching exception, or null
       */
      public function resolve(Throwable $exception, string ...$classes): ?Throwable
      {
        return $this->firstFailureOf($exception, ...$classes);
      }
    };

    return $resolver->resolve($exception, ...$classes);
  }
}
