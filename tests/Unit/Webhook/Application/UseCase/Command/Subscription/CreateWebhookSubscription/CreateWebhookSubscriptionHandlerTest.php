<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\UseCase\Command\Subscription\CreateWebhookSubscription;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{EventDispatcherPort, UuidGeneratorPort};
use Webhook\Application\Port\Outbound\{WebhookSecretCipherPort, WebhookSubscriptionRepositoryPort};
use Webhook\Application\UseCase\Command\Subscription\CreateWebhookSubscription\{CreateWebhookSubscriptionCommand, CreateWebhookSubscriptionHandler, CreateWebhookSubscriptionResult};
use Webhook\Domain\Event\Subscription\WebhookSubscriptionCreatedEvent;
use Webhook\Domain\Exception\WebhookValidationException;
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\Service\WebhookUrlPolicy;

/**
 * Test CreateWebhookSubscriptionHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateWebhookSubscriptionHandler::class)]
final class CreateWebhookSubscriptionHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string USER_ID = 'user-1';

  #[Test]
  public function itCreatesASubscriptionAndReturnsThePlaintextSecretOnce(): void
  {
    $repository = $this->createMock(WebhookSubscriptionRepositoryPort::class);
    $repository->method('countActiveByOrganization')->willReturn(0);
    $repository->expects(self::once())->method('save')->with(self::isInstanceOf(WebhookSubscription::class));

    $cipher = $this->createMock(WebhookSecretCipherPort::class);
    $cipher->expects(self::once())
      ->method('encrypt')
      ->with(self::stringStartsWith('whsec_'))
      ->willReturn('ENCRYPTED');

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with(self::USER_ID, self::ORGANIZATION_ID, ['organization.webhooks.manage']);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (WebhookSubscriptionCreatedEvent $event): bool {
        self::assertSame(self::ORGANIZATION_ID, $event->organizationId);
        self::assertSame('example.com', $event->urlHost);
        self::assertSame(['intervention.published'], $event->eventTypes);
        self::assertSame(self::USER_ID, $event->actorUserId);

        return true;
      }));

    $handler = new CreateWebhookSubscriptionHandler(
      $repository,
      $cipher,
      new WebhookUrlPolicy(allowInsecureUrls: false),
      $authorization,
      $this->uuidFactory(),
      $eventDispatcher,
    );

    $result = $handler->__invoke(new CreateWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      url: 'https://example.com/webhooks/fireguard',
      eventTypes: ['intervention.published'],
      description: 'My integration',
    ));

    self::assertInstanceOf(CreateWebhookSubscriptionResult::class, $result);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame('https://example.com/webhooks/fireguard', $result->url);
    self::assertTrue($result->isActive);
    self::assertStringStartsWith('whsec_', $result->secret);
  }

  #[Test]
  public function itThrowsWhenThePermissionIsMissing(): void
  {
    $repository = $this->createMock(WebhookSubscriptionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')
      ->willThrowException(new OrganizationAccessDeniedException('Missing permission.'));

    $handler = new CreateWebhookSubscriptionHandler(
      $repository,
      $this->createStub(WebhookSecretCipherPort::class),
      new WebhookUrlPolicy(),
      $authorization,
      $this->uuidFactory(),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new CreateWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      url: 'https://example.com/hook',
      eventTypes: ['intervention.published'],
    ));
  }

  #[Test]
  public function itThrowsWhenAnEventTypeIsNotInTheCuratedAllowlist(): void
  {
    $repository = $this->createMock(WebhookSubscriptionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $handler = new CreateWebhookSubscriptionHandler(
      $repository,
      $this->createStub(WebhookSecretCipherPort::class),
      new WebhookUrlPolicy(),
      $authorization,
      $this->uuidFactory(),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(WebhookValidationException::class);

    $handler->__invoke(new CreateWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      url: 'https://example.com/hook',
      eventTypes: ['auth.user_logged_in_event'],
    ));
  }

  #[Test]
  public function itThrowsWhenTheActiveSubscriptionCapIsReached(): void
  {
    $repository = $this->createMock(WebhookSubscriptionRepositoryPort::class);
    $repository->method('countActiveByOrganization')->willReturn(20);
    $repository->expects(self::never())->method('save');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $handler = new CreateWebhookSubscriptionHandler(
      $repository,
      $this->createStub(WebhookSecretCipherPort::class),
      new WebhookUrlPolicy(),
      $authorization,
      $this->uuidFactory(),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(WebhookValidationException::class);

    $handler->__invoke(new CreateWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      url: 'https://example.com/hook',
      eventTypes: ['intervention.published'],
    ));
  }

  #[Test]
  public function itThrowsWhenTheUrlIsInsecure(): void
  {
    $repository = $this->createStub(WebhookSubscriptionRepositoryPort::class);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $handler = new CreateWebhookSubscriptionHandler(
      $repository,
      $this->createStub(WebhookSecretCipherPort::class),
      new WebhookUrlPolicy(allowInsecureUrls: false),
      $authorization,
      $this->uuidFactory(),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(WebhookValidationException::class);

    $handler->__invoke(new CreateWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      url: 'http://example.com/hook',
      eventTypes: ['intervention.published'],
    ));
  }

  #[Test]
  public function itRejectsAnEmptyEventTypeAllowlist(): void
  {
    $repository = $this->createMock(WebhookSubscriptionRepositoryPort::class);
    $repository->method('countActiveByOrganization')->willReturn(0);
    $repository->expects(self::never())->method('save');

    $handler = new CreateWebhookSubscriptionHandler(
      $repository,
      $this->createStub(WebhookSecretCipherPort::class),
      new WebhookUrlPolicy(allowInsecureUrls: false),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->uuidFactory(),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(WebhookValidationException::class);
    $this->expectExceptionMessage('At least one event type is required.');

    $handler->__invoke(new CreateWebhookSubscriptionCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      url: 'https://example.com/webhooks/fireguard',
      eventTypes: [],
    ));
  }

  /**
   * Method uuidFactory.
   *
   * @return UuidFactory a uuid factory generating deterministic-enough raw ids
   */
  private function uuidFactory(): UuidFactory
  {
    $generator = $this->createStub(UuidGeneratorPort::class);
    $generator->method('generate')->willReturn('018f0b68-6758-7a12-8a1d-3f0d97f64aff');

    return new UuidFactory($generator);
  }
}
