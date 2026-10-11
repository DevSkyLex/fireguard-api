<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Inspection\Application\Contract\Attachment\InspectionAttachmentView;
use Inspection\Application\UseCase\Query\Attachment\ListInspectionAttachments\{ListInspectionAttachmentsQuery, ListInspectionAttachmentsResult};
use Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia\{ResolveInspectionMediaQuery, ResolveInspectionMediaResult};
use Inspection\Presentation\Api\Dto\Output\Attachment\InspectionAttachmentOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/** InspectionMediaProvider translates HTTP media requests through use cases.
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<InspectionAttachmentOutput>
 */
final readonly class InspectionMediaProvider implements ProviderInterface
{
  public function __construct(private QueryBusPort $queryBus, private Security $security)
  {
  }

  /** @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   *
   * @return InspectionAttachmentOutput|list<InspectionAttachmentOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): InspectionAttachmentOutput|array
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $inspectionId = $uriVariables['inspectionId'] ?? null;
    $nonConformityId = $uriVariables['nonConformityId'] ?? null;
    if (isset($uriVariables['nonConformityId']) && (!is_string($nonConformityId) || '' === $nonConformityId)) {
      throw new BadRequestHttpException('The nonConformityId URI parameter is required.');
    }
    if (isset($uriVariables['inspectionId']) && (!is_string($inspectionId) || '' === $inspectionId)) {
      throw new BadRequestHttpException('The inspectionId URI parameter is required.');
    }
    if (is_string($inspectionId) || is_string($nonConformityId)) {
      /** @var ResolveInspectionMediaResult $scope */
      $scope = $this->queryBus->ask(new ResolveInspectionMediaQuery(userId: $user->getId(), inspectionId: is_string($inspectionId) ? $inspectionId : null, nonConformityId: is_string($nonConformityId) ? $nonConformityId : null));
      /** @var ListInspectionAttachmentsResult $result */
      $result = $this->queryBus->ask(new ListInspectionAttachmentsQuery($scope->organizationId, $scope->inspectionId, is_string($nonConformityId) ? $nonConformityId : null));
      $outputs = [];
      foreach ($result->attachments as $attachment) {
        $output = new InspectionAttachmentOutput();
        $output->inspectionId = $scope->inspectionId;
        foreach (['id', 'nonConformityId', 'fileName', 'mimeType', 'size', 'label', 'uploadedAt'] as $field) {
          $output->{$field} = $attachment[$field];
        }
        $outputs[] = $output;
      }

      return $outputs;
    }
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Attachment not found.');
    }
    /** @var ResolveInspectionMediaResult $result */
    $result = $this->queryBus->ask(new ResolveInspectionMediaQuery(userId: $user->getId(), attachmentId: $id));
    if (null === $result->attachment) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    return self::output($result->attachment);
  }

  public static function output(InspectionAttachmentView $view): InspectionAttachmentOutput
  {
    $output = new InspectionAttachmentOutput();
    $output->id = $view->id;
    $output->inspectionId = $view->inspectionId;
    $output->fileName = $view->fileName;
    $output->mimeType = $view->mimeType;
    $output->size = $view->size;
    $output->label = $view->label;
    $output->revision = $view->revision;
    $output->uploadedAt = $view->uploadedAt;
    $output->nonConformityId = $view->nonConformityId;

    return $output;
  }
}
