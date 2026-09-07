<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\EmailOwnership;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\EmailOwnership\ConfirmEmailOwnership\{ConfirmEmailOwnershipCommand, ConfirmEmailOwnershipResult};
use Auth\Presentation\Api\Dto\Input\EmailOwnership\ConfirmEmailOwnershipInput;
use Auth\Presentation\Api\Dto\Output\EmailOwnership\EmailOwnershipOutput;
use Auth\Presentation\Api\Service\EmailOwnershipActor;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function max;
use function time;

/**
 * Processor ConfirmEmailOwnershipProcessor.
 *
 * @implements ProcessorInterface<ConfirmEmailOwnershipInput, EmailOwnershipOutput>
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmEmailOwnershipProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param CommandBusPort $bus the application message bus
   * @param EmailOwnershipActor $actor authenticated request principal
   * @param RateLimiterFactory $limiter per-account abuse protection
   */
  public function __construct(private CommandBusPort $bus, private EmailOwnershipActor $actor, #[Autowire(service: 'limiter.email_ownership_confirm')] private RateLimiterFactory $limiter)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param ConfirmEmailOwnershipInput $data deserialized input
   * @param Operation $operation the API operation
   * @param array<string, mixed> $uriVariables route variables
   * @param array<string, mixed> $context serializer context
   *
   * @return EmailOwnershipOutput the public response
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EmailOwnershipOutput
  {
    $userId = $this->actor->id();
    $limit = $this->limiter->create($userId)->consume();
    if (!$limit->isAccepted()) {
      throw new TooManyRequestsHttpException(max(0, $limit->getRetryAfter()->getTimestamp() - time()));
    }
    /**
     * @var ConfirmEmailOwnershipResult $result
     */
    $result = $this->bus->dispatch(new ConfirmEmailOwnershipCommand($userId, $data->challengeToken, $data->code));

    return new EmailOwnershipOutput($result->verified);
  }
  // #endregion
}
