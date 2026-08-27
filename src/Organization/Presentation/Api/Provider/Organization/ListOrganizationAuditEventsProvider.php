<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Exception;
use Organization\Application\UseCase\Query\Organization\ListOrganizationAuditEvents\{ListOrganizationAuditEventsQuery, OrganizationAuditEventResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationAuditEventOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Pagination\PaginationExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function array_key_exists;
use function is_string;
use function min;
use function trim;

/**
 * Provider ListOrganizationAuditEventsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationAuditEventOutput>
 */
final readonly class ListOrganizationAuditEventsProvider implements ProviderInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * Lets the provider catch messenger-wrapped domain exceptions and
   * rethrow the matching HTTP exception — the module-wide unwrapping rule.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;
  // #endregion

  // #region Constants
  /**
   * Constant NOT_FOUND_MESSAGE.
   *
   * Shared 404 detail for both "organization does not exist" and
   * "caller is not a member" — the bodies must be indistinguishable
   * or the message becomes an organization-existence oracle.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string NOT_FOUND_MESSAGE = 'Organization not found.';

  /**
   * Constant MAX_ITEMS_PER_PAGE.
   *
   * Upper bound on the client-selectable page size, mirroring the
   * resource's `paginationMaximumItemsPerPage`.
   *
   * `PaginationExtractor` does clamp — at 500, the shared ceiling sized for
   * the heaviest option pickers. This is a second, tighter clamp
   * (`min(500-clamped, 100)`) because an audit ledger grows without bound
   * per organization and each row carries a metadata payload, so a page of
   * 500 is a far heavier response here than 500 option rows. Both bounds
   * apply; this one is the binding constraint on this endpoint.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int MAX_ITEMS_PER_PAGE = 100;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationAuditEventsProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Provides the organization's audit events, newest first. The
   * handler owns the denial ordering: unknown organization and
   * non-member both surface as 404, a member without
   * `organization.audit.read` gets 403.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @throws AccessDeniedHttpException when the caller is unauthenticated or lacks the permission
   * @throws BadRequestHttpException when a date filter is malformed
   * @throws NotFoundHttpException when the organization does not exist or the caller is not a member
   *
   * @return TraversablePaginator<OrganizationAuditEventOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return new TraversablePaginator(new ArrayIterator([]), 1, 30, 0);
    }

    $filters = $context['filters'] ?? [];
    /** @var array<string, mixed> $filters */
    $pagination = PaginationExtractor::fromContext($context);
    $itemsPerPage = min($pagination->itemsPerPage, self::MAX_ITEMS_PER_PAGE);
    $offset = ($pagination->page - 1) * $itemsPerPage;

    try {
      /** @var PaginatedResult<OrganizationAuditEventResult> $result */
      $result = $this->queryBus->ask(new ListOrganizationAuditEventsQuery(
        organizationId: $organizationId,
        userId: $user->getId(),
        action: $this->stringOrNull($filters['action'] ?? null),
        from: $this->parseDateFilter($filters, 'from'),
        to: $this->parseDateFilter($filters, 'to'),
        pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
      ));
    } catch (OrganizationAccessDeniedException $exception) {
      throw new AccessDeniedHttpException($exception->getMessage(), $exception);
    } catch (OrganizationNotFoundException|OrganizationMemberNotFoundException $exception) {
      // One shared message for both causes: the specific exception texts
      // differ, and echoing them would let an outsider tell a real
      // organization from a fake one by diffing the 404 body — the exact
      // oracle the identical status code is there to prevent.
      throw new NotFoundHttpException(self::NOT_FOUND_MESSAGE, $exception);
    } catch (MessengerRuntimeException $exception) {
      $accessDenied = $this->findWrappedException($exception, OrganizationAccessDeniedException::class);
      if ($accessDenied instanceof OrganizationAccessDeniedException) {
        throw new AccessDeniedHttpException($accessDenied->getMessage(), $exception);
      }

      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class)
        ?? $this->findWrappedException($exception, OrganizationMemberNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException(self::NOT_FOUND_MESSAGE, $exception);
      }

      throw $exception;
    }

    /** @var array<string, ?string> $displayNameCache */
    $displayNameCache = [];
    $outputs = [];
    foreach ($result->items as $event) {
      $outputs[] = $this->mapToOutput($event, $displayNameCache);
    }

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $pagination->page,
      itemsPerPage: (float) $itemsPerPage,
      totalItems: (float) $result->total,
    );
  }

  /**
   * Method mapToOutput.
   *
   * Maps a use-case result to the API output, naming the actor only when the
   * handler decided this audience may know who they are
   * (`actorIsOrganizationMember`) — the decision is the handler's, this is
   * only the lookup it authorizes.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuditEventResult $event the event to map
   * @param array<string, ?string> $displayNameCache per-request display-name cache
   *
   * @return OrganizationAuditEventOutput the mapped output
   */
  private function mapToOutput(OrganizationAuditEventResult $event, array &$displayNameCache): OrganizationAuditEventOutput
  {
    $output = new OrganizationAuditEventOutput();
    $output->id = $event->id;
    $output->action = $event->action;
    $output->actorType = $event->actorType;
    $output->actorId = $event->actorId;
    $output->actorDisplayName = $event->actorIsOrganizationMember && null !== $event->actorId
      ? $this->resolveDisplayName($event->actorId, $displayNameCache)
      : null;
    $output->subjectType = $event->subjectType;
    $output->subjectId = $event->subjectId;
    $output->metadata = $event->metadata;
    $output->occurredAt = $event->occurredAt;
    $output->recordedAt = $event->recordedAt;

    return $output;
  }

  /**
   * Method resolveDisplayName.
   *
   * Resolves an actor's display name, caching per user id and never
   * letting a missing user record fail the listing. Mirrors
   * `ListOrganizationInvitationsProvider::resolveDisplayName`.
   *
   * @since 1.0.0
   *
   * @param string $userId the actor user id
   * @param array<string, ?string> $cache per-request resolution cache
   *
   * @return string|null the resolved display name, or null when unavailable
   */
  private function resolveDisplayName(string $userId, array &$cache): ?string
  {
    if (array_key_exists($userId, $cache)) {
      return $cache[$userId];
    }

    $name = null;

    try {
      $result = $this->queryBus->ask(new GetUserQuery($userId));
      if ($result instanceof GetUserResult && null !== $result->user) {
        $name = trim($result->user->firstName . ' ' . $result->user->lastName)
          ?: ($result->user->username ?: null);
      }
    } catch (Throwable) {
      $name = null;
    }

    $cache[$userId] = $name;

    return $name;
  }

  /**
   * Method parseDateFilter.
   *
   * Parses an optional ISO 8601 date filter, mapping a malformed
   * value to a 400 instead of a 500.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $filters the raw filters
   * @param string $name the filter name ('from' or 'to')
   *
   * @throws BadRequestHttpException when the value is present but unparseable
   *
   * @return DateTimeImmutable|null the parsed date or null when absent
   */
  private function parseDateFilter(array $filters, string $name): ?DateTimeImmutable
  {
    $value = $filters[$name] ?? null;
    if (!is_string($value) || '' === $value) {
      return null;
    }

    try {
      return new DateTimeImmutable($value);
    } catch (Exception $exception) {
      throw new BadRequestHttpException(
        'Invalid "' . $name . '" filter. Use an ISO 8601 datetime such as 2026-03-01T00:00:00Z.',
        $exception,
      );
    }
  }

  /**
   * Method stringOrNull.
   *
   * Returns the string value or null when empty.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw value
   *
   * @return string|null the sanitized string or null
   */
  private function stringOrNull(mixed $value): ?string
  {
    if (!is_string($value) || '' === $value) {
      return null;
    }

    return $value;
  }
  // #endregion
}
