<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Presentation\Api\Trait;

use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversTrait, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;
use Webhook\Domain\Exception\{WebhookDeliveryNotFoundException, WebhookSubscriptionNotFoundException, WebhookValidationException};
use Webhook\Presentation\Api\Trait\WebhookExceptionMapperTrait;

/**
 * Test WebhookExceptionMapperTraitTest.
 *
 * @category Trait Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(WebhookExceptionMapperTrait::class)]
final class WebhookExceptionMapperTraitTest extends TestCase
{
  #[Test]
  public function testItMapsAnOrganizationDenialToAccessDenied(): void
  {
    $mapped = $this->map(OrganizationAccessDeniedException::missingPermission('organization.read'));

    self::assertInstanceOf(AccessDeniedHttpException::class, $mapped);
  }

  #[Test]
  public function testItMapsAMissingSubscriptionToNotFound(): void
  {
    $mapped = $this->map(WebhookSubscriptionNotFoundException::withId('sub-1'));

    self::assertInstanceOf(NotFoundHttpException::class, $mapped);
  }

  #[Test]
  public function testItMapsAMissingDeliveryToNotFound(): void
  {
    $mapped = $this->map(WebhookDeliveryNotFoundException::withId('delivery-1'));

    self::assertInstanceOf(NotFoundHttpException::class, $mapped);
  }

  #[Test]
  public function testItMapsAValidationFailureToUnprocessableEntity(): void
  {
    $mapped = $this->map(new WebhookValidationException('Invalid URL.'));

    self::assertInstanceOf(UnprocessableEntityHttpException::class, $mapped);
    self::assertSame('Invalid URL.', $mapped->getMessage());
  }

  #[Test]
  public function testItMapsAnInvalidArgumentToBadRequest(): void
  {
    $mapped = $this->map(new InvalidArgumentException('Bad identifier.'));

    self::assertInstanceOf(BadRequestHttpException::class, $mapped);
  }

  #[Test]
  public function testItUnwrapsTheExceptionChain(): void
  {
    $wrapped = new RuntimeException('Handling failed.', 0, WebhookSubscriptionNotFoundException::withId('sub-1'));

    $mapped = $this->map($wrapped);

    self::assertInstanceOf(NotFoundHttpException::class, $mapped);
    self::assertSame($wrapped, $mapped->getPrevious());
  }

  #[Test]
  public function testItReturnsAnUnknownExceptionUnchanged(): void
  {
    $exception = new RuntimeException('Boom.');

    self::assertSame($exception, $this->map($exception));
  }

  /**
   * Method map.
   *
   * @param Throwable $exception the exception to map
   *
   * @return Throwable the mapped exception
   */
  private function map(Throwable $exception): Throwable
  {
    $mapper = new class () {
      use WebhookExceptionMapperTrait;

      /**
       * Method map.
       *
       * @param Throwable $exception the exception to map
       *
       * @return Throwable the mapped exception
       */
      public function map(Throwable $exception): Throwable
      {
        return $this->mapWebhookException($exception);
      }
    };

    return $mapper->map($exception);
  }
}
