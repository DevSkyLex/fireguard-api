<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Provider\Session;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Session\Application\UseCase\Query\Session\GetSession\{GetSessionQuery, GetSessionResult};
use Session\Presentation\Api\Dto\Output\Session\SessionOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider GetSessionProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<SessionOutput>
 */
final readonly class GetSessionProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
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
   * Method provide
   * {@inheritDoc}
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): SessionOutput
  {
    if (null === $this->security->getUser()) {
      throw new AccessDeniedHttpException('Authentication required');
    }

    $sessionId = $uriVariables['id'] ?? null;

    if (!is_string($sessionId)) {
      throw new NotFoundHttpException('Session ID is required.');
    }

    /** @var GetSessionResult $result */
    $result = $this->queryBus->ask(query: new GetSessionQuery(sessionId: $sessionId));

    $output = new SessionOutput();
    $output->id = $result->sessionId;
    $output->userId = $result->userId;
    $output->ipAddress = $result->ipAddress;
    $output->userAgent = $result->userAgent;
    $output->deviceType = $result->deviceType;
    $output->browser = $result->browser;
    $output->createdAt = $result->createdAt->format('c');
    $output->lastActivityAt = $result->lastActivityAt->format('c');
    $output->isActive = !$result->isRevoked;

    return $output;
  }

  // #endregion
}
