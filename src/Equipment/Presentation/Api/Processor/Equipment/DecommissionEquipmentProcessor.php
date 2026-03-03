<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment\{DecommissionEquipmentCommand, DecommissionEquipmentResult};
use Equipment\Domain\Exception\{EquipmentAlreadyDecommissionedException, EquipmentNotFoundException};
use Equipment\Presentation\Api\Dto\Output\Equipment\{EquipmentOutput, TagOutput};
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function array_map;
use function is_string;

/**
 * Processor DecommissionEquipmentProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, EquipmentOutput>
 */
final readonly class DecommissionEquipmentProcessor implements ProcessorInterface
{
  use EquipmentExceptionUnwrapperTrait;

  // #region Constructor
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EquipmentOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $equipmentId = $uriVariables['equipmentId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($equipmentId) || '' === $equipmentId) {
      throw new BadRequestHttpException('OrganizationId and equipmentId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.equipment.write')) {
      throw new AccessDeniedHttpException('Missing organization.equipment.write permission.');
    }

    try {
      /** @var DecommissionEquipmentResult $result */
      $result = $this->commandBus->dispatch(new DecommissionEquipmentCommand(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
      ));
    } catch (EquipmentNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (EquipmentAlreadyDecommissionedException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findEquipmentNotFoundException($exception);
      if ($notFound instanceof EquipmentNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $decommissioned = $this->findEquipmentAlreadyDecommissionedException($exception);
      if ($decommissioned instanceof EquipmentAlreadyDecommissionedException) {
        throw new ConflictHttpException($decommissioned->getMessage(), $exception);
      }

      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new EquipmentOutput();
    $output->id = $result->equipmentId;
    $output->organizationId = $result->organizationId;
    $output->facilityId = $result->facilityId;
    $output->type = $result->type;
    $output->subType = $result->subType;
    $output->brand = $result->brand;
    $output->model = $result->model;
    $output->serialNumber = $result->serialNumber;
    $output->locationLabel = $result->locationLabel;
    $output->status = $result->status;
    $output->installedAt = $result->installedAt;
    $output->commissionedAt = $result->commissionedAt;
    $output->tags = array_map(TagOutput::fromArray(...), $result->tags);
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
  // #endregion
}
