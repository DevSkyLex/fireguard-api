<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Provider\Totp;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DateTimeInterface;
use Otp\Application\UseCase\Query\Totp\GetRecoveryCodesStatus\{GetRecoveryCodesStatusQuery, GetRecoveryCodesStatusResult};
use Otp\Presentation\Api\Dto\Output\Totp\RecoveryCodesStatusOutput;
use Otp\Presentation\Api\Service\RecoveryCodesActor;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Class GetRecoveryCodesStatusProvider
 *
 * Returns the authenticated owner's metadata without recovery credentials.
 *
 * @category Provider
 *
 * @implements ProviderInterface<RecoveryCodesStatusOutput>
 */
final readonly class GetRecoveryCodesStatusProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param QueryBusPort $bus application query dispatcher
   * @param RecoveryCodesActor $actor authenticated immutable identity
   *
   * @return void
   */
  public function __construct(private QueryBusPort $bus, private RecoveryCodesActor $actor)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   *
   * @access public
   *
   * @param Operation $operation matched operation
   * @param array<string, mixed> $uriVariables route variables
   * @param array<string, mixed> $context request context
   *
   * @return RecoveryCodesStatusOutput safe availability metadata
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): RecoveryCodesStatusOutput
  {
    /** @var GetRecoveryCodesStatusResult $result */
    $result = $this->bus->ask(new GetRecoveryCodesStatusQuery($this->actor->id()));

    return new RecoveryCodesStatusOutput($result->remainingCount, $result->generatedAt?->format(DateTimeInterface::ATOM));
  }
  // #endregion
}
