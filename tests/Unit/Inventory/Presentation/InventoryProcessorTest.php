<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory\Presentation;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inventory\Application\UseCase\Command\ManageInventoryReference\{ManageInventoryReferenceCommand,ManageInventoryReferenceResult};
use Inventory\Domain\Model\Stock\InventoryReference;
use Inventory\Presentation\Api\Dto\Input\{CorrectInventoryStockInput,CreateInventoryWarehouseInput};
use Inventory\Presentation\Api\Dto\Output\InventoryWarehouseOutput;
use Inventory\Presentation\Api\Processor\InventoryProcessor;
use Inventory\Presentation\Api\Service\{InventoryAccess,InventoryOutputFactory};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Financial writes require the separate gate before dispatch. @category Unit Tests */
final class InventoryProcessorTest extends TestCase
{
  private const string ORG = 'beb70000-0000-4000-8000-000000000001';

  #[Test]
  public function correctionCannotModifyValuationWithOnlyInventoryManage(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $auth = $this->createStub(OrganizationAuthorizationPort::class);
    $auth->method('resolveAccess')->willReturnCallback(static fn (string $actor, string $org, string $permission): OrganizationAccessDecision => 'organization.maintenance_cost.manage' === $permission ? OrganizationAccessDecision::MISSING_PERMISSION : OrganizationAccessDecision::GRANTED);
    $processor = new InventoryProcessor($commands, $this->access($auth), new InventoryOutputFactory());
    $this->expectException(AccessDeniedHttpException::class);
    $processor->process(new CorrectInventoryStockInput(), new Post(name:'inventory_corrections_create'), ['organizationId' => self::ORG]);
  }

  #[Test]
  public function warehouseNamesAreTranslatedThroughTheCommandAndStableIdentifier(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->willReturnCallback(static function (ManageInventoryReferenceCommand $c): ManageInventoryReferenceResult {
      self::assertSame('warehouses', $c->type);
      self::assertSame('Main stock', $c->label);
      self::assertSame('M', $c->code);

      return new ManageInventoryReferenceResult(new InventoryReference('warehouse', self::ORG, 'M', 'Main stock'));
    });
    $auth = $this->createStub(OrganizationAuthorizationPort::class);
    $auth->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $input = new CreateInventoryWarehouseInput();
    $input->code = 'M';
    $input->name = 'Main stock';
    $output = new InventoryProcessor($commands, $this->access($auth), new InventoryOutputFactory())->process($input, new Post(name:'inventory_warehouses_create'), ['organizationId' => self::ORG]);
    self::assertInstanceOf(InventoryWarehouseOutput::class, $output);
    self::assertSame('warehouse', $output->id);
    self::assertSame('Main stock', $output->name);
  }

  private function access(OrganizationAuthorizationPort $auth): InventoryAccess
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('beb70000-0000-4000-8000-000000000003', 'stock@corp.example', 'unused', ['ROLE_USER']));

    return new InventoryAccess($security, $auth);
  }
}
