<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Publication\ExecutePublication;

use Intervention\Application\Port\Outbound\PublicationRepositoryPort;
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Domain\Exception\PublicationNotFoundException;
use RuntimeException;
use Shared\Application\Message\{CommandHandler, VoidResult};
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
   */
  public function __construct(
    private PublicationRepositoryPort $publications,
    private InterventionIssueFinder $issueFinder,
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
      $this->publications->publish($publication->id);
    } catch (Throwable $exception) {
      $this->publications->markFailed($publication->id, $exception->getMessage());
    }

    return new VoidResult();
  }
}
