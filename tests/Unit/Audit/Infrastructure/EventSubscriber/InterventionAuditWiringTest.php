<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use Audit\Application\UseCase\Command\RecordAuditEvent\{RecordAuditEventCommand, RecordAuditEventResult};
use Audit\Infrastructure\EventSubscriber\AuditEventSubscriber;
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use Intervention\Domain\Event\Publication\{InterventionPublicationFailedEvent, InterventionPublishedEvent};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

use function array_keys;
use function count;
use function sprintf;

/**
 * Test InterventionAuditWiringTest.
 *
 * End-to-end wiring proof for the Intervention publication slice:
 * both domain events, dispatched through the real event-name
 * derivation of SymfonyEventDispatcherAdapter, reach
 * AuditEventSubscriber and produce the expected audit action,
 * subject and metadata.
 *
 * @category Event Subscriber Tests
 */
#[CoversClass(className: AuditEventSubscriber::class)]
final class InterventionAuditWiringTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testEveryInterventionDomainEventProducesItsAuditRecord(): void
  {
    $events = [
      'intervention.published' => new InterventionPublishedEvent(organizationId: 'org-1', interventionId: 'int-1', publicationId: 'pub-1'),
      'intervention.publication_failed' => new InterventionPublicationFailedEvent(organizationId: 'org-1', interventionId: 'int-1', publicationId: 'pub-1', reason: 'Intervention changed before publication execution.'),
    ];

    $expected = [
      'intervention.published' => ['intervention', 'int-1', ['publication_id' => 'pub-1', 'organization_id' => 'org-1']],
      'intervention.publication_failed' => ['intervention', 'int-1', ['publication_id' => 'pub-1', 'reason' => 'Intervention changed before publication execution.', 'organization_id' => 'org-1']],
    ];

    /** @var list<RecordAuditEventCommand> $recorded */
    $recorded = [];
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willReturnCallback(static function (RecordAuditEventCommand $command) use (&$recorded): RecordAuditEventResult {
        $recorded[] = $command;

        return new RecordAuditEventResult(eventId: 'event-1');
      });

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: null),
      requestStack: new RequestStack(),
      security: $security,
      logger: new NullLogger(),
    );

    $symfonyDispatcher = new EventDispatcher();
    $symfonyDispatcher->addSubscriber($subscriber);
    $adapter = new SymfonyEventDispatcherAdapter(
      eventDispatcher: $symfonyDispatcher,
      logger: new NullLogger(),
    );

    foreach ($events as $event) {
      $adapter->dispatch($event);
    }

    self::assertCount(count($expected), $recorded);

    $actions = [];
    foreach ($recorded as $command) {
      $actions[] = $command->action;
      [$subjectType, $subjectId, $metadata] = $expected[$command->action];
      self::assertSame($subjectType, $command->subjectType, sprintf('subjectType mismatch for %s', $command->action));
      self::assertSame($subjectId, $command->subjectId, sprintf('subjectId mismatch for %s', $command->action));
      self::assertSame($metadata, $command->metadata, sprintf('metadata mismatch for %s', $command->action));
    }

    self::assertSame(array_keys($expected), $actions);
  }
  // #endregion
}
