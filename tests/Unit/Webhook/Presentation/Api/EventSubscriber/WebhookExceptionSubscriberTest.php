<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Presentation\Api\EventSubscriber;

use Organization\Application\Contract\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, UnprocessableEntityHttpException};
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;
use Webhook\Domain\Exception\WebhookValidationException;
use Webhook\Presentation\Api\EventSubscriber\WebhookExceptionSubscriber;
use Webhook\Presentation\Api\Resource\WebhookSubscriptionResource;

/** Central mapping preserves Webhook status and messages without affecting other resources. */
final class WebhookExceptionSubscriberTest extends TestCase
{
  #[Test]
  public function mapsWrappedValidationFailureTo422(): void
  {
    $event = $this->event(new RuntimeException('Bus envelope', previous: new WebhookValidationException('Invalid event type.')));
    new WebhookExceptionSubscriber()->onKernelException($event);
    self::assertInstanceOf(UnprocessableEntityHttpException::class, $event->getThrowable());
    self::assertSame('Invalid event type.', $event->getThrowable()->getMessage());
  }

  #[Test]
  public function mapsOrganizationDenialOnWebhookResourcesOnly(): void
  {
    $denial = OrganizationAccessDeniedException::missingPermission('organization.webhooks.manage');
    $event = $this->event($denial);
    new WebhookExceptionSubscriber()->onKernelException($event);
    self::assertInstanceOf(AccessDeniedHttpException::class, $event->getThrowable());
    $elsewhere = $this->event($denial);
    $elsewhere->getRequest()->attributes->set('_api_resource_class', 'Another\\Resource');
    new WebhookExceptionSubscriber()->onKernelException($elsewhere);
    self::assertSame($denial, $elsewhere->getThrowable());
  }

  #[Test]
  public function preservesUnknownProgrammingErrors(): void
  {
    $exception = new RuntimeException('Unexpected defect');
    $event = $this->event($exception);
    new WebhookExceptionSubscriber()->onKernelException($event);
    self::assertSame($exception, $event->getThrowable());
  }

  private function event(Throwable $exception): ExceptionEvent
  {
    $request = new Request(attributes: ['_api_resource_class' => WebhookSubscriptionResource::class]);

    return new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $exception);
  }
}
