<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Provider\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Query\Message\ListPinnedMessages\{ListPinnedMessagesQuery, ListPinnedMessagesResult};
use Messaging\Presentation\Api\Factory\MessageOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;
use function max;
use function min;

/**
 * Provider ListPinnedMessagesProvider.
 *
 * Backs the conversation Pins tab (`GET
 * /conversations/{conversationId}/pinned-messages`).
 *
 * @category Provider
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<\Messaging\Presentation\Api\Dto\Output\MessageOutput>
 */
final readonly class ListPinnedMessagesProvider implements ProviderInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param MessageOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private MessageOutputFactory $mapper,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }

  /**
   * Method provide.
   *
   * @since 1.1.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return object the provide result
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
      /** @var ListPinnedMessagesResult $result */
      $result = $this->queryBus->ask(new ListPinnedMessagesQuery(
        userId: $user->getId(),
        conversationId: $conversationId,
        page: $page,
        itemsPerPage: $itemsPerPage,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return new TraversablePaginator(
      new ArrayIterator($this->mapper->fromViews($result->page->items, $result->currentMemberId)),
      (float) $result->page->page,
      (float) $result->page->itemsPerPage,
      (float) $result->page->total,
    );
  }

  /**
   * Method user.
   *
   * @since 1.1.0
   *
   * @return SecurityUser the user result
   */
  private function user(): SecurityUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
}
