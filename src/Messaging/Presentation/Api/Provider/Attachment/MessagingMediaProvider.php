<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Query\Attachment\ListConversationAttachments\{ListConversationAttachmentsQuery, ListConversationAttachmentsResult};
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingAttachmentRecord, MessagingMessageRecord};
use Messaging\Presentation\Api\Dto\Output\MessageAttachmentOutput;
use Messaging\Presentation\Api\Factory\MessageAttachmentOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

use function array_map;
use function is_string;
use function max;
use function min;

/**
 * Provider MessagingMediaProvider.
 *
 * Serves `GET /conversations/{conversationId}/attachments` — the conversation
 * Files tab.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<MessageAttachmentOutput>
 */
final readonly class MessagingMediaProvider implements ProviderInterface
{
  use MessagingExceptionMapperTrait;

  // #region Constructor
  public function __construct(
    private QueryBusPort $queryBus,
    private MessageAttachmentOutputFactory $mapper,
    private Security $security,
    private RequestStack $requestStack,
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
   * @return TraversablePaginator<int, MessageAttachmentOutput> the paginated conversation attachments
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->user();
    $conversationId = $uriVariables['conversationId'] ?? null;
    if (!is_string($conversationId) || '' === $conversationId) {
      throw new BadRequestHttpException('The conversationId URI parameter is required.');
    }

    $query = $this->requestStack->getCurrentRequest()?->query;
    $page = max(1, $query?->getInt('page', 1) ?? 1);
    $itemsPerPage = max(1, min(100, $query?->getInt('itemsPerPage', 30) ?? 30));

    try {
      /** @var ListConversationAttachmentsResult $result */
      $result = $this->queryBus->ask(new ListConversationAttachmentsQuery(
        userId: $user->getId(),
        conversationId: $conversationId,
        page: $page,
        itemsPerPage: $itemsPerPage,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return new TraversablePaginator(
      new ArrayIterator(array_map($this->mapper->fromAttachment(...), $result->page->items)),
      (float) $result->page->page,
      (float) $result->page->itemsPerPage,
      (float) $result->page->total,
    );
  }

  /**
   * Method output.
   *
   * @static
   *
   * Builds the response DTO for a single, freshly persisted (or fetched)
   * attachment record — the real, revisioned `revision` value, unlike the
   * list mapping which reads through the domain aggregate (which does not
   * carry `revision`, matching `InspectionMediaProvider::output()`'s
   * precedent).
   *
   * @since 1.0.0
   */
  public static function output(MessagingAttachmentRecord $record): MessageAttachmentOutput
  {
    if (!$record->message instanceof MessagingMessageRecord) {
      throw new NotFoundHttpException('Attachment message not found.');
    }

    $output = new MessageAttachmentOutput();
    $output->id = $record->id;
    $output->message = '/api/messages/' . $record->message->id;
    $output->conversation = '/api/conversations/' . $record->conversationId;
    $output->uploadedByMember = '/api/organizations/' . $record->organizationId . '/members/' . $record->uploadedByMemberId;
    $output->contentUrl = '/api/messaging-attachments/' . $record->id . '/content';
    $output->fileName = $record->fileName;
    $output->mimeType = $record->mimeType;
    $output->size = $record->size;
    $output->label = $record->label;
    $output->revision = $record->revision;
    $output->uploadedAt = $record->uploadedAt->format('c');

    return $output;
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
