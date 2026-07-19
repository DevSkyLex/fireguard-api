<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\UseCase\Query\Attachment\ListFacilityAttachments\{ListFacilityAttachmentsQuery, ListFacilityAttachmentsResult};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider FacilityMediaProvider.
 *
 * Serves `GET /facilities/{facilityId}/attachments` (collection) and
 * `GET /facility-attachments/{id}` (single item).
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<FacilityAttachmentOutput>
 */
final readonly class FacilityMediaProvider implements ProviderInterface
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  public function __construct(
    private EntityManagerInterface $entityManager,
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
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   *
   * @return FacilityAttachmentOutput|list<FacilityAttachmentOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): FacilityAttachmentOutput|array
  {
    if (isset($uriVariables['facilityId'])) {
      return $this->list($uriVariables);
    }

    return $this->getOne($uriVariables);
  }

  /**
   * Method output.
   *
   * @static
   *
   * @since 1.0.0
   */
  public static function output(FacilityAttachmentRecord $record): FacilityAttachmentOutput
  {
    if (null === $record->facility) {
      throw new NotFoundHttpException('Attachment facility not found.');
    }

    $output = new FacilityAttachmentOutput();
    $output->id = $record->id;
    $output->facilityId = $record->facility->id;
    $output->fileName = $record->fileName;
    $output->mimeType = $record->mimeType;
    $output->size = $record->size;
    $output->label = $record->label;
    $output->revision = $record->revision;
    $output->uploadedAt = $record->uploadedAt->format('c');

    return $output;
  }

  /**
   * Method list.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   *
   * @return list<FacilityAttachmentOutput>
   */
  private function list(array $uriVariables): array
  {
    $facilityId = $uriVariables['facilityId'] ?? null;
    if (!is_string($facilityId) || '' === $facilityId) {
      throw new BadRequestHttpException('The facilityId URI parameter is required.');
    }

    $user = $this->user();
    $facility = $this->entityManager->find(FacilityRecord::class, $facilityId);
    if (!$facility instanceof FacilityRecord || null === $facility->organization) {
      throw new NotFoundHttpException('Facility not found.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $facility->organization->id, 'organization.facilities.read')) {
      throw new AccessDeniedHttpException('Missing organization.facilities.read permission.');
    }

    try {
      /** @var ListFacilityAttachmentsResult $result */
      $result = $this->queryBus->ask(new ListFacilityAttachmentsQuery(
        organizationId: $facility->organization->id,
        facilityId: $facilityId,
      ));
    } catch (FacilityNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findException($exception, FacilityNotFoundException::class);
      if ($notFound instanceof FacilityNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalidArgument = $this->findException($exception, InvalidArgumentException::class);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $outputs = [];
    foreach ($result->attachments as $attachment) {
      $output = new FacilityAttachmentOutput();
      $output->id = $attachment['id'];
      $output->facilityId = $facilityId;
      $output->fileName = $attachment['fileName'];
      $output->mimeType = $attachment['mimeType'];
      $output->size = $attachment['size'];
      $output->label = $attachment['label'];
      $output->uploadedAt = $attachment['uploadedAt'];
      $outputs[] = $output;
    }

    return $outputs;
  }

  /**
   * Method getOne.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   */
  private function getOne(array $uriVariables): FacilityAttachmentOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    $record = $this->entityManager->find(FacilityAttachmentRecord::class, $id);
    if (!$record instanceof FacilityAttachmentRecord || null === $record->facility?->organization) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    $user = $this->user();
    if (!$this->authorization->hasPermission($user->getId(), $record->facility->organization->id, 'organization.facilities.read')) {
      throw new AccessDeniedHttpException('Missing organization.facilities.read permission.');
    }

    return self::output($record);
  }

  /**
   * Method user.
   *
   * @since 1.0.0
   */
  private function user(): SecurityUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
  // #endregion
}
