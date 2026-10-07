<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Intervention\Application\Contract\Resource\InterventionEquipmentWork;
use Intervention\Application\UseCase\Query\Equipment\ListEquipmentOpenWork\{ListEquipmentOpenWorkQuery, ListEquipmentOpenWorkResult};
use Intervention\Presentation\Api\Dto\Output\EquipmentOpenWorkOutput;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_map;
use function is_string;

/**
 * Class EquipmentOpenWorkProvider
 *
 * Translates an authorized equipment-work query into its transport projection.
 *
 * @category Provider
 *
 * @implements ProviderInterface<EquipmentOpenWorkOutput>
 */
final readonly class EquipmentOpenWorkProvider implements ProviderInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  /**
   * Method __construct
   *
   * @access public
   *
   * @param QueryBusPort $queries application read entrypoint
   * @param Security $security authenticated account reader
   *
   * @return void
   */
  public function __construct(private QueryBusPort $queries, private Security $security)
  {
  }

  /**
   * Method provide
   *
   * @access public
   *
   * @param Operation $operation API operation
   * @param array<string,mixed> $uriVariables selected organization and equipment
   * @param array<string,mixed> $context serialization context
   *
   * @return list<EquipmentOpenWorkOutput> authorized open work
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $uriVariables['organizationId'] ?? null;
    $equipmentId = $uriVariables['equipmentId'] ?? null;
    if (!is_string($organizationId) || !is_string($equipmentId)) {
      throw new BadRequestHttpException('Organization and equipment are required.');
    }

    try {
      /** @var ListEquipmentOpenWorkResult $result */
      $result = $this->queries->ask(new ListEquipmentOpenWorkQuery($user->getId(), $organizationId, $equipmentId));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }

    return array_map(static function (InterventionEquipmentWork $item): EquipmentOpenWorkOutput {
      $output = new EquipmentOpenWorkOutput();
      $output->interventionId = $item->interventionId;
      $output->number = $item->number;
      $output->name = $item->name;
      $output->status = $item->status;
      $output->workItemId = $item->workItemId;
      $output->action = $item->action;
      $output->workItemStatus = $item->workItemStatus;

      return $output;
    }, $result->items);
  }
}
