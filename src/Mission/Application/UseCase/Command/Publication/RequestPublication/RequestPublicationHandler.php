<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Command\Publication\RequestPublication;

use Mission\Application\Port\Outbound\{PublicationQueuePort, PublicationRepositoryPort};
use Mission\Application\Service\MissionIssueFinder;
use Mission\Domain\Exception\{MissionAccessDeniedException, MissionBlockedException, MissionConflictException, MissionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;

use function array_filter;

/**
 * UseCase RequestPublicationHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPublicationHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the RequestPublicationHandler class.
   *
   * @since 1.0.0
   *
   * @param PublicationRepositoryPort $publications the publications value
   * @param PublicationQueuePort $queue the queue value
   * @param MissionIssueFinder $issueFinder the issue finder value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param UuidFactory $uuidFactory the uuid factory value
   */
  public function __construct(
    private PublicationRepositoryPort $publications,
    private PublicationQueuePort $queue,
    private MissionIssueFinder $issueFinder,
    private OrganizationAuthorizationPort $authorization,
    private UuidFactory $uuidFactory,
  ) {
  }

  /**
   * Method __invoke.
   *
   * Executes the   invoke operation.
   *
   * @since 1.0.0
   *
   * @param RequestPublicationCommand $command the command value
   *
   * @return RequestPublicationResult the   invoke result
   */
  public function __invoke(RequestPublicationCommand $command): RequestPublicationResult
  {
    $context = $this->publications->missionContext($command->missionId);
    if (null === $context) {
      throw MissionNotFoundException::withId($command->missionId);
    }
    if (!$this->authorization->hasPermission($command->userId, $context->organizationId, 'organization.missions.publish')) {
      throw new MissionAccessDeniedException('Missing organization.missions.publish permission.');
    }

    $existing = $this->publications->findByMissionRevision($command->missionId, $command->missionRevision);
    if (null !== $existing && 'failed' !== $existing->status) {
      if ('pending' === $existing->status) {
        $this->queue->dispatch($existing->id);
      }

      return new RequestPublicationResult($existing);
    }
    if ('submitted' !== $context->status) {
      throw new MissionConflictException('Only submitted missions can be published.');
    }
    if ($context->revision !== $command->missionRevision) {
      throw new MissionConflictException('Mission revision does not match.');
    }

    $blockers = array_filter(
      $this->issueFinder->find($command->missionId),
      static fn ($issue): bool => 'blocker' === $issue->severity,
    );
    if ([] !== $blockers) {
      throw new MissionBlockedException('Mission contains blocking validation issues.');
    }

    $publication = null === $existing
      ? $this->publications->createOrGetPending(
        $this->uuidFactory->generateRaw(),
        $command->missionId,
        $command->missionRevision,
      )
      : $this->publications->retryFailed($existing->id);
    if ('pending' === $publication->status) {
      $this->queue->dispatch($publication->id);
    }

    return new RequestPublicationResult($publication);
  }
}
