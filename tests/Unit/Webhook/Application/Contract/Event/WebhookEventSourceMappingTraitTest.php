<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\Contract\Event;

use DateTimeImmutable;
use Equipment\Infrastructure\Adapter\Webhook\EquipmentWebhookEventSourceAdapter;
use Facility\Infrastructure\Adapter\Webhook\FacilityWebhookEventSourceAdapter;
use Inspection\Infrastructure\Adapter\Webhook\InspectionWebhookEventSourceAdapter;
use Intervention\Infrastructure\Adapter\Webhook\InterventionWebhookEventSourceAdapter;
use InvalidArgumentException;
use Maintenance\Infrastructure\Adapter\Webhook\MaintenanceWebhookEventSourceAdapter;
use PHPUnit\Framework\Attributes\{CoversTrait, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use stdClass;
use Webhook\Application\Contract\Event\{WebhookEventDescriptor, WebhookEventProjection, WebhookEventSourceMappingTrait};
use Webhook\Application\Port\Inbound\WebhookEventPublisherPort;
use Webhook\Application\Port\Outbound\WebhookEventSourcePort;

/**
 * Test WebhookEventSourceMappingTraitTest.
 *
 * Verifies the published mapping mechanism independently of FireGuard event definitions.
 *
 * @category Contract Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @phpstan-type OwnerSource EquipmentWebhookEventSourceAdapter|FacilityWebhookEventSourceAdapter|InspectionWebhookEventSourceAdapter|InterventionWebhookEventSourceAdapter|MaintenanceWebhookEventSourceAdapter
 */
#[CoversTrait(WebhookEventSourceMappingTrait::class)]
final class WebhookEventSourceMappingTraitTest extends TestCase
{
  #[Test]
  public function publishesAnOwnerSelectedProjectionWithoutPrivateFields(): void
  {
    $occurredAt = new DateTimeImmutable('2026-10-12T10:12:00+00:00');
    $event = new ContributedWebhookSourceEvent('organization-1', $occurredAt, 'invoice-1', 'private customer details');
    $publisher = $this->createMock(WebhookEventPublisherPort::class);
    $publisher->expects(self::once())->method('publish')->with(self::callback(
      static function (WebhookEventProjection $projection) use ($occurredAt): bool {
        self::assertSame('organization-1', $projection->organizationId);
        self::assertSame('invoice.issued', $projection->type->value);
        self::assertSame(['invoiceId' => 'invoice-1'], $projection->data);
        self::assertSame($occurredAt, $projection->occurredAt);

        return true;
      },
    ));
    $source = $this->source($publisher);

    self::assertSame(['invoice.issued_event' => 'onEvent'], $source::getSubscribedEvents());
    self::assertEquals([new WebhookEventDescriptor('invoice.issued', 'Invoice Issued', 'invoice.issued_event')], $source->descriptors());
    $source->onEvent($event, 'invoice.issued_event');
  }

  /**
   * Method rejectsAnUnregisteredEventWithoutPublishing.
   *
   * @param class-string<OwnerSource> $sourceClass the owner source adapter
   */
  #[Test]
  #[DataProvider('owners')]
  public function rejectsAnUnregisteredEventWithoutPublishing(string $sourceClass): void
  {
    $publisher = $this->createMock(WebhookEventPublisherPort::class);
    $publisher->expects(self::never())->method('publish');
    $source = new $sourceClass($publisher);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown webhook source event.');
    $source->onEvent(new stdClass(), 'invoice.unknown_event');
  }

  /**
   * Method rejectsAnEventWhoseClassDoesNotMatchItsOwnerRegistration.
   *
   * @param class-string<OwnerSource> $sourceClass the owner source adapter
   */
  #[Test]
  #[DataProvider('owners')]
  public function rejectsAnEventWhoseClassDoesNotMatchItsOwnerRegistration(string $sourceClass): void
  {
    $publisher = $this->createMock(WebhookEventPublisherPort::class);
    $publisher->expects(self::never())->method('publish');
    $source = new $sourceClass($publisher);
    $eventName = $source->descriptors()[0]->sourceEventName;

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Webhook source event class does not match its registration.');
    $source->onEvent(new stdClass(), $eventName);
  }

  #[Test]
  #[DataProvider('invalidContexts')]
  public function rejectsAnInvalidSourceContextWithoutPublishing(mixed $organizationId, mixed $occurredAt): void
  {
    $publisher = $this->createMock(WebhookEventPublisherPort::class);
    $publisher->expects(self::never())->method('publish');
    $source = $this->source($publisher);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid webhook source context.');
    $source->onEvent(new ContributedWebhookSourceEvent($organizationId, $occurredAt, 'invoice-1', 'private'), 'invoice.issued_event');
  }

  /**
   * Method owners.
   *
   * @return iterable<string, array{class-string<OwnerSource>}> the existing owner source classes
   */
  public static function owners(): iterable
  {
    yield 'equipment' => [EquipmentWebhookEventSourceAdapter::class];
    yield 'facility' => [FacilityWebhookEventSourceAdapter::class];
    yield 'inspection' => [InspectionWebhookEventSourceAdapter::class];
    yield 'intervention' => [InterventionWebhookEventSourceAdapter::class];
    yield 'maintenance' => [MaintenanceWebhookEventSourceAdapter::class];
  }

  /**
   * Method invalidContexts.
   *
   * @return iterable<string, array{mixed, mixed}> malformed source contexts
   */
  public static function invalidContexts(): iterable
  {
    yield 'missing organization' => [null, new DateTimeImmutable()];
    yield 'non-string organization' => [42, new DateTimeImmutable()];
    yield 'missing occurrence' => ['organization-1', null];
    yield 'non-date occurrence' => ['organization-1', '2026-10-12T10:12:00+00:00'];
  }

  /**
   * Method source.
   *
   * @param WebhookEventPublisherPort $publisher the publisher double
   *
   * @return ContributedWebhookEventSource the independent owner contribution
   */
  private function source(WebhookEventPublisherPort $publisher): ContributedWebhookEventSource
  {
    return new ContributedWebhookEventSource($publisher);
  }
}

/**
 * Class ContributedWebhookEventSource.
 *
 * Defines a new public event without changing the reusable mechanism.
 *
 * @category Test Double
 */
final readonly class ContributedWebhookEventSource implements WebhookEventSourcePort
{
  use WebhookEventSourceMappingTrait;

  /**
   * Method sourceDefinitions.
   *
   * @return array<string, array{class: class-string, type: string, fields: list<string>}> the fictional owner's public mapping
   */
  protected static function sourceDefinitions(): array
  {
    return ['invoice.issued_event' => ['class' => ContributedWebhookSourceEvent::class, 'type' => 'invoice.issued', 'fields' => ['invoiceId']]];
  }
}

/**
 * Class ContributedWebhookSourceEvent.
 *
 * @category Test Double
 */
final readonly class ContributedWebhookSourceEvent
{
  /**
   * Constructor.
   *
   * @param mixed $organizationId the context value under test
   * @param mixed $occurredAt the context value under test
   * @param string $invoiceId the public subject identity
   * @param string $privateDetails excluded private source data
   */
  public function __construct(public mixed $organizationId, public mixed $occurredAt, public string $invoiceId, public string $privateDetails)
  {
  }
}
