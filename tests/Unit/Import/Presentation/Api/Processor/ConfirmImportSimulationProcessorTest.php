<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Import\Application\UseCase\Command\ConfirmImportSimulation\ConfirmImportSimulationCommand;
use Import\Application\UseCase\Query\GetImportJob\GetImportJobResult;
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind};
use Import\Presentation\Api\Factory\ImportJobOutputFactory;
use Import\Presentation\Api\Processor\ConfirmImportSimulationProcessor;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;

final class ConfirmImportSimulationProcessorTest extends TestCase
{
  public function testOnlyActorAndSimulationIdentifierReachTheCommand(): void
  {
    $job = ImportJob::create(ImportJobId::fromString('bd000000-0000-4000-8000-000000000192'), 'org', ImportKind::FACILITY, 'retained.csv', 'facilities.csv', 'actor');
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ConfirmImportSimulationCommand $c): bool => 'actor' === $c->userId && 'simulation' === $c->simulationId))->willReturn(GetImportJobResult::fromDomain($job));
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('actor');
    $result = new ConfirmImportSimulationProcessor($bus, $actor, new ImportJobOutputFactory())->process(null, new Post(), ['id' => 'simulation']);
    self::assertSame((string) $job->id(), $result->id);
    self::assertFalse($result->dryRun);
  }
}
