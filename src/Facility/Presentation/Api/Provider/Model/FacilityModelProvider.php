<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Provider\Model;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Facility\Application\UseCase\Query\Model\GetFacilityModel\{GetFacilityModelQuery, GetFacilityModelResult};
use Facility\Application\UseCase\Query\Model\ListFacilityModels\{ListFacilityModelsQuery, ListFacilityModelsResult};
use Facility\Presentation\Api\Dto\Output\Model\FacilityModelOutput;
use Facility\Presentation\Api\Operation\FacilityModelOperations;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function array_map;
use function is_string;

/**
 * Provider FacilityModelProvider.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<FacilityModelOutput>
 */
final readonly class FacilityModelProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Initializes the model capability with its typed dependencies and state.
   *
   * @access public
   * @since 1.0.0
   *
   * @param QueryBusPort $queries the queries
   * @param Security $security the security
   *
   * @return void no return value
   */
  public function __construct(private QueryBusPort $queries, private Security $security)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Translates HTTP identifiers into typed queries and maps their transport output.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   *
   * @return FacilityModelOutput|list<FacilityModelOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): FacilityModelOutput|array
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    if (FacilityModelOperations::LIST === $operation->getName()) {
      /** @var ListFacilityModelsResult $result */
      $result = $this->queries->ask(new ListFacilityModelsQuery(
        $user->getId(),
        $this->identifier($uriVariables, 'organizationId'),
        $this->identifier($uriVariables, 'buildingId'),
      ));

      return array_map(FacilityModelOutput::fromView(...), $result->models);
    }
    /** @var GetFacilityModelResult $result */
    $result = $this->queries->ask(new GetFacilityModelQuery($user->getId(), $this->identifier($uriVariables, 'id')));

    return FacilityModelOutput::fromView($result->model);
  }

  /**
   * Method identifier.
   *
   * Reads a required transport identifier without performing a business lookup.
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<string, mixed> $variables
   * @param string $key the key
   *
   * @return string the operation result
   */
  private function identifier(array $variables, string $key): string
  {
    $id = $variables[$key] ?? null;
    if (!is_string($id) || '' === $id) {
      throw new BadRequestHttpException('Missing model URI identifier.');
    }

    return $id;
  }
  // #endregion
}
