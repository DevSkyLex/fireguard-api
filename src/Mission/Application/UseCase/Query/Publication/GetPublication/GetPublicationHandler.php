<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Query\Publication\GetPublication;

use Mission\Application\Port\Outbound\PublicationRepositoryPort;
use Mission\Domain\Exception\{MissionAccessDeniedException, PublicationNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetPublicationHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPublicationHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetPublicationHandler class.
   *
   * @since 1.0.0
   *
   * @param PublicationRepositoryPort $publications the publications value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   */
  public function __construct(
    private PublicationRepositoryPort $publications,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }

  /**
   * Method __invoke.
   *
   * Executes the   invoke operation.
   *
   * @since 1.0.0
   *
   * @param GetPublicationQuery $query the query value
   *
   * @return GetPublicationResult the   invoke result
   */
  public function __invoke(GetPublicationQuery $query): GetPublicationResult
  {
    $publication = $this->publications->find($query->publicationId);
    if (null === $publication) {
      throw PublicationNotFoundException::withId($query->publicationId);
    }
    $context = $this->publications->missionContext($publication->missionId);
    if (null === $context) {
      throw PublicationNotFoundException::withId($query->publicationId);
    }
    if (!$this->authorization->hasPermission($query->userId, $context->organizationId, 'organization.missions.read')) {
      throw new MissionAccessDeniedException('Missing organization.missions.read permission.');
    }

    return new GetPublicationResult($publication);
  }
}
