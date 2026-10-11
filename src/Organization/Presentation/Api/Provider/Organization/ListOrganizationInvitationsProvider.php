<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Application\Contract\User\AuthenticatedUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations\{GetOrganizationInvitationResult, ListOrganizationInvitationsQuery};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInvitationOutput;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Contract\Pagination\PaginationExtractor;
use Shared\Application\Contract\Search\{CollectionSearcher, SearchExtractor};
use Shared\Application\Contract\Sorting\{CollectionSorter, SortingExtractor};
use Shared\Application\Contract\Sorting\SortDirection;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;
use User\Application\Port\Inbound\UserDirectoryPort;

use function array_filter;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function is_string;
use function trim;

/**
 * Provider ListOrganizationInvitationsProvider.
 *
 * @category Provider
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
    private UserDirectoryPort $users,
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
    if (!$user instanceof AuthenticatedUser) {
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

    /** @var PaginatedResult<GetOrganizationInvitationResult> $result */
    $result = $this->queryBus->ask(new ListOrganizationInvitationsQuery(
      organizationId: $organizationId,
      pagination: new Pagination(offset: $pagination->offset, limit: $pagination->itemsPerPage),
    ));
    $displayNameCache = $this->displayNamesForInviters($result);
    $outputs = [];
    foreach ($result->items as $invitation) {
      $outputs[] = $this->mapToOutput($invitation, $displayNameCache);
    }

    $filters = \Organization\Presentation\Api\Http\OperationParameterReader::filters($operation, $context);
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
   * Method displayNamesForInviters.
   *
   * Resolves distinct inviter profiles once and keeps a missing-account fallback for the listing.
   *
   * @access private
   *
   * @param PaginatedResult<GetOrganizationInvitationResult> $result the authorized invitation page
   *
   * @return array<string, ?string> available display names keyed by account identifier
   */
  private function displayNamesForInviters(PaginatedResult $result): array
  {
    /** @var array<string, ?string> $displayNameCache */
    $displayNameCache = [];
    $userIds = [];
    foreach ($result->items as $item) {
      $userIds[] = $item->invitedByUserId;
    }

    try {
      foreach ($this->users->findByIds(array_values(array_unique($userIds))) as $userId => $profile) {
        $displayNameCache[$userId] = trim($profile->firstName . ' ' . $profile->lastName) ?: ($profile->username ?: null);
      }
    } catch (Throwable) {
      $displayNameCache = [];
    }

    return $displayNameCache;
  }

  /**
   * Method mapToOutput.
   *
   * Maps the invitation projection and its already batched inviter display name.
   *
   * @access private
   *
   * @param GetOrganizationInvitationResult $invitation the owned invitation result
   * @param array<string, ?string> $displayNameCache available inviter names
   *
   * @return OrganizationInvitationOutput the deterministic transport projection
   */
  private function mapToOutput(GetOrganizationInvitationResult $invitation, array &$displayNameCache): OrganizationInvitationOutput
  {
    $output = new OrganizationInvitationOutput();
    $output->id = $invitation->id;
    $output->organizationId = $invitation->organizationId;
    $output->email = $invitation->email;
    $output->status = $invitation->status;
    $output->invitedByUserId = $invitation->invitedByUserId;
    $output->invitedByDisplayName = $this->resolveDisplayName($invitation->invitedByUserId, $displayNameCache);
    $output->acceptedByUserId = $invitation->acceptedByUserId;
    $output->revokedByUserId = $invitation->revokedByUserId;
    $output->expiresAt = $invitation->expiresAt->format('c');
    $output->createdAt = $invitation->createdAt->format('c');
    $output->updatedAt = $invitation->updatedAt->format('c');
    $output->acceptedAt = null !== $invitation->acceptedAt ? $invitation->acceptedAt->format('c') : null;
    $output->revokedAt = null !== $invitation->revokedAt ? $invitation->revokedAt->format('c') : null;
    $output->roleIds = $invitation->roleIds;

    return $output;
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
    return $cache[$userId] ?? null;
  }

  // #endregion
}
