<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Publication\GetPublication;

use Intervention\Application\Port\Outbound\PublicationRepositoryPort;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, PublicationNotFoundException};
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
    $context = $this->publications->interventionContext($publication->interventionId);
    if (null === $context) {
      throw PublicationNotFoundException::withId($query->publicationId);
    }
    $decision = $this->authorization->resolveAccess($query->userId, $context->organizationId, 'organization.interventions.read');
    if ($decision->isOutsideScope()) {
      throw PublicationNotFoundException::withId($query->publicationId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    return new GetPublicationResult($publication);
  }
}
