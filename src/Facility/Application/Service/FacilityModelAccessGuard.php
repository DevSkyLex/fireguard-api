<?php

declare(strict_types=1);

namespace Facility\Application\Service;

use Facility\Application\Contract\Model\FacilityModelView;
use Facility\Application\Port\Outbound\{FacilityModelRepositoryPort, FacilityRepositoryPort};
use Facility\Domain\Exception\FacilityModelException;
use Facility\Domain\Model\FacilityModel\FacilityModel;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityType};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

/**
 * Service FacilityModelAccessGuard.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityModelAccessGuard
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
   * @param OrganizationAuthorizationPort $authorization the authorization
   * @param FacilityRepositoryPort $facilities the facilities
   * @param FacilityModelRepositoryPort $models the models
   *
   * @return void no return value
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private FacilityRepositoryPort $facilities,
    private FacilityModelRepositoryPort $models,
    private FacilitySpatialValidityResolver $spatial,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method building.
   *
   * Checks the requested building and the caller facilities grant before model access.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId the user id
   * @param string $organizationId the organization id
   * @param string $buildingId the building id
   * @param bool $write the write
   *
   * @return void no return value
   */
  public function building(string $userId, string $organizationId, string $buildingId, bool $write): void
  {
    $this->permission($userId, $organizationId, $write);

    try {
      $facility = $this->facilities->findPublishedById(FacilityId::fromString($buildingId));
    } catch (InvalidValueException) {
      throw FacilityModelException::notFound();
    }
    if (null === $facility || (string) $facility->organizationId() !== $organizationId) {
      throw FacilityModelException::notFound();
    }
    if (FacilityType::BUILDING !== $facility->type()) {
      throw FacilityModelException::invalid('Only buildings can own a GLB model.');
    }
  }

  /**
   * Method model.
   *
   * Resolves a model and fails closed on missing grants or organization scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId the user id
   * @param string $modelId the model id
   * @param bool $write the write
   *
   * @return FacilityModel the operation result
   */
  public function model(string $userId, string $modelId, bool $write): FacilityModel
  {
    try {
      new Uuid($modelId);
    } catch (InvalidValueException) {
      throw FacilityModelException::notFound();
    }
    $model = $this->models->find($modelId);
    if (null === $model) {
      throw FacilityModelException::notFound();
    }
    $this->permission($userId, $model->organizationId, $write);

    return $model;
  }

  /**
   * Method revision.
   *
   * Checks the optimistic concurrency precondition before mutating settings.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModel $model the model
   * @param int $expectedRevision the expected revision
   *
   * @return void no return value
   */
  public function revision(FacilityModel $model, int $expectedRevision): void
  {
    if ($model->revision !== $expectedRevision) {
      throw FacilityModelException::stale();
    }
  }

  /**
   * Method bindings.
   *
   * Rejects new or changed associations outside the published building subtree.
   * Unchanged historical references remain available for later correction.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModel $model the model
   * @param list<array{nodeIndex: int, facilityId: string}> $bindings
   * @param list<array{nodeIndex: int, facilityId: string}> $previousBindings the stored associations allowed to remain unchanged
   * @param ?array<string, true> $allowed the batch-resolved subtree
   *
   * @return void no return value
   */
  public function bindings(FacilityModel $model, array $bindings, array $previousBindings = [], ?array $allowed = null): void
  {
    if ([] === $bindings) {
      return;
    }
    $allowed ??= $this->bindingTargets($model);
    $previous = [];
    foreach ($previousBindings as $binding) {
      $previous[$binding['nodeIndex']] = $binding['facilityId'];
    }
    foreach ($bindings as $binding) {
      if (!isset($allowed[$binding['facilityId']]) && ($previous[$binding['nodeIndex']] ?? null) !== $binding['facilityId']) {
        throw FacilityModelException::invalid('Model associations must belong to the same building subtree.');
      }
    }
  }

  /**
   * Method bindingTargets.
   *
   * Resolves a published organization-scoped subtree once, including archived targets.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModel $model the model owning the subtree
   *
   * @return array<string, true> the usable target identifiers
   */
  public function bindingTargets(FacilityModel $model): array
  {
    $buildingId = FacilityId::fromString($model->buildingId);
    $building = $this->facilities->findPublishedById($buildingId);
    if (null === $building || (string) $building->organizationId() !== $model->organizationId || FacilityType::BUILDING !== $building->type()) {
      return [];
    }
    $allowed = [$model->buildingId => true];
    $descendants = $this->facilities->findDescendants(FacilityOrganizationId::fromString($model->organizationId), $buildingId, true);
    $ids = [$model->buildingId];
    foreach ($descendants as $facility) {
      $ids[] = (string) $facility->id();
    }
    $context = $this->spatial->context($model->organizationId, $ids);
    foreach ($descendants as $facility) {
      if ((string) $facility->organizationId() === $model->organizationId
        && $context->nearest((string) $facility->id(), 'building') === $model->buildingId) {
        $allowed[(string) $facility->id()] = true;
      }
    }

    return $allowed;
  }

  /**
   * Method view.
   *
   * Omits unusable target identifiers while preserving diagnostics for their GLB nodes.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModel $model the persisted model
   * @param ?array<string, true> $allowed the batch-resolved subtree
   *
   * @return FacilityModelView the safe model projection
   */
  public function view(FacilityModel $model, ?array $allowed = null): FacilityModelView
  {
    $allowed ??= [] === $model->bindings ? [] : $this->bindingTargets($model);
    $bindings = [];
    $issues = [];
    foreach ($model->bindings as $binding) {
      if (isset($allowed[$binding['facilityId']])) {
        $bindings[] = $binding;
      } else {
        $issues[] = ['nodeIndex' => $binding['nodeIndex'], 'code' => 'target_unavailable'];
      }
    }

    return FacilityModelView::fromModel($model, $bindings, $issues);
  }

  /**
   * Method views.
   *
   * Shares one published subtree read across all models of the requested building.
   *
   * @access public
   * @since 1.0.0
   *
   * @param list<FacilityModel> $models the same-building models
   *
   * @return list<FacilityModelView> the safe model projections
   */
  public function views(array $models): array
  {
    $scopes = [];
    $views = [];
    foreach ($models as $model) {
      $key = $model->organizationId . ':' . $model->buildingId;
      if ([] !== $model->bindings && !isset($scopes[$key])) {
        $scopes[$key] = $this->bindingTargets($model);
      }
      $views[] = $this->view($model, $scopes[$key] ?? []);
    }

    return $views;
  }

  /**
   * Method permission.
   *
   * Resolves organization scope before enforcing the requested facilities permission.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $userId the user id
   * @param string $organizationId the organization id
   * @param bool $write the write
   *
   * @return void no return value
   */
  private function permission(string $userId, string $organizationId, bool $write): void
  {
    $decision = $this->authorization->resolveAccess($userId, $organizationId, $write ? 'organization.facilities.write' : 'organization.facilities.read');
    if ($decision->isOutsideScope()) {
      throw FacilityModelException::notFound();
    }
    if (!$decision->isGranted()) {
      throw FacilityModelException::denied();
    }
  }
  // #endregion
}
