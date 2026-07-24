<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Provider\Link;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Query\Link\ListConversationLinks\{ListConversationLinksQuery, ListConversationLinksResult};
use Messaging\Presentation\Api\Factory\MessagingLinkOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_map;
use function is_string;
use function max;
use function min;

/**
 * Provider ListConversationLinksProvider.
 *
 * Backs the conversation Links tab (`GET
 * /conversations/{conversationId}/links`).
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<\Messaging\Presentation\Api\Dto\Output\MessagingLinkOutput>
 */
final readonly class ListConversationLinksProvider implements ProviderInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param MessagingLinkOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private MessagingLinkOutputFactory $mapper,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }

  /**
   * Method provide.
   *
   * @since 1.0.0
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
      /** @var ListConversationLinksResult $result */
      $result = $this->queryBus->ask(new ListConversationLinksQuery(
        userId: $user->getId(),
        conversationId: $conversationId,
        page: $page,
        itemsPerPage: $itemsPerPage,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return new TraversablePaginator(
      new ArrayIterator(array_map($this->mapper->fromView(...), $result->page->items)),
      (float) $result->page->page,
      (float) $result->page->itemsPerPage,
      (float) $result->page->total,
    );
  }

  /**
   * Method user.
   *
   * @since 1.0.0
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
