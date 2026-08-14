<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Provider\Media;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentAttachmentRecord;
use Equipment\Presentation\Api\Dto\Output\Equipment\AttachmentOutput;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider MediaProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<AttachmentOutput>
 */
final readonly class MediaProvider implements ProviderInterface
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MediaProvider class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }

  /**
   * Method provide.
   *
   * Executes the provide operation.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return AttachmentOutput the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): AttachmentOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Media not found.');
    }
    $record = $this->entityManager->find(EquipmentAttachmentRecord::class, $id);
    if (!$record instanceof EquipmentAttachmentRecord || null === $record->equipment?->organization) {
      throw new NotFoundHttpException('Media not found.');
    }
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $record->equipment->organization->id, 'organization.equipment.read');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Media not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.equipment.read permission.');
    }

    return self::output($record);
  }

  /**
   * Method output.
   *
   * Executes the output operation.
   *
   * @since 1.0.0
   *
   * @param EquipmentAttachmentRecord $record the record value
   *
   * @return AttachmentOutput the output result
   */
  public static function output(EquipmentAttachmentRecord $record): AttachmentOutput
  {
    if (null === $record->equipment) {
      throw new NotFoundHttpException('Media equipment not found.');
    }
    $output = new AttachmentOutput();
    $output->id = $record->id;
    $output->equipmentId = $record->equipment->id;
    $output->fileName = $record->fileName;
    $output->mimeType = $record->mimeType;
    $output->size = $record->size;
    $output->label = $record->label;
    $output->revision = $record->revision;
    $output->uploadedAt = $record->uploadedAt->format('c');

    return $output;
  }
}
