<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Messaging\Outbox;

use Organization\Application\Contract\Event\OrganizationSettingsUpdatedEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;
use RuntimeException;
use Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter;
use Shared\Infrastructure\Messaging\Outbox\{DeliverOutboxEventHandler, DurableEventContext, OutboxEvent};
use Symfony\Component\EventDispatcher\EventDispatcher;

use function serialize;
use function str_replace;
use function strlen;
use function unserialize;

final class OutboxActorContextTest extends TestCase
{
  public function testNestedFailureRestoresOuterActorAndThenClearsContext(): void
  {
    $context = new DurableEventContext();
    $context->deliver('outer', function () use ($context): void {
      try {
        $context->deliver('system', static function () use ($context): void {
          self::assertNull($context->actorUserId());

          throw new RuntimeException('consumer failed');
        });
      } catch (RuntimeException) {
        self::assertSame('outer', $context->eventId());
        self::assertSame('actor', $context->actorUserId());
      }
    }, 'actor');
    self::assertNull($context->eventId());
    self::assertNull($context->actorUserId());
  }

  public function testOldSerializedEnvelopeWithoutActorStillDeliversAsSystem(): void
  {
    $legacy = new ReflectionClass(OutboxEvent::class)->newInstanceWithoutConstructor();
    new ReflectionClass(OutboxEvent::class)->getProperty('id')->setValue($legacy, 'legacy');
    new ReflectionClass(OutboxEvent::class)->getProperty('event')->setValue($legacy, new OrganizationSettingsUpdatedEvent('org', ['compliance']));
    $message = unserialize(serialize($legacy));
    self::assertInstanceOf(OutboxEvent::class, $message);
    $context = new DurableEventContext();
    $dispatcher = new EventDispatcher();
    $calls = 0;
    $dispatcher->addListener('organization.organization_settings_updated_event', static function () use ($context, &$calls): void {
      ++$calls;
      self::assertSame('legacy', $context->eventId());
      self::assertNull($context->actorUserId());
    });
    $handler = new DeliverOutboxEventHandler($context, new SymfonyEventDispatcherAdapter($dispatcher, new NullLogger()));
    $handler($message);
    self::assertSame(1, $calls);
    self::assertNull($context->eventId());
  }

  public function testLegacySettingsClassStillDeserializesToPublicContract(): void
  {
    $serialized = serialize(new OrganizationSettingsUpdatedEvent('org', ['compliance']));
    $publicName = OrganizationSettingsUpdatedEvent::class;
    $legacyName = 'Organization\\Domain\\Event\\Organization\\OrganizationSettingsUpdatedEvent';
    $serialized = str_replace('O:' . strlen($publicName) . ':"' . $publicName . '"', 'O:' . strlen($legacyName) . ':"' . $legacyName . '"', $serialized);
    $event = unserialize($serialized);
    self::assertInstanceOf(OrganizationSettingsUpdatedEvent::class, $event);
    self::assertSame('org', $event->organizationId);
    self::assertSame(['compliance'], $event->changedFields);
  }
}
