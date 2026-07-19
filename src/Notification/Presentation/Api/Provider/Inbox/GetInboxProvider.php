<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Provider\Inbox;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Exception;
use Notification\Application\Contract\Inbox\InboxItem;
use Notification\Application\UseCase\Query\Inbox\ListInboxItems\{ListInboxItemsQuery, ListInboxItemsResult};
use Notification\Presentation\Api\Dto\Output\Inbox\{InboxItemOutput, InboxOutput};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function array_map;
use function is_numeric;
use function is_string;
use function trim;

/**
 * Provider GetInboxProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<InboxOutput>
 */
final readonly class GetInboxProvider implements ProviderInterface
{
  private const int DEFAULT_LIMIT = 20;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   * @param RequestStack $requestStack the request stack (for `organization`/`before`/`limit` query params)
   */
  public function __construct(
    private QueryBusPort $queryBus,
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
   * @param Operation $operation the operation metadata
   * @param array<string, mixed> $uriVariables URI variables
   * @param array<string, mixed> $context provider context
   *
   * @return InboxOutput the aggregated inbox feed
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): InboxOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $request = $this->requestStack->getCurrentRequest();
    $organizationId = $this->toNullableString($request?->query->get('organization'));
    $before = $this->toCursor($request?->query->get('before'));
    $limit = $this->toLimit($request?->query->get('limit'));

    /** @var ListInboxItemsResult $result */
    $result = $this->queryBus->ask(new ListInboxItemsQuery(
      userId: $user->getId(),
      organizationId: $organizationId,
      before: $before,
      limit: $limit,
    ));

    $output = new InboxOutput();
    $output->items = array_map($this->mapItem(...), $result->items);
    $output->nextCursor = $result->nextCursor;
    $output->hasMore = $result->hasMore;

    return $output;
  }

  /**
   * Method mapItem.
   *
   * @since 1.0.0
   *
   * @param InboxItem $item the contract item
   *
   * @return InboxItemOutput the API output
   */
  private function mapItem(InboxItem $item): InboxItemOutput
  {
    $output = new InboxItemOutput();
    $output->sourceKey = $item->sourceKey;
    $output->id = $item->id;
    $output->kind = $item->kind;
    $output->title = $item->title;
    $output->snippet = $item->snippet;
    $output->occurredAt = $item->occurredAt->format('c');
    $output->isRead = $item->isRead;
    $output->organizationId = $item->organizationId;
    $output->targetType = $item->targetType;
    $output->targetId = $item->targetId;

    return $output;
  }

  /**
   * Method toNullableString.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw filter value
   *
   * @return string|null trimmed non-empty string, or null
   */
  private function toNullableString(mixed $value): ?string
  {
    if (!is_string($value)) {
      return null;
    }

    $trimmed = trim($value);

    return '' !== $trimmed ? $trimmed : null;
  }

  /**
   * Method toCursor.
   *
   * Parses the `before` query parameter into a cursor instant. Not-a-date
   * values are a caller error (400), not silently ignored, so a malformed
   * cursor never gets misread as "first page".
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw `before` filter value
   *
   * @return DateTimeImmutable|null the parsed cursor, or null when absent
   */
  private function toCursor(mixed $value): ?DateTimeImmutable
  {
    $raw = $this->toNullableString($value);
    if (null === $raw) {
      return null;
    }

    try {
      return new DateTimeImmutable($raw);
    } catch (Exception) {
      throw new BadRequestHttpException('Invalid "before" cursor: expected an ISO-8601 datetime.');
    }
  }

  /**
   * Method toLimit.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw `limit` filter value
   *
   * @return int the normalized page size (handler clamps further)
   */
  private function toLimit(mixed $value): int
  {
    if (!is_numeric($value)) {
      return self::DEFAULT_LIMIT;
    }

    return (int) $value;
  }
  // #endregion
}
