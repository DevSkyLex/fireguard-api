<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations\{GetOrganizationInvitationResult, ListOrganizationInvitationsQuery};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInvitationOutput;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Contract\Sorting\SortDirection;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Pagination\PaginationExtractor;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Throwable;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function array_filter;
use function array_key_exists;
use function array_slice;
use function array_values;
use function count;
use function is_string;
use function trim;

/**
 * Provider ListOrganizationInvitationsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationInvitationOutput>
 */
final readonly class ListOrganizationInvitationsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationInvitationsProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param Security $security the security service
   */
  public function __construct(
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
   * Provides resource data for the requested API operation.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return TraversablePaginator<OrganizationInvitationOutput>
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

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.members.manage')) {
      throw new AccessDeniedHttpException('Missing Organization.members.manage permission.');
    }

    $pagination = PaginationExtractor::fromContext($context);

    try {
      /** @var PaginatedResult<GetOrganizationInvitationResult> $result */
      $result = $this->queryBus->ask(new ListOrganizationInvitationsQuery(
        organizationId: $organizationId,
        pagination: new Pagination(offset: $pagination->offset, limit: $pagination->itemsPerPage),
      ));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    /** @var array<string, ?string> $displayNameCache */
    $displayNameCache = [];
    $outputs = [];
    foreach ($result->items as $invitation) {
      $output = new OrganizationInvitationOutput();
      $output->id = $invitation->id;
      $output->organizationId = $invitation->organizationId;
      $output->email = $invitation->email;
      $output->status = $invitation->status;
      $output->invitedByUserId = $invitation->invitedByUserId;
      $output->invitedByDisplayName = $this->resolveDisplayName(
        $invitation->invitedByUserId,
        $displayNameCache,
      );
      $output->acceptedByUserId = $invitation->acceptedByUserId;
      $output->revokedByUserId = $invitation->revokedByUserId;
      $output->expiresAt = $invitation->expiresAt->format('c');
      $output->createdAt = $invitation->createdAt->format('c');
      $output->updatedAt = $invitation->updatedAt->format('c');
      $output->acceptedAt = null !== $invitation->acceptedAt ? $invitation->acceptedAt->format('c') : null;
      $output->revokedAt = null !== $invitation->revokedAt ? $invitation->revokedAt->format('c') : null;
      $output->roleIds = $invitation->roleIds;
      $outputs[] = $output;
    }

    $filters = $context['filters'] ?? [];
    /** @var array<string, mixed> $filters */
    $statusFilter = $filters['status'] ?? null;
    if (is_string($statusFilter) && '' !== $statusFilter) {
      $outputs = array_values(
        array_filter($outputs, static fn (OrganizationInvitationOutput $o): bool => $o->status === $statusFilter),
      );
    }

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['email', 'status', 'invitedByDisplayName']);

    $total = count($outputs);

    $sorting = SortingExtractor::fromContext($context, ['email', 'status', 'createdAt', 'expiresAt'], 'createdAt', SortDirection::DESC);
    $outputs = CollectionSorter::sort($outputs, $sorting);

    $outputs = array_slice($outputs, $pagination->offset, $pagination->itemsPerPage);

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $pagination->page,
      itemsPerPage: (float) $pagination->itemsPerPage,
      totalItems: (float) $total,
    );
  }

  /**
   * Method resolveDisplayName.
   *
   * Resolves an inviter's display name, caching per user id and never letting a
   * missing user record fail the listing.
   *
   * @since 1.2.0
   *
   * @param string $userId the inviter user id
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
      /** @var GetUserResult $result */
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
  // #endregion
}
