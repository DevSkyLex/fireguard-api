<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Provider\Presence;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\Contract\Presence\MemberPresenceView;
use Messaging\Application\UseCase\Query\Presence\GetPresence\{GetPresenceQuery, GetPresenceResult};
use Messaging\Presentation\Api\Dto\Output\PresenceOutput;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function is_string;
use function sprintf;
use function trim;

/**
 * Provider GetPresenceProvider.
 *
 * Handles `GET /api/presence` (L2.7): a bounded multi-get over the
 * `memberIds` the client is currently displaying — the `memberIds` filter
 * is REQUIRED and non-empty, structurally ruling out a "list all online
 * members" call (see `PresenceResource`/`MODULE.md`). Capped at
 * {@see self::MAX_MEMBER_IDS} ids per request.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<PresenceOutput>
 */
final readonly class GetPresenceProvider implements ProviderInterface
{
  use MessagingExceptionMapperTrait;

  // #region Constants
  private const int MAX_MEMBER_IDS = 100;
  // #endregion

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(
    private QueryBusPort $queryBus,
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
   * @return list<PresenceOutput> the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->user();

    $query = $this->requestStack->getCurrentRequest()?->query;
    $organization = $query?->get('organization');
    if (!is_string($organization) || '' === $organization) {
      throw new BadRequestHttpException('The organization filter is required.');
    }

    $memberIds = $this->parseMemberIds($query?->get('memberIds'));
    if ([] === $memberIds) {
      throw new BadRequestHttpException('The memberIds filter is required.');
    }

    try {
      /** @var GetPresenceResult $result */
      $result = $this->queryBus->ask(new GetPresenceQuery(
        userId: $user->getId(),
        organizationId: ResourceIriParser::id($organization, 'organizations'),
        memberIds: $memberIds,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return array_map(
      static function (MemberPresenceView $view): PresenceOutput {
        $output = new PresenceOutput();
        $output->memberId = $view->memberId;
        $output->online = $view->online;
        $output->lastSeenAt = $view->lastSeenAt;

        return $output;
      },
      $result->presences,
    );
  }

  /**
   * Method parseMemberIds.
   *
   * Parses the comma-separated `memberIds` filter into a deduplicated,
   * bounded list of bare member ids.
   *
   * @since 1.0.0
   *
   * @param mixed $raw the raw query parameter value
   *
   * @return list<string> the parsed member ids
   */
  private function parseMemberIds(mixed $raw): array
  {
    if (!is_string($raw) || '' === $raw) {
      return [];
    }

    $ids = array_values(array_unique(array_filter(
      array_map(trim(...), explode(',', $raw)),
      static fn (string $id): bool => '' !== $id,
    )));

    if (count($ids) > self::MAX_MEMBER_IDS) {
      throw new BadRequestHttpException(sprintf('At most %d member ids may be requested at once.', self::MAX_MEMBER_IDS));
    }

    return $ids;
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
