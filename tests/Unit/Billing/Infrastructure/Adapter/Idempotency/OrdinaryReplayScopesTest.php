<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Infrastructure\Adapter\Idempotency;

use Billing\Infrastructure\Adapter\Idempotency\{BillingHttpIdempotencyOperationContributionAdapter, BillingHttpIdempotencyReplayAuthorizationAdapter};
use Calendar\Infrastructure\Adapter\Idempotency\{CalendarHttpIdempotencyOperationContributionAdapter, CalendarHttpIdempotencyReplayAuthorizationAdapter};
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\{HttpIdempotencyAuthorizationRequest, HttpIdempotencyOperation};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Port\Outbound\Idempotency\OrdinaryHttpReplayAuthorizationPort;
use Webhook\Infrastructure\Adapter\Idempotency\{WebhookHttpIdempotencyOperationContributionAdapter, WebhookHttpIdempotencyReplayAuthorizationAdapter};

use function array_replace;

/**
 * Class OrdinaryReplayScopesTest
 *
 * Covers current organization permission rejection and signed scopes after hard deletion.
 *
 * @category Test
 */
final class OrdinaryReplayScopesTest extends TestCase
{
  private const string USER = '019c6f20-4000-7000-8000-000000000001';

  private const string ORG = '019c6f20-4000-7000-8000-000000000002';

  private const string RESOURCE = '019c6f20-4000-7000-8000-000000000003';

  private const string OTHER = '019c6f20-4000-7000-8000-000000000004';

  /**
   * Method testBillingUrlReplayNeedsCurrentSettingsWritePermission
   */
  public function testBillingUrlReplayNeedsCurrentSettingsWritePermission(): void
  {
    $state = (object) ['permission' => true];
    $organizations = $this->organizations($state);
    $adapter = new BillingHttpIdempotencyReplayAuthorizationAdapter($this->ordinary(), $organizations);
    $request = $this->request(BillingHttpIdempotencyOperationContributionAdapter::definitions(), 'startBillingCheckout', ['organizationId' => self::ORG]);
    $facts = $adapter->authorize($request, null);
    self::assertSame(self::ORG, $facts['organizationId']);
    $proof = new IdempotencyProof($facts, new DateTimeImmutable('+5 minutes'));
    $state->permission = false;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
  }

  /**
   * Method testCalendarDeleteRetainsOriginalScopeAfterRowIsGone
   */
  public function testCalendarDeleteRetainsOriginalScopeAfterRowIsGone(): void
  {
    $state = (object) ['permission' => true, 'organization' => self::ORG];
    $main = $this->createStub(Connection::class);
    $main->method('fetchOne')->willReturnCallback(static fn (): string|false => $state->organization ?? false);
    $adapter = new CalendarHttpIdempotencyReplayAuthorizationAdapter($this->ordinary(), $this->organizations($state), $main);
    $request = $this->request(CalendarHttpIdempotencyOperationContributionAdapter::definitions(), 'deleteCalendarEvent', ['organizationId' => self::ORG, 'eventId' => self::RESOURCE]);
    $proof = $this->proof($adapter->authorize($request, null), 'calendar_event');
    $state->organization = null;
    self::assertSame(self::ORG, $adapter->authorize($request, $proof)['organizationId']);
    $state->permission = false;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
    $state->permission = true;
    $state->organization = self::OTHER;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
  }

  /**
   * Method testWebhookDeleteScopeCannotBeLostAndRedeliveryCannotBorrowAnotherParent
   */
  public function testWebhookDeleteScopeCannotBeLostAndRedeliveryCannotBorrowAnotherParent(): void
  {
    $state = (object) ['permission' => true, 'organization' => self::ORG, 'subscription' => self::RESOURCE];
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchOne')->willReturnCallback(static fn (): string|false => $state->organization ?? false);
    $connection->method('fetchAssociative')->willReturnCallback(static fn (): array => ['organization_id' => self::ORG, 'subscription_id' => $state->subscription]);
    $adapter = new WebhookHttpIdempotencyReplayAuthorizationAdapter($this->ordinary(), $this->organizations($state), $connection);
    $delete = $this->request(WebhookHttpIdempotencyOperationContributionAdapter::definitions(), 'deleteWebhookSubscription', ['organizationId' => self::ORG, 'webhookId' => self::RESOURCE]);
    $proof = $this->proof($adapter->authorize($delete, null), 'webhook_subscription');
    $state->organization = null;
    self::assertSame(self::RESOURCE, $adapter->authorize($delete, $proof)['webhookSubscriptionId']);
    $state->permission = false;
    $this->assertDenied(fn () => $adapter->authorize($delete, $proof));
    $state->permission = true;
    $state->organization = self::ORG;
    $redeliver = $this->request(WebhookHttpIdempotencyOperationContributionAdapter::definitions(), 'redeliverWebhook', ['organizationId' => self::ORG, 'webhookId' => self::RESOURCE, 'deliveryId' => self::OTHER]);
    self::assertSame(self::RESOURCE, $adapter->authorize($redeliver, null)['webhookSubscriptionId']);
    $state->subscription = self::OTHER;
    $this->assertDenied(fn () => $adapter->authorize($redeliver, null));
  }

  /**
   * Method ordinary
   *
   * @return OrdinaryHttpReplayAuthorizationPort actor proof seam
   */
  private function ordinary(): OrdinaryHttpReplayAuthorizationPort
  {
    $ordinary = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $ordinary->method('authorize')->willReturnCallback(static fn (HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof, ?string $organization): array => null === $proof ? ['organizationId' => $organization] : $proof->metadata);

    return $ordinary;
  }

  /**
   * Method organizations
   *
   * @param object{permission: bool} $state current grant
   *
   * @return FreshOrganizationAuthorizationPort uncached current authority seam
   */
  private function organizations(object $state): FreshOrganizationAuthorizationPort
  {
    $organization = $this->createStub(FreshOrganizationAuthorizationPort::class);
    $organization->method('hasPermission')->willReturnCallback(static fn (): bool => $state->permission);

    return $organization;
  }

  /**
   * Method request
   *
   * @param list<HttpIdempotencyOperation> $operations exact owner descriptors
   * @param string $name current route
   * @param array<string, string> $variables resolved variables
   *
   * @return HttpIdempotencyAuthorizationRequest authenticated request
   */
  private function request(array $operations, string $name, array $variables): HttpIdempotencyAuthorizationRequest
  {
    foreach ($operations as $operation) {
      if ($operation->name === $name) {
        return new HttpIdempotencyAuthorizationRequest(self::USER, null, $operation, '/api/test', $variables, []);
      }
    }
    self::fail('Missing owner route.');
  }

  /**
   * Method proof
   *
   * @param array<string, string> $facts original authorized scope
   * @param string $kind exact resource kind
   *
   * @return IdempotencyProof authenticated original identifier
   */
  private function proof(array $facts, string $kind): IdempotencyProof
  {
    return new IdempotencyProof(array_replace($facts, ['resourceId' => self::RESOURCE, 'resourceKind' => $kind]), new DateTimeImmutable('+5 minutes'));
  }

  /**
   * Method assertDenied
   *
   * @param callable(): mixed $attempt current gate
   */
  private function assertDenied(callable $attempt): void
  {
    try {
      $attempt();
      self::fail('Current owner scope must deny decoding.');
    } catch (IdempotencyDeniedException $failure) {
      self::assertSame('access_denied', $failure->reason);
    }
  }
}
