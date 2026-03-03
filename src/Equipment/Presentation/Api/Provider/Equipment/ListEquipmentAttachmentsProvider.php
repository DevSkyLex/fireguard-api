<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Query\Equipment\ListEquipmentAttachments\{ListEquipmentAttachmentsQuery, ListEquipmentAttachmentsResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Presentation\Api\Dto\Output\Equipment\AttachmentOutput;
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider ListEquipmentAttachmentsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<AttachmentOutput>
 */
final readonly class ListEquipmentAttachmentsProvider implements ProviderInterface
{
  use EquipmentExceptionUnwrapperTrait;

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
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return list<AttachmentOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
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

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.equipment.read')) {
      throw new AccessDeniedHttpException('Missing organization.equipment.read permission.');
    }

    try {
      /** @var ListEquipmentAttachmentsResult $result */
      $result = $this->queryBus->ask(new ListEquipmentAttachmentsQuery(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
      ));
    } catch (EquipmentNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findEquipmentNotFoundException($exception);
      if ($notFound instanceof EquipmentNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $outputs = [];
    foreach ($result->attachments as $attachment) {
      $output = new AttachmentOutput();
      $output->id = $attachment['id'];
      $output->equipmentId = $equipmentId;
      $output->fileName = $attachment['fileName'];
      $output->mimeType = $attachment['mimeType'];
      $output->size = $attachment['size'];
      $output->label = $attachment['label'];
      $output->uploadedAt = $attachment['uploadedAt'];
      $outputs[] = $output;
    }

    return $outputs;
  }

  // #endregion
}
