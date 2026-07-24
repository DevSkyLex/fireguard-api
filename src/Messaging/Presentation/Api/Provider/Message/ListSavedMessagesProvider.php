<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Provider\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Query\Message\ListSavedMessages\{ListSavedMessagesQuery, ListSavedMessagesResult};
use Messaging\Presentation\Api\Factory\MessageOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;
use function max;
use function min;

/**
 * Provider ListSavedMessagesProvider.
 *
 * Backs the "Saved items" list (`GET /saved-messages?organization=...`) —
 * the acting member's saved messages across the WHOLE organization, most
 * recently saved first, mirroring `ListConversationsProvider`'s required
 * `organization` query filter.
 *
 * @category Provider
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<\Messaging\Presentation\Api\Dto\Output\MessageOutput>
 */
final readonly class ListSavedMessagesProvider implements ProviderInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.2.0
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
   * @since 1.2.0
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

    $query = $this->requestStack->getCurrentRequest()?->query;
    $organization = $query?->get('organization');
    if (!is_string($organization) || '' === $organization) {
      throw new BadRequestHttpException('The organization filter is required.');
    }

    $page = max(1, $query?->getInt('page', 1) ?? 1);
    $itemsPerPage = max(1, min(100, $query?->getInt('itemsPerPage', 30) ?? 30));

    try {
      /** @var ListSavedMessagesResult $result */
      $result = $this->queryBus->ask(new ListSavedMessagesQuery(
        userId: $user->getId(),
        organizationId: ResourceIriParser::id($organization, 'organizations'),
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
   * @since 1.2.0
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
