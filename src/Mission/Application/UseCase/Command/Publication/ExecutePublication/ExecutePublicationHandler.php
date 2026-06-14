<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Command\Publication\ExecutePublication;

use Mission\Application\Port\Outbound\PublicationRepositoryPort;
use Mission\Application\Service\MissionIssueFinder;
use Mission\Domain\Exception\PublicationNotFoundException;
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
   * @param MissionIssueFinder $issueFinder the issue finder value
   */
  public function __construct(
    private PublicationRepositoryPort $publications,
    private MissionIssueFinder $issueFinder,
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
      $context = $this->publications->missionContext($publication->missionId);
      if (null === $context) {
        throw PublicationNotFoundException::withId($publication->id);
      }
      if ('submitted' !== $context->status || $context->revision !== $publication->missionRevision) {
        throw new RuntimeException('Mission changed before publication execution.');
      }
      $blockers = array_filter(
        $this->issueFinder->find($publication->missionId),
        static fn ($issue): bool => 'blocker' === $issue->severity,
      );
      if ([] !== $blockers) {
        throw new RuntimeException('Mission contains blocking validation issues.');
      }

      $this->publications->markProcessing($publication->id);
      $this->publications->publish($publication->id);
    } catch (Throwable $exception) {
      $this->publications->markFailed($publication->id, $exception->getMessage());
    }

    return new VoidResult();
  }
}
