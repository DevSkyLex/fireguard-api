<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Publication\ExecutePublication;

use Intervention\Application\Port\Outbound\PublicationRepositoryPort;
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Domain\Event\Publication\{InterventionPublicationFailedEvent, InterventionPublishedEvent};
use Intervention\Domain\Exception\PublicationNotFoundException;
use RuntimeException;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Throwable;

use function array_filter;
use function in_array;

/**
 * UseCase ExecutePublicationHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExecutePublicationHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the ExecutePublicationHandler class.
   *
   * @since 1.0.0
   *
   * @param PublicationRepositoryPort $publications the publications value
   * @param InterventionIssueFinder $issueFinder the issue finder value
   * @param EventDispatcherPort $eventDispatcher the event dispatcher
   */
  public function __construct(
    private PublicationRepositoryPort $publications,
    private InterventionIssueFinder $issueFinder,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  /**
   * Method __invoke.
   *
   * Executes the   invoke operation.
   *
   * @since 1.0.0
   *
   * @param ExecutePublicationCommand $command the command value
   *
   * @return VoidResult the   invoke result
   */
  public function __invoke(ExecutePublicationCommand $command): VoidResult
  {
    $publication = $this->publications->find($command->publicationId);
    if (null === $publication) {
      throw PublicationNotFoundException::withId($command->publicationId);
    }
    if (!in_array($publication->status, ['pending', 'processing'], true)) {
      return new VoidResult();
    }

    $context = null;
    $published = false;
    $failed = false;
    $failureReason = null;

    try {
      $context = $this->publications->interventionContext($publication->interventionId);
      if (null === $context) {
        throw PublicationNotFoundException::withId($publication->id);
      }
      if ('submitted' !== $context->status || $context->revision !== $publication->interventionRevision) {
        throw new RuntimeException('Intervention changed before publication execution.');
      }
      $blockers = array_filter(
        $this->issueFinder->find($publication->interventionId),
        static fn ($issue): bool => 'blocker' === $issue->severity,
      );
      if ([] !== $blockers) {
        throw new RuntimeException('Intervention contains blocking validation issues.');
      }

      $this->publications->markProcessing($publication->id);
      $published = $this->publications->publish($publication->id);
    } catch (Throwable $exception) {
      $failed = $this->publications->markFailed($publication->id, $exception->getMessage());
      $failureReason = $exception->getMessage();
    }

    // Audit ledger: emitted AFTER the try/catch — publish() commits inside its
    // own wrapInTransaction, so the published event is post-commit; and a
    // hypothetical dispatch failure must never turn a committed publication
    // into a markFailed. The dispatches are gated on the DURABLE transition
    // reported by the adapter (not on local control flow), so an at-least-once
    // redelivery racing a concurrent worker can neither duplicate the
    // published row nor ledger a false failure for a completed publication.
    // The failure event also needs the intervention context (organization
    // scope): failures before the context is resolved are not ledgered
    // (nothing to scope them to) but stay on the publication record.
    if ($published && null !== $context) {
      $this->eventDispatcher->dispatch(new InterventionPublishedEvent(
        organizationId: $context->organizationId,
        interventionId: $publication->interventionId,
        publicationId: $publication->id,
      ));
    } elseif ($failed && null !== $failureReason && null !== $context) {
      $this->eventDispatcher->dispatch(new InterventionPublicationFailedEvent(
        organizationId: $context->organizationId,
        interventionId: $publication->interventionId,
        publicationId: $publication->id,
        reason: $failureReason,
      ));
    }

    return new VoidResult();
  }
}
