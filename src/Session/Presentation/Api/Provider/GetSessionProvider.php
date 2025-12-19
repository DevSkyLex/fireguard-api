<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Session\Application\UseCase\Query\GetSession\GetSessionHandler;
use Session\Application\UseCase\Query\GetSession\GetSessionQuery;
use Session\Domain\Exception\SessionNotFoundException;
use Session\Presentation\Api\Dto\SessionOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * @param GetSessionHandler $handler  the query handler
     * @param Security          $security the security service
     */
    public function __construct(
        private GetSessionHandler $handler,
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

        try {
            $query = new GetSessionQuery(sessionId: $sessionId);
            $result = ($this->handler)($query);

            $output = new SessionOutput();
            $output->id = $result->sessionId;
            $output->userId = $result->userId;
            $output->ipAddress = $result->ipAddress;
            $output->userAgent = $result->userAgent;
            $output->createdAt = $result->createdAt->format('c');
            $output->lastActivityAt = $result->lastActivityAt->format('c');
            $output->isActive = !$result->isRevoked;

            return $output;
        } catch (SessionNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }
    }
    // #endregion
}
