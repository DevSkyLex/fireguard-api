<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Provider\MetadataField;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Query\MetadataField\ListMetadataFields\{ListMetadataFieldsQuery, ListMetadataFieldsResult, MetadataFieldItem};
use Facility\Presentation\Api\Dto\Output\MetadataField\FacilityMetadataFieldOutput;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/**
 * Provider ListFacilityMetadataFieldsProvider.
 *
 * Doubles as the frontend's form-schema source (`value`/`label` plus type
 * metadata) for one organization's facility metadata fields. This is a
 * business resource (the organization can create/update/delete its own
 * definitions), not a static reference catalog, so the read permission —
 * not merely `ROLE_USER` — gates it, and the organization scope check
 * (403 vs 404) runs here rather than only at the resource level.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<FacilityMetadataFieldOutput>
 */
final readonly class ListFacilityMetadataFieldsProvider implements ProviderInterface
{
  // #region Constructor
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @return list<FacilityMetadataFieldOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.facilities.read');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.facilities.read permission.');
    }

    try {
      /** @var ListMetadataFieldsResult $result */
      $result = $this->queryBus->ask(new ListMetadataFieldsQuery(organizationId: $organizationId));
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $outputs = [];
    foreach ($result->items as $item) {
      $outputs[] = $this->mapItem($item);
    }

    return $outputs;
  }

  /**
   * Method findInvalidArgumentException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   *
   * @return ?InvalidArgumentException the resolved exception
   */
  private function findInvalidArgumentException(Throwable $exception): ?InvalidArgumentException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof InvalidArgumentException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof InvalidArgumentException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method mapItem.
   *
   * @since 1.0.0
   */
  private function mapItem(MetadataFieldItem $item): FacilityMetadataFieldOutput
  {
    $output = new FacilityMetadataFieldOutput();
    $output->id = $item->id;
    $output->key = $item->key;
    $output->label = $item->label;
    $output->fieldType = $item->fieldType;
    $output->options = $item->options;
    $output->facilityType = $item->facilityType;
    $output->required = $item->required;
    $output->unit = $item->unit;

    return $output;
  }
  // #endregion
}
