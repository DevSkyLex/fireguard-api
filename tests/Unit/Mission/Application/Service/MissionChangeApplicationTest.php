<?php

declare(strict_types=1);

namespace Tests\Unit\Mission\Application\Service;

use Mission\Application\Port\Outbound\MissionChangeApplierPort;
use Mission\Application\Service\MissionChangeApplication;
use Mission\Domain\Exception\MissionConflictException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(MissionChangeApplication::class)]
final class MissionChangeApplicationTest extends TestCase
{
  #[Test]
  public function testDelegatesPatchToOwningModule(): void
  {
    $equipment = $this->createMock(MissionChangeApplierPort::class);
    $equipment->expects(self::once())->method('supports')->with('/api/equipment/equipment-1')->willReturn(true);
    $equipment->expects(self::once())
      ->method('apply')
      ->with('organization-1', '/api/equipment/equipment-1', ['status' => 'operational']);

    new MissionChangeApplication([$equipment])->apply(
      'organization-1',
      '/api/equipment/equipment-1',
      ['status' => 'operational'],
    );
  }

  #[Test]
  public function testRejectsUnsupportedResource(): void
  {
    $this->expectException(MissionConflictException::class);
    $this->expectExceptionMessage('Unsupported mission change resource "/api/users/user-1".');

    new MissionChangeApplication([])->apply('organization-1', '/api/users/user-1', []);
  }
}
