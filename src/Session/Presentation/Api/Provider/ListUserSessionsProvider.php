<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Session\Application\UseCase\Query\ListUserSessions\ListUserSessionsHandler;
use Session\Application\UseCase\Query\ListUserSessions\ListUserSessionsQuery;
use Session\Presentation\Api\Dto\SessionOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;

/**
 * Provider ListUserSessionsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<SessionOutput>
 */
final readonly class ListUserSessionsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ListUserSessionsHandler $handler the query handler
   * @param Security $security the security service
   */
  public function __construct(
    private ListUserSessionsHandler $handler,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * @return list<SessionOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (null === $user) {
      throw new AccessDeniedHttpException('Authentication required');
    }

    $userId = $user->getUserIdentifier();
    $query = new ListUserSessionsQuery(userId: $userId, activeOnly: true);
    $result = ($this->handler)($query);

    $request = isset($context['request']) && $context['request'] instanceof \Symfony\Component\HttpFoundation\Request
      ? $context['request']
      : null;
    $currentSessionId = $request?->getSession()->getId() ?? '';

    return array_map(
      callback: function ($session) use ($currentSessionId): SessionOutput {
        $output = new SessionOutput();
        $output->id = $session->sessionId;
        $output->userId = $session->userId;
        $output->ipAddress = $session->ipAddress;
        $output->userAgent = $session->userAgent;
        $output->createdAt = $session->createdAt->format('c');
        $output->lastActivityAt = $session->lastActivityAt->format('c');
        $output->isActive = !$session->isRevoked;
        $output->isCurrent = $session->sessionId === $currentSessionId;

        return $output;
      },
      array: $result->sessions,
    );
  }
  // #endregion
}
