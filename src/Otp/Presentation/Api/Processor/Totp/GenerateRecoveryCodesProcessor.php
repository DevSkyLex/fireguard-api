<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Processor\Totp;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DateTimeInterface;
use Otp\Application\UseCase\Command\Totp\GenerateRecoveryCodes\{GenerateRecoveryCodesCommand, GenerateRecoveryCodesResult};
use Otp\Presentation\Api\Dto\Input\Totp\GenerateRecoveryCodesInput;
use Otp\Presentation\Api\Dto\Output\Totp\GenerateRecoveryCodesOutput;
use Otp\Presentation\Api\Service\RecoveryCodesActor;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function hash;
use function max;
use function time;

/**
 * Class GenerateRecoveryCodesProcessor
 *
 * Translates the authenticated request and guards the current-factor verification budget.
 *
 * @category Processor
 *
 * @implements ProcessorInterface<GenerateRecoveryCodesInput, GenerateRecoveryCodesOutput>
 */
final readonly class GenerateRecoveryCodesProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param CommandBusPort $bus application command dispatcher
   * @param RecoveryCodesActor $actor authenticated immutable identity
   * @param ?RateLimiterFactory $rateLimiter per-user generation limiter
   *
   * @return void
   */
  public function __construct(
    private CommandBusPort $bus,
    private RecoveryCodesActor $actor,
    #[Autowire(service: 'limiter.otp_totp_recovery_codes')]
    private ?RateLimiterFactory $rateLimiter = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   *
   * @access public
   *
   * @param GenerateRecoveryCodesInput $data active TOTP proof
   * @param Operation $operation matched API operation
   * @param array<string, mixed> $uriVariables route variables
   * @param array<string, mixed> $context request context
   *
   * @return GenerateRecoveryCodesOutput plaintext lot after durable persistence
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): GenerateRecoveryCodesOutput
  {
    $userId = $this->actor->id();
    $limit = $this->rateLimiter?->create(hash('sha256', $userId))->consume();
    if (null !== $limit && !$limit->isAccepted()) {
      throw new TooManyRequestsHttpException(max(0, $limit->getRetryAfter()->getTimestamp() - time()), 'Too many recovery code generation attempts.');
    }
    /** @var GenerateRecoveryCodesResult $result */
    $result = $this->bus->dispatch(new GenerateRecoveryCodesCommand($userId, $data->code));

    return new GenerateRecoveryCodesOutput($result->codes, $result->generatedAt->format(DateTimeInterface::ATOM));
  }
  // #endregion
}
