<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Application\UseCase\Query\Attachment\ListInspectionAttachments\{ListInspectionAttachmentsQuery, ListInspectionAttachmentsResult};
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityNotFoundException};
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionAttachmentRecord, InspectionRecord, NonConformityRecord};
use Inspection\Presentation\Api\Dto\Output\Attachment\InspectionAttachmentOutput;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider InspectionMediaProvider.
 *
 * Serves `GET /inspections/{inspectionId}/attachments`,
 * `GET /non-conformities/{nonConformityId}/attachments` and
 * `GET /inspection-attachments/{id}`.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<InspectionAttachmentOutput>
 */
final readonly class InspectionMediaProvider implements ProviderInterface
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
   * @return InspectionAttachmentOutput|list<InspectionAttachmentOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): InspectionAttachmentOutput|array
  {
    if (isset($uriVariables['nonConformityId'])) {
      return $this->listForNonConformity($uriVariables);
    }

    if (isset($uriVariables['inspectionId'])) {
      return $this->listForInspection($uriVariables);
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
  public static function output(InspectionAttachmentRecord $record): InspectionAttachmentOutput
  {
    if (null === $record->inspection) {
      throw new NotFoundHttpException('Attachment inspection not found.');
    }

    $output = new InspectionAttachmentOutput();
    $output->id = $record->id;
    $output->inspectionId = $record->inspection->id;
    $output->nonConformityId = $record->nonConformity?->id;
    $output->fileName = $record->fileName;
    $output->mimeType = $record->mimeType;
    $output->size = $record->size;
    $output->label = $record->label;
    $output->revision = $record->revision;
    $output->uploadedAt = $record->uploadedAt->format('c');

    return $output;
  }

  /**
   * Method listForInspection.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   *
   * @return list<InspectionAttachmentOutput>
   */
  private function listForInspection(array $uriVariables): array
  {
    $inspectionId = $uriVariables['inspectionId'] ?? null;
    if (!is_string($inspectionId) || '' === $inspectionId) {
      throw new BadRequestHttpException('The inspectionId URI parameter is required.');
    }

    $inspection = $this->entityManager->find(InspectionRecord::class, $inspectionId);
    if (!$inspection instanceof InspectionRecord || null === $inspection->organization) {
      throw new NotFoundHttpException('Inspection not found.');
    }

    $user = $this->user();
    if (!$this->authorization->hasPermission($user->getId(), $inspection->organization->id, 'organization.inspection.read')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
    }

    return $this->ask($inspection->organization->id, $inspectionId, null, $inspectionId);
  }

  /**
   * Method listForNonConformity.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   *
   * @return list<InspectionAttachmentOutput>
   */
  private function listForNonConformity(array $uriVariables): array
  {
    $nonConformityId = $uriVariables['nonConformityId'] ?? null;
    if (!is_string($nonConformityId) || '' === $nonConformityId) {
      throw new BadRequestHttpException('The nonConformityId URI parameter is required.');
    }

    $nonConformity = $this->entityManager->find(NonConformityRecord::class, $nonConformityId);
    if (!$nonConformity instanceof NonConformityRecord || !$nonConformity->inspection instanceof InspectionRecord) {
      throw new NotFoundHttpException('Non-conformity not found.');
    }

    $inspection = $nonConformity->inspection;
    $organization = $inspection->organization;
    if (null === $organization) {
      throw new NotFoundHttpException('Non-conformity not found.');
    }

    $user = $this->user();
    if (!$this->authorization->hasPermission($user->getId(), $organization->id, 'organization.inspection.read')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
    }

    return $this->ask($organization->id, $inspection->id, $nonConformityId, $inspection->id);
  }

  /**
   * Method ask.
   *
   * @since 1.0.0
   *
   * @return list<InspectionAttachmentOutput>
   */
  private function ask(string $organizationId, string $inspectionId, ?string $nonConformityId, string $outputInspectionId): array
  {
    try {
      /** @var ListInspectionAttachmentsResult $result */
      $result = $this->queryBus->ask(new ListInspectionAttachmentsQuery(
        organizationId: $organizationId,
        inspectionId: $inspectionId,
        nonConformityId: $nonConformityId,
      ));
    } catch (InspectionNotFoundException|NonConformityNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findException($exception, InspectionNotFoundException::class)
        ?? $this->findException($exception, NonConformityNotFoundException::class);
      if (null !== $notFound) {
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
      $output = new InspectionAttachmentOutput();
      $output->id = $attachment['id'];
      $output->inspectionId = $outputInspectionId;
      $output->nonConformityId = $attachment['nonConformityId'];
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
  private function getOne(array $uriVariables): InspectionAttachmentOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    $record = $this->entityManager->find(InspectionAttachmentRecord::class, $id);
    if (!$record instanceof InspectionAttachmentRecord || null === $record->inspection?->organization) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    $user = $this->user();
    if (!$this->authorization->hasPermission($user->getId(), $record->inspection->organization->id, 'organization.inspection.read')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
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
