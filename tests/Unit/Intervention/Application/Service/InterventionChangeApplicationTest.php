<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use Intervention\Application\Port\Outbound\InterventionChangeApplierPort;
use Intervention\Application\Service\InterventionChangeApplication;
use Intervention\Domain\Exception\InterventionConflictException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(InterventionChangeApplication::class)]
final class InterventionChangeApplicationTest extends TestCase
{
  #[Test]
  public function testDelegatesPatchToOwningModule(): void
  {
    $equipment = $this->createMock(InterventionChangeApplierPort::class);
    $equipment->expects(self::once())->method('supports')->with('/api/equipment/equipment-1')->willReturn(true);
    $equipment->expects(self::once())
      ->method('apply')
      ->with('organization-1', '/api/equipment/equipment-1', ['status' => 'operational']);

    new InterventionChangeApplication([$equipment])->apply(
      'organization-1',
      '/api/equipment/equipment-1',
      ['status' => 'operational'],
    );
  }

  #[Test]
  public function testRejectsUnsupportedResource(): void
  {
    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Unsupported intervention change resource "/api/users/user-1".');

    new InterventionChangeApplication([])->apply('organization-1', '/api/users/user-1', []);
  }
}
