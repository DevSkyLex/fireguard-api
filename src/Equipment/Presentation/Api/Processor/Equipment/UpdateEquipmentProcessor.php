<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Command\Equipment\UpdateEquipment\{UpdateEquipmentCommand, UpdateEquipmentResult};
use Equipment\Domain\Exception\{EquipmentNotFoundException, EquipmentSerialNumberAlreadyExistsException};
use Equipment\Presentation\Api\Dto\Input\Equipment\UpdateEquipmentInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Factory\EquipmentDetailOutputFactory;
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function array_key_exists;
use function is_array;
use function is_string;
use function json_decode;

/**
 * Processor UpdateEquipmentProcessor.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UpdateEquipmentInput, EquipmentOutput>
 */
final readonly class UpdateEquipmentProcessor implements ProcessorInterface
{
  use EquipmentExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Method __construct
   *
   * Receives command dispatch, organization authorization, caller identity, and equipment response assembly for equipment updates.
   *
   * @access public
   *
   * @param CommandBusPort $commandBus port used to dispatch the update command
   * @param OrganizationAuthorizationPort $authorization port used to authorize the organization-scoped operation
   * @param Security $security security context used to obtain the acting member
   * @param EquipmentDetailOutputFactory $outputFactory factory used to assemble the updated equipment response
   *
   * @return void
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private EquipmentDetailOutputFactory $outputFactory,
    private ?\Symfony\Component\HttpFoundation\RequestStack $requests = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Checks the authenticated organization context and dispatches the equipment field update with its revision precondition.
   *
   * @access public
   * @since 1.0.0
   *
   * @param UpdateEquipmentInput $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return EquipmentOutput the updated equipment
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EquipmentOutput
  {
    /** @var UpdateEquipmentInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $equipmentId = $uriVariables['equipmentId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($equipmentId) || '' === $equipmentId) {
      throw new BadRequestHttpException('OrganizationId and equipmentId URI parameters are required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.equipment.write');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.equipment.write permission.');
    }

    try {
      /** @var UpdateEquipmentResult $result */
      $result = $this->commandBus->dispatch($this->command($data, $organizationId, $equipmentId));
    } catch (EquipmentNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (EquipmentSerialNumberAlreadyExistsException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findEquipmentNotFoundException($exception);
      if ($notFound instanceof EquipmentNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $serial = $this->findEquipmentSerialNumberAlreadyExistsException($exception);
      if ($serial instanceof EquipmentSerialNumberAlreadyExistsException) {
        throw new ConflictHttpException($serial->getMessage(), $exception);
      }

      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    return $this->outputFactory->read($organizationId, $result->equipmentId);
  }

  /**
   * Method command
   *
   * Preserves omitted identity fields while allowing explicitly submitted null values to clear them.
   *
   * @access private
   *
   * @param UpdateEquipmentInput $data deserialized equipment values
   * @param string $organizationId owning organization
   * @param string $equipmentId equipment being changed
   *
   * @return UpdateEquipmentCommand update values and their explicit presence flags
   */
  private function command(UpdateEquipmentInput $data, string $organizationId, string $equipmentId): UpdateEquipmentCommand
  {
    $body = $this->requests?->getCurrentRequest()?->getContent();
    $decoded = null !== $body && '' !== $body ? json_decode($body, true) : [];
    $fields = is_array($decoded) ? $decoded : [];

    return new UpdateEquipmentCommand(
      organizationId: $organizationId,
      equipmentId: $equipmentId,
      type: $data->type,
      subType: $data->subType,
      brand: $data->brand,
      model: $data->model,
      serialNumber: $data->serialNumber,
      locationLabel: $data->locationLabel,
      name: $data->name,
      assetCode: $data->assetCode,
      criticality: $data->criticality,
      technicalProperties: $data->technicalProperties,
      hasName: array_key_exists('name', $fields) || null !== $data->name,
      hasAssetCode: array_key_exists('assetCode', $fields) || null !== $data->assetCode,
      hasCriticality: array_key_exists('criticality', $fields) || null !== $data->criticality,
      hasTechnicalProperties: array_key_exists('technicalProperties', $fields) || [] !== $data->technicalProperties,
    );
  }
  // #endregion
}
