<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Provider\EmailOwnership;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\UseCase\Query\EmailOwnership\GetEmailOwnership\{GetEmailOwnershipQuery, GetEmailOwnershipResult};
use Auth\Presentation\Api\Dto\Output\EmailOwnership\EmailOwnershipOutput;
use Auth\Presentation\Api\Service\EmailOwnershipActor;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Provider GetEmailOwnershipProvider.
 *
 * @implements ProviderInterface<EmailOwnershipOutput>
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEmailOwnershipProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param QueryBusPort $bus the application message bus
   * @param EmailOwnershipActor $actor authenticated request principal
   */
  public function __construct(private QueryBusPort $bus, private EmailOwnershipActor $actor)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param Operation $operation the API operation
   * @param array<string, mixed> $uriVariables route variables
   * @param array<string, mixed> $context serializer context
   *
   * @return EmailOwnershipOutput the public response
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): EmailOwnershipOutput
  {
    $userId = $this->actor->id();
    /**
     * @var GetEmailOwnershipResult $result
     */
    $result = $this->bus->ask(new GetEmailOwnershipQuery($userId));

    return new EmailOwnershipOutput($result->verified);
  }
  // #endregion
}
