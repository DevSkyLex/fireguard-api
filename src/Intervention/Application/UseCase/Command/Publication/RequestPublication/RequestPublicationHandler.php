<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Publication\RequestPublication;

use Intervention\Application\Port\Outbound\{PublicationQueuePort, PublicationRepositoryPort};
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionBlockedException, InterventionConflictException, InterventionNotFoundException};
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
   * @param InterventionIssueFinder $issueFinder the issue finder value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param UuidFactory $uuidFactory the uuid factory value
   */
  public function __construct(
    private PublicationRepositoryPort $publications,
    private PublicationQueuePort $queue,
    private InterventionIssueFinder $issueFinder,
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
    $context = $this->publications->interventionContext($command->interventionId);
    if (null === $context) {
      throw InterventionNotFoundException::withId($command->interventionId);
    }
    $decision = $this->authorization->resolveAccess($command->userId, $context->organizationId, 'organization.interventions.publish');
    if ($decision->isOutsideScope()) {
      throw InterventionNotFoundException::withId($command->interventionId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.publish permission.');
    }

    $existing = $this->publications->findByInterventionRevision($command->interventionId, $command->interventionRevision);
    if (null !== $existing && 'failed' !== $existing->status) {
      if ('pending' === $existing->status) {
        $this->queue->dispatch($existing->id);
      }

      return new RequestPublicationResult($existing);
    }
    if ('submitted' !== $context->status) {
      throw new InterventionConflictException('Only submitted interventions can be published.');
    }
    if ($context->revision !== $command->interventionRevision) {
      throw new InterventionConflictException('Intervention revision does not match.');
    }

    $blockers = array_filter(
      $this->issueFinder->find($command->interventionId),
      static fn ($issue): bool => 'blocker' === $issue->severity,
    );
    if ([] !== $blockers) {
      throw new InterventionBlockedException('Intervention contains blocking validation issues.');
    }

    $publication = null === $existing
      ? $this->publications->createOrGetPending(
        $this->uuidFactory->generateRaw(),
        $command->interventionId,
        $command->interventionRevision,
      )
      : $this->publications->retryFailed($existing->id);
    if ('pending' === $publication->status) {
      $this->queue->dispatch($publication->id);
    }

    return new RequestPublicationResult($publication);
  }
}
