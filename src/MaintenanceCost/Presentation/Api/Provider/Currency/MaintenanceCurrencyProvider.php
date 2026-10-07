<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Provider\Currency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use MaintenanceCost\Application\UseCase\Query\Currency\ReadMaintenanceCurrency\{ReadMaintenanceCurrencyQuery, ReadMaintenanceCurrencyResult};
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use MaintenanceCost\Presentation\Api\Dto\Output\Currency\MaintenanceCurrencyOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;

/** @implements ProviderInterface<MaintenanceCurrencyOutput> */
final readonly class MaintenanceCurrencyProvider implements ProviderInterface
{
  public function __construct(private QueryBusPort $queries, private CurrentActorPort $actor)
  {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): MaintenanceCurrencyOutput
  {
    $actor = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId)) {
      throw MaintenanceCostException::notFound();
    }
    /** @var ReadMaintenanceCurrencyResult $result */
    $result = $this->queries->ask(new ReadMaintenanceCurrencyQuery($organizationId, $actor));

    return MaintenanceCurrencyOutput::fromSnapshot($result->currency);
  }
}
