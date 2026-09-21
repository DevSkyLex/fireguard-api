<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Import\Application\UseCase\Query\GetImportTemplate\{GetImportTemplateQuery, GetImportTemplateResult};
use Import\Presentation\Api\Dto\Output\ImportTemplateOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/** @implements ProviderInterface<ImportTemplateOutput> */
final readonly class ImportTemplateProvider implements ProviderInterface
{
  public function __construct(private QueryBusPort $queries, private CurrentActorPort $actor)
  {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ImportTemplateOutput
  {
    $actor = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organizationId = $uriVariables['organizationId'] ?? null;
    $kind = $uriVariables['kind'] ?? null;
    if (!is_string($organizationId) || !is_string($kind)) {
      throw new BadRequestHttpException('Organization and kind are required.');
    }
    /** @var GetImportTemplateResult $result */
    $result = $this->queries->ask(new GetImportTemplateQuery($actor, $organizationId, $kind));

    return new ImportTemplateOutput($kind, $result->filename, $result->content, $result->mediaType);
  }
}
