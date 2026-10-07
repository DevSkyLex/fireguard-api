<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\EquipmentTypeCatalog;

use ApiPlatform\Metadata\{GetCollection, Patch, Post};
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\Contract\EquipmentTypeCatalog\EquipmentTypeDescriptor;
use Equipment\Application\UseCase\Command\EquipmentTypeCatalog\CreateEquipmentType\{CreateEquipmentTypeCommand, CreateEquipmentTypeResult};
use Equipment\Application\UseCase\Command\EquipmentTypeCatalog\PatchEquipmentType\{PatchEquipmentTypeCommand, PatchEquipmentTypeResult};
use Equipment\Application\UseCase\Query\EquipmentTypeCatalog\ListEquipmentTypes\{ListEquipmentTypesQuery, ListEquipmentTypesResult};
use Equipment\Presentation\Api\Dto\Input\EquipmentTypeCatalog\{CreateEquipmentTypeInput, PatchEquipmentTypeInput};
use Equipment\Presentation\Api\Processor\EquipmentTypeCatalog\EquipmentTypeCatalogProcessor;
use Equipment\Presentation\Api\Provider\EquipmentTypeCatalog\EquipmentTypeCatalogProvider;
use Equipment\Presentation\Api\Service\EquipmentTypeCatalogAccess;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

/**
 * Test EquipmentTypeCatalogPresentationTest.
 *
 * @category Tests
 */
#[CoversClass(EquipmentTypeCatalogProvider::class)]
#[CoversClass(EquipmentTypeCatalogProcessor::class)]
#[CoversClass(EquipmentTypeCatalogAccess::class)]
final class EquipmentTypeCatalogPresentationTest extends TestCase
{
  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655491001';

  #[Test]
  public function testProviderProjectsCatalogAndUsesReadPermission(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())->method('ask')->with(self::callback(static fn (ListEquipmentTypesQuery $query): bool => self::ORGANIZATION_ID === $query->organizationId))->willReturn(
      new ListEquipmentTypesResult([new EquipmentTypeDescriptor('water_mist', 'Water mist', 'fire', true, 2)]),
    );
    $outputs = new EquipmentTypeCatalogProvider($queryBus, $this->access('organization.equipment.read'))->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
    self::assertIsArray($outputs);
    self::assertCount(1, $outputs);
    self::assertSame('water_mist', $outputs[0]->value);
    self::assertSame('Water mist', $outputs[0]->label);
    self::assertSame('fire', $outputs[0]->family);
    self::assertTrue($outputs[0]->archived);
    self::assertSame(2, $outputs[0]->revision);
  }

  #[Test]
  public function testMissingReadPermissionNeverDispatches(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');
    $this->expectException(AccessDeniedHttpException::class);
    new EquipmentTypeCatalogProvider($queryBus, $this->access('organization.equipment.read', OrganizationAccessDecision::MISSING_PERMISSION))->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testOutsideOrganizationIsHidden(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');
    $this->expectException(NotFoundHttpException::class);
    new EquipmentTypeCatalogProvider($queryBus, $this->access('organization.equipment.read', OrganizationAccessDecision::OUTSIDE_SCOPE))->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessorCreatesViaCommandBus(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch')->with(self::callback(static fn (CreateEquipmentTypeCommand $command): bool => self::ORGANIZATION_ID === $command->organizationId && 'water_mist' === $command->value && 'Water mist' === $command->label && 'fire' === $command->family))->willReturn(
      new CreateEquipmentTypeResult(new EquipmentTypeDescriptor('water_mist', 'Water mist', 'fire', false, 1)),
    );
    $input = new CreateEquipmentTypeInput();
    $input->value = 'water_mist';
    $input->label = 'Water mist';
    $output = new EquipmentTypeCatalogProcessor($commandBus, $this->access('organization.equipment.write'))->process($input, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
    self::assertSame('water_mist', $output->value);
    self::assertSame(1, $output->revision);
  }

  #[Test]
  public function testProcessorPreservesPatchRevisionAndArchiveIntent(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch')->with(self::callback(static fn (PatchEquipmentTypeCommand $command): bool => 'water_mist' === $command->value && 1 === $command->revision && true === $command->archived))->willReturn(
      new PatchEquipmentTypeResult(new EquipmentTypeDescriptor('water_mist', 'Water mist', 'fire', true, 2)),
    );
    $input = new PatchEquipmentTypeInput();
    $input->revision = 1;
    $input->archived = true;
    $output = new EquipmentTypeCatalogProcessor($commandBus, $this->access('organization.equipment.write'))->process($input, new Patch(), ['organizationId' => self::ORGANIZATION_ID, 'typeCode' => 'water_mist']);
    self::assertTrue($output->archived);
    self::assertSame(2, $output->revision);
  }

  /**
   * Method access.
   *
   * @param string $permission expected permission
   * @param OrganizationAccessDecision $decision access decision
   *
   * @return EquipmentTypeCatalogAccess gated presentation service
   */
  private function access(string $permission, OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED): EquipmentTypeCatalogAccess
  {
    $user = new SecurityUser('770e8400-e29b-41d4-a716-446655491002', 'catalog@example.com', 'unused');
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with($user->getId(), self::ORGANIZATION_ID, $permission)->willReturn($decision);

    return new EquipmentTypeCatalogAccess($security, $authorization);
  }
}
