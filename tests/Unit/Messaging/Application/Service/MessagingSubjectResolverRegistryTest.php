<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Service;

use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\MessagingSubjectResolverPort;
use Messaging\Application\Service\MessagingSubjectResolverRegistry;
use Messaging\Domain\Exception\MessagingSubjectNotFoundException;
use Messaging\Domain\ValueObject\MessagingSubjectType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingSubjectResolverRegistryTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingSubjectResolverRegistry::class)]
final class MessagingSubjectResolverRegistryTest extends TestCase
{
  #[Test]
  public function testResolveRoutesToTheSupportingResolver(): void
  {
    $facilityResolver = $this->resolverSupporting(MessagingSubjectType::FACILITY, exists: false);
    $equipmentResolver = $this->resolverSupporting(MessagingSubjectType::EQUIPMENT, exists: true, label: 'Extinguisher #1', permission: 'organization.equipment.read');

    $registry = new MessagingSubjectResolverRegistry([$facilityResolver, $equipmentResolver]);

    $resolution = $registry->resolve(MessagingSubjectType::EQUIPMENT, 'org-1', 'equipment-1');

    self::assertTrue($resolution->exists);
    self::assertSame('Extinguisher #1', $resolution->label);
    self::assertSame('organization.equipment.read', $resolution->requiredReadPermission);
  }

  #[Test]
  public function testResolveThrowsWhenNoResolverSupportsTheType(): void
  {
    $registry = new MessagingSubjectResolverRegistry([]);

    $this->expectException(MessagingSubjectNotFoundException::class);

    $registry->resolve(MessagingSubjectType::FACILITY, 'org-1', 'facility-1');
  }

  #[Test]
  public function testResolveThrowsWhenTheSubjectDoesNotExist(): void
  {
    $resolver = $this->resolverSupporting(MessagingSubjectType::FACILITY, exists: false);
    $registry = new MessagingSubjectResolverRegistry([$resolver]);

    $this->expectException(MessagingSubjectNotFoundException::class);

    $registry->resolve(MessagingSubjectType::FACILITY, 'org-1', 'unknown-facility');
  }

  private function resolverSupporting(
    MessagingSubjectType $type,
    bool $exists,
    ?string $label = null,
    string $permission = 'organization.facilities.read',
  ): MessagingSubjectResolverPort {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('supports')->willReturnCallback(static fn (MessagingSubjectType $candidate): bool => $candidate === $type);
    $resolver->method('resolve')->willReturn(new MessagingSubjectResolution($exists, $label, $permission));

    return $resolver;
  }
}
