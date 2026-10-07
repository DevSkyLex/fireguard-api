<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceExport\Presentation\Api\Processor;

use ApiPlatform\Metadata\Patch;
use MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport\{ManageMaintenanceExportCommand,ManageMaintenanceExportResult};
use MaintenanceExport\Presentation\Api\Dto\Input\WriteMaintenanceExportReferenceInput;
use MaintenanceExport\Presentation\Api\Dto\Output\MaintenanceExportReferenceOutput;
use MaintenanceExport\Presentation\Api\Operation\MaintenanceExportOperations;
use MaintenanceExport\Presentation\Api\Processor\MaintenanceExportProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\{Request,RequestStack};

/**
 * Class MaintenanceExportProcessorTest
 * Route scope and quoted zero revision remain explicit on reference creation.
 *
 * @category Test
 */
final class MaintenanceExportProcessorTest extends TestCase
{
  #[Test]
  public function mappingUpsertUsesOwnedTargetAndQuotedRevisionZero(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('actor');
    $input = new WriteMaintenanceExportReferenceInput();
    $input->clientOperationId = 'operation';
    $input->reference = 'ERP-999';
    $input->system = 'erp';
    $request = Request::create('/reference', 'PATCH', server:['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-0"'], content:'{"clientOperationId":"operation","system":"erp","reference":"ERP-999"}');
    $stack = new RequestStack();
    $stack->push($request);
    $commands->expects(self::once())->method('dispatch')->with(self::callback(static fn (ManageMaintenanceExportCommand $command): bool => 'organization' === $command->organizationId && 'reference' === $command->action && 'resource' === $command->id && 0 === $command->expectedRevision && 'equipment' === $command->payload['resourceType']))->willReturn(new ManageMaintenanceExportResult('reference', ['id' => 'mapping', 'organizationId' => 'organization', 'resourceType' => 'equipment', 'resourceId' => 'resource', 'system' => 'erp', 'reference' => 'ERP-999', 'revision' => 1, 'updatedAt' => '2026-10-07T12:00:00Z']));
    $output = new MaintenanceExportProcessor($commands, $actor, $stack)->process($input, new Patch(name:MaintenanceExportOperations::WRITE_REFERENCE), ['organizationId' => 'organization', 'resourceType' => 'equipment', 'resourceId' => 'resource']);
    self::assertInstanceOf(MaintenanceExportReferenceOutput::class, $output);
    self::assertSame(1, $output->revision);
    self::assertSame('ERP-999', $output->reference);
    self::assertFalse($output->replayed);
  }
}
