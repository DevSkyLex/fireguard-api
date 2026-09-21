<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use Import\Application\UseCase\Query\GetImportTemplate\{GetImportTemplateQuery, GetImportTemplateResult};
use Import\Presentation\Api\Provider\ImportTemplateProvider;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;

final class ImportTemplateProviderTest extends TestCase
{
  public function testReturnsBackendContentWithoutRebuildingColumns(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (GetImportTemplateQuery $q): bool => 'actor' === $q->userId && 'org' === $q->organizationId && 'facility' === $q->kind))->willReturn(new GetImportTemplateResult('template.csv', "type,name\r\n"));
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('actor');
    $result = new ImportTemplateProvider($bus, $actor)->provide(new Get(), ['organizationId' => 'org', 'kind' => 'facility']);
    self::assertSame('template.csv', $result->filename);
    self::assertSame("type,name\r\n", $result->content);
  }
}
