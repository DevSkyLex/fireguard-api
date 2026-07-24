<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Provider\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Notification\Application\UseCase\Query\Notification\GetUnreadNotificationsCount\{GetUnreadNotificationsCountQuery, GetUnreadNotificationsCountResult};
use Notification\Presentation\Api\Dto\Output\Notification\UnreadNotificationsCountOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;
use function trim;

/**
 * Provider GetUnreadNotificationsCountProvider.
 *
 * @category Provider
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<UnreadNotificationsCountOutput>
 */
final readonly class GetUnreadNotificationsCountProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   * @param RequestStack $requestStack the request stack (for the optional `organization` filter)
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
   * @since 1.1.0
   *
   * @param Operation $operation the operation metadata
   * @param array<string, mixed> $uriVariables URI variables
   * @param array<string, mixed> $context provider context
   *
   * @return UnreadNotificationsCountOutput the unread count output
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): UnreadNotificationsCountOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $this->toNullableString($this->requestStack->getCurrentRequest()?->query->get('organization'));

    /** @var GetUnreadNotificationsCountResult $result */
    $result = $this->queryBus->ask(new GetUnreadNotificationsCountQuery(
      userId: $user->getId(),
      organizationId: $organizationId,
    ));

    $output = new UnreadNotificationsCountOutput();
    $output->count = $result->count;

    return $output;
  }

  /**
   * Method toNullableString.
   *
   * @since 1.1.0
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
  // #endregion
}
