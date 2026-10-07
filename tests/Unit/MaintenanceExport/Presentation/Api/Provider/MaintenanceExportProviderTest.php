<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceExport\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use MaintenanceExport\Application\UseCase\Query\ReadMaintenanceExport\{ReadMaintenanceExportQuery,ReadMaintenanceExportResult};
use MaintenanceExport\Presentation\Api\Operation\MaintenanceExportOperations;
use MaintenanceExport\Presentation\Api\Provider\MaintenanceExportProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\{Request,RequestStack,Response};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function hash;

/**
 * Class MaintenanceExportProviderTest
 * The provider transfers saved bytes without invoking a renderer.
 *
 * @category Test
 */
final class MaintenanceExportProviderTest extends TestCase
{
  #[Test]
  public function savedUnicodeAndCsvQuotesAreTransferredExactlyWithPrivateCacheHeaders(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('actor');
    $stack = new RequestStack();
    $stack->push(Request::create('/api/organizations/org/maintenance-exports/export/files/csv'));
    $bytes = "id,label\r\n\"id\",\"équipement \"\"historique\"\"\"\r\n";
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (ReadMaintenanceExportQuery $query): bool => 'org' === $query->organizationId && 'file' === $query->action && 'csv' === $query->format))->willReturn(new ReadMaintenanceExportResult('file', [], bytes:$bytes, mediaType:'text/csv', fileName:'fireguard-export.csv', sha256:hash('sha256', $bytes)));
    $response = new MaintenanceExportProvider($queries, $actor, $stack)->provide(new Get(name:MaintenanceExportOperations::FILE), ['organizationId' => 'org', 'id' => 'export', 'format' => 'csv']);
    self::assertInstanceOf(Response::class, $response);
    self::assertSame($bytes, $response->getContent());
    self::assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
    self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    self::assertStringContainsString('no-store', $response->headers->get('Cache-Control') ?? '');
  }

  #[Test]
  public function unauthenticatedReadNeverDispatches(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn(null);
    $this->expectException(AccessDeniedHttpException::class);
    new MaintenanceExportProvider($queries, $actor, new RequestStack())->provide(new Get(name:MaintenanceExportOperations::EXPORT));
  }
}
