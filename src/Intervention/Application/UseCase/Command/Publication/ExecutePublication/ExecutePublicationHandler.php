<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Publication\ExecutePublication;

use Intervention\Application\Contract\Publication\{InterventionPublicationContext, PublicationView};
use Intervention\Application\Exception\PublicationExecutionException;
use Intervention\Application\Port\Outbound\PublicationRepositoryPort;
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Domain\Event\Publication\{InterventionPublicationFailedEvent, InterventionPublishedEvent};
use Intervention\Domain\Exception\PublicationNotFoundException;
use Intervention\Domain\ValueObject\PublicationStatus;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
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
    private TransactionManagerPort $transactions,
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
    if (!in_array($publication->status, [PublicationStatus::PENDING->value, PublicationStatus::PROCESSING->value], true)) {
      return new VoidResult();
    }

    $context = null;

    try {
      $context = $this->publications->interventionContext($publication->interventionId);
      $this->transactions->transactional(function () use ($publication, $context): void {
        $this->executePending($publication, $context);
      });
    } catch (Throwable $exception) {
      // The publication and its event rolled back together. Persist a failed
      // transition and its event in a fresh local transaction, even if ORM closed.
      $this->transactions->transactional(function () use ($publication, $context, $exception): void {
        $this->recordFailure($publication, $context, $exception);
      });
    }

    return new VoidResult();
  }

  private function executePending(PublicationView $publication, ?InterventionPublicationContext $context): void
  {
    if (null === $context) {
      throw PublicationNotFoundException::withId($publication->id);
    }
    if ('submitted' !== $context->status || $context->revision !== $publication->interventionRevision) {
      throw new PublicationExecutionException('Intervention changed before publication execution.');
    }
    $blockers = array_filter(
      $this->issueFinder->find($publication->interventionId),
      static fn ($issue): bool => 'blocker' === $issue->severity,
    );
    if ([] !== $blockers) {
      throw new PublicationExecutionException('Intervention contains blocking validation issues.');
    }

    $this->publications->markProcessing($publication->id);
    $published = $this->publications->publish($publication->id);
    if ($published) {
      $this->eventDispatcher->dispatch(new InterventionPublishedEvent(
        organizationId: $context->organizationId,
        interventionId: $publication->interventionId,
        publicationId: $publication->id,
        interventionName: $context->name,
        recipientMemberIds: $context->recipientMemberIds,
      ));
    }
  }

  private function recordFailure(PublicationView $publication, ?InterventionPublicationContext $context, Throwable $exception): void
  {
    $failed = $this->publications->markFailed($publication->id, $exception->getMessage());
    if (!$failed || null === $context) {
      return;
    }
    $this->eventDispatcher->dispatch(new InterventionPublicationFailedEvent(
      organizationId: $context->organizationId,
      interventionId: $publication->interventionId,
      publicationId: $publication->id,
      reason: $exception->getMessage(),
    ));
  }
}
