<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\Service;

use Approval\Application\Contract\Action\{ApprovalActionDescriptor, ApprovalActionKey};
use Approval\Application\Port\Outbound\{ApprovalActionDescriptorProviderPort, ApprovalActionExecutorPort};
use Approval\Application\Service\ApprovalActionCatalogRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Tests\Support\Catalogs\BuiltInCapabilityCatalogs;

use function array_column;

/**
 * Class ApprovalActionCatalogRegistryTest
 *
 * @category Test
 */
#[CoversClass(ApprovalActionCatalogRegistry::class)]
final class ApprovalActionCatalogRegistryTest extends TestCase
{
  #[Test]
  public function contributesAnActionWithoutChangingTheEngine(): void
  {
    $descriptor = new ApprovalActionDescriptor('invoice.release', 'Invoice release');
    $provider = $this->createStub(ApprovalActionDescriptorProviderPort::class);
    $provider->method('descriptor')->willReturn($descriptor);
    $executor = $this->createStub(ApprovalActionExecutorPort::class);
    $executor->method('actionType')->willReturn($descriptor->value);
    $registry = new ApprovalActionCatalogRegistry([$provider], [$executor]);

    self::assertSame([$descriptor], $registry->descriptors());
    self::assertSame($descriptor, $registry->find('invoice.release'));
    self::assertNull($registry->find('uninstalled.action'));
  }

  #[Test]
  public function rejectsDuplicateMetadataAtConstruction(): void
  {
    $provider = $this->createStub(ApprovalActionDescriptorProviderPort::class);
    $provider->method('descriptor')->willReturn(new ApprovalActionDescriptor('invoice.release', 'Invoice release'));
    $executor = $this->createStub(ApprovalActionExecutorPort::class);
    $executor->method('actionType')->willReturn('invoice.release');

    $this->expectException(InvalidArgumentException::class);
    new ApprovalActionCatalogRegistry([$provider, $provider], [$executor]);
  }

  #[Test]
  public function rejectsAnActionWithoutItsExecutor(): void
  {
    $provider = $this->createStub(ApprovalActionDescriptorProviderPort::class);
    $provider->method('descriptor')->willReturn(new ApprovalActionDescriptor('invoice.release', 'Invoice release'));

    $this->expectException(InvalidArgumentException::class);
    new ApprovalActionCatalogRegistry([$provider], []);
  }

  #[Test]
  public function keepsLegacyMetadataAndHistoricalRepresentation(): void
  {
    $catalog = BuiltInCapabilityCatalogs::approval();
    self::assertSame(['nc_waiver', 'equipment_decommission'], array_column($catalog->descriptors(), 'value'));
    self::assertSame('critical', $catalog->find('nc_waiver')?->defaultMinSeverity);
    self::assertNull($catalog->find('equipment_decommission')?->defaultMinSeverity);
    self::assertSame('removed.action', new ApprovalActionKey('removed.action')->value);
  }
}
