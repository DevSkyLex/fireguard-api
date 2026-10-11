<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Intervention\Application\Contract\Attachment\InterventionAttachmentView;
use Intervention\Application\UseCase\Query\Attachment\ListInterventionAttachments\{ListInterventionAttachmentsQuery, ListInterventionAttachmentsResult};
use Intervention\Application\UseCase\Query\Attachment\ResolveInterventionMedia\{ResolveInterventionMediaQuery, ResolveInterventionMediaResult};
use Intervention\Presentation\Api\Dto\Output\Attachment\InterventionAttachmentOutput;
use Shared\Application\Contract\Http\ResourceIriParser;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/** InterventionMediaProvider translates HTTP media requests through use cases.
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<InterventionAttachmentOutput>
 */
final readonly class InterventionMediaProvider implements ProviderInterface
{
  public function __construct(private QueryBusPort $queryBus, private Security $security, private RequestStack $requestStack)
  {
  }

  /** @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   *
   * @return InterventionAttachmentOutput|list<InterventionAttachmentOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): InterventionAttachmentOutput|array
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $id = $uriVariables['interventionId'] ?? null;
    if (isset($uriVariables['interventionId'])) {
      if (!is_string($id) || '' === $id) {
        throw new BadRequestHttpException('The interventionId URI parameter is required.');
      }
      $workItem = $this->requestStack->getCurrentRequest()?->query->get('workItem');
      $workItemId = is_string($workItem) && '' !== $workItem ? ResourceIriParser::id($workItem, 'intervention-work-items') : null;
      /** @var ListInterventionAttachmentsResult $result */
      $result = $this->queryBus->ask(new ListInterventionAttachmentsQuery($user->getId(), $id, $workItemId));
      $outputs = [];
      foreach ($result->attachments as $attachment) {
        $output = new InterventionAttachmentOutput();
        $output->interventionId = $id;
        foreach (['id', 'fileName', 'mimeType', 'size', 'label', 'uploadedAt', 'workItemId', 'kind'] as $field) {
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
    /** @var ResolveInterventionMediaResult $result */
    $result = $this->queryBus->ask(new ResolveInterventionMediaQuery(userId: $user->getId(), attachmentId: $id));
    if (null === $result->attachment) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    return self::output($result->attachment);
  }

  public static function output(InterventionAttachmentView $view): InterventionAttachmentOutput
  {
    $output = new InterventionAttachmentOutput();
    $output->id = $view->id;
    $output->interventionId = $view->interventionId;
    $output->fileName = $view->fileName;
    $output->mimeType = $view->mimeType;
    $output->size = $view->size;
    $output->label = $view->label;
    $output->revision = $view->revision;
    $output->uploadedAt = $view->uploadedAt;
    $output->workItemId = $view->workItemId;
    $output->kind = $view->kind;

    return $output;
  }
}
