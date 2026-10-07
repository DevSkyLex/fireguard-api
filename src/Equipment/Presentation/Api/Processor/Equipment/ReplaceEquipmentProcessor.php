<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Equipment\Application\Contract\Replacement\EquipmentReplacementSuccessor;
use Equipment\Application\UseCase\Command\Equipment\ReplaceEquipment\{ReplaceEquipmentCommand, ReplaceEquipmentResult};
use Equipment\Presentation\Api\Dto\Input\Equipment\ReplaceEquipmentInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\ReplaceEquipmentOutput;
use LogicException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;
use function strtolower;

/**
 * Class ReplaceEquipmentProcessor
 *
 * Translates replacement HTTP fields after an organization-scoped permission decision.
 *
 * @category Processor
 *
 * @implements ProcessorInterface<ReplaceEquipmentInput, ReplaceEquipmentOutput>
 */
final readonly class ReplaceEquipmentProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param CommandBusPort $commandBus dispatches the replacement command
   * @param OrganizationAuthorizationPort $authorization the permission owner
   * @param Security $security the caller context
   *
   * @return void
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   *
   * @access public
   *
   * @param mixed $data the deserialized replacement input
   * @param Operation $operation the endpoint metadata
   * @param array<string, mixed> $uriVariables the owner and predecessor identities
   * @param array<string, mixed> $context the API Platform context
   *
   * @return ReplaceEquipmentOutput the durable operation result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ReplaceEquipmentOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $uriVariables['organizationId'] ?? null;
    $equipmentId = $uriVariables['equipmentId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId || !is_string($equipmentId) || '' === $equipmentId || !$data instanceof ReplaceEquipmentInput) {
      throw new BadRequestHttpException('Organization, equipment and replacement input are required.');
    }
    $organizationId = strtolower($organizationId);
    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.equipment.write');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.equipment.write permission.');
    }
    $newAsset = $data->successor;
    $result = $this->commandBus->dispatch(new ReplaceEquipmentCommand(
      organizationId: $organizationId,
      equipmentId: strtolower($equipmentId),
      clientOperationId: strtolower($data->clientOperationId),
      successorEquipmentId: null === $data->successorEquipmentId ? null : strtolower($data->successorEquipmentId),
      successor: null === $newAsset ? null : new EquipmentReplacementSuccessor(
        $newAsset->type,
        $newAsset->subType,
        $newAsset->brand,
        $newAsset->model,
        $newAsset->serialNumber,
        $newAsset->locationLabel,
        $newAsset->name,
        $newAsset->assetCode,
        $newAsset->criticality,
        $newAsset->technicalProperties,
      ),
    ));
    if (!$result instanceof ReplaceEquipmentResult) {
      throw new LogicException('Replacement did not return its typed result.');
    }

    return new ReplaceEquipmentOutput($result->predecessorEquipmentId, $result->successorEquipmentId, $result->clientOperationId, $result->replayed);
  }
  // #endregion
}
