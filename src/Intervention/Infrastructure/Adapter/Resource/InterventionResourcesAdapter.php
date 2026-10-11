<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Resource;

use Intervention\Application\Contract\Resource\{ClientResourceAlreadyExistsException, InterventionAccessDeniedException, InterventionAssignmentContext, InterventionConflictException, InterventionNotFoundException, InterventionResourceAssignment, InterventionResourceNotFoundException, InterventionResourceType};
use Intervention\Application\Port\Inbound\InterventionResourcesPort;
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\Exception as Internal;
use Intervention\Domain\ValueObject\InterventionResourceType as InternalResourceType;

/** Translates private owner orchestration into the published resource contract.
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionResourcesAdapter implements InterventionResourcesPort
{
  public function __construct(private InterventionResourceManager $resources)
  {
  }

  public function attach(InterventionResourceType $type, string $resourceId, string $organizationId, ?string $interventionId, ?string $clientId = null): InterventionResourceAssignment
  {
    return $this->translate(fn () => $this->resources->attach(InternalResourceType::from($type->value), $resourceId, $organizationId, $interventionId, $clientId));
  }

  public function assertOfflineCreate(InterventionResourceType $type, ?string $clientId, bool $forUpdate = true): void
  {
    $this->translate(fn () => $this->resources->assertOfflineCreate(InternalResourceType::from($type->value), $clientId, $forUpdate));
  }

  public function interventionContext(string $interventionId): ?InterventionAssignmentContext
  {
    return $this->translate(fn () => $this->resources->interventionContext($interventionId));
  }

  public function resourceInInterventionScope(InterventionResourceType $type, string $resourceId, string $interventionId): bool
  {
    return $this->translate(fn () => $this->resources->resourceInInterventionScope(InternalResourceType::from($type->value), $resourceId, $interventionId));
  }

  public function workItemBelongsToIntervention(string $workItemId, string $interventionId): bool
  {
    return $this->translate(fn () => $this->resources->workItemBelongsToIntervention($workItemId, $interventionId));
  }

  public function mutationPermission(string $interventionId, ?string $userId = null, ?string $expectedOrganizationId = null, bool $forUpdate = true): string
  {
    return $this->translate(fn () => $this->resources->mutationPermission($interventionId, $userId, $expectedOrganizationId, $forUpdate));
  }

  public function touchDraftIntervention(?string $interventionId): void
  {
    $this->translate(fn () => $this->resources->touchDraftIntervention($interventionId));
  }

  /** @template T
   * @param callable(): T $operation
   *
   * @return T
   */
  private function translate(callable $operation): mixed
  {
    try {
      return $operation();
    } catch (Internal\ClientResourceAlreadyExistsException $exception) {
      throw new ClientResourceAlreadyExistsException($exception->getMessage(), previous: $exception);
    } catch (Internal\InterventionNotFoundException $exception) {
      throw new InterventionNotFoundException($exception->getMessage(), previous: $exception);
    } catch (Internal\InterventionResourceNotFoundException $exception) {
      throw new InterventionResourceNotFoundException($exception->getMessage(), previous: $exception);
    } catch (Internal\InterventionConflictException $exception) {
      throw new InterventionConflictException($exception->getMessage(), previous: $exception);
    } catch (Internal\InterventionAccessDeniedException $exception) {
      throw new InterventionAccessDeniedException($exception->getMessage(), previous: $exception);
    }
  }
}
