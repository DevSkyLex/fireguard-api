<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\UseCase\Query\Attachment\ListInterventionAttachments\{ListInterventionAttachmentsQuery, ListInterventionAttachmentsResult};
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionAttachmentRecord;
use Intervention\Presentation\Api\Dto\Output\Attachment\InterventionAttachmentOutput;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

use function is_string;

/**
 * Provider InterventionMediaProvider.
 *
 * Serves `GET /interventions/{interventionId}/attachments` (collection) and
 * `GET /intervention-attachments/{id}` (single item).
 *
 * The collection route makes NO authorization decision of its own: the flat
 * `organization.interventions.read` permission is enforced authoritatively
 * inside `ListInterventionAttachmentsHandler` (see
 * `tests/Architecture/Unit/InterventionAuthorizationEnforcementTest`). The
 * single-item route has no backing handler (a direct Doctrine read, mirroring
 * `Equipment\...\MediaProvider`), so it remains the sole enforcement point for
 * that path and keeps its own permission check.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<InterventionAttachmentOutput>
 */
final readonly class InterventionMediaProvider implements ProviderInterface
{
  use InterventionWorkflowExceptionMapperTrait;

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
   * @return InterventionAttachmentOutput|list<InterventionAttachmentOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): InterventionAttachmentOutput|array
  {
    if (isset($uriVariables['interventionId'])) {
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
  public static function output(InterventionAttachmentRecord $record): InterventionAttachmentOutput
  {
    if (null === $record->intervention) {
      throw new NotFoundHttpException('Attachment intervention not found.');
    }

    $output = new InterventionAttachmentOutput();
    $output->id = $record->id;
    $output->interventionId = $record->intervention->id;
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
   * @return list<InterventionAttachmentOutput>
   */
  private function list(array $uriVariables): array
  {
    $interventionId = $uriVariables['interventionId'] ?? null;
    if (!is_string($interventionId) || '' === $interventionId) {
      throw new BadRequestHttpException('The interventionId URI parameter is required.');
    }

    $user = $this->user();

    try {
      /** @var ListInterventionAttachmentsResult $result */
      $result = $this->queryBus->ask(new ListInterventionAttachmentsQuery(
        userId: $user->getId(),
        interventionId: $interventionId,
      ));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }

    $outputs = [];
    foreach ($result->attachments as $attachment) {
      $output = new InterventionAttachmentOutput();
      $output->id = $attachment['id'];
      $output->interventionId = $interventionId;
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
  private function getOne(array $uriVariables): InterventionAttachmentOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    $record = $this->entityManager->find(InterventionAttachmentRecord::class, $id);
    if (!$record instanceof InterventionAttachmentRecord || null === $record->intervention?->organization) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    $user = $this->user();
    if (!$this->authorization->hasPermission($user->getId(), $record->intervention->organization->id, 'organization.interventions.read')) {
      throw new AccessDeniedHttpException('Missing organization.interventions.read permission.');
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
