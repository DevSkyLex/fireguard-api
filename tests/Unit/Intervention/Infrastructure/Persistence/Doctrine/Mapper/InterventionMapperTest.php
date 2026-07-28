<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Persistence\Doctrine\Mapper;

use Intervention\Infrastructure\Persistence\Doctrine\Mapper\InterventionMapper;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionMapperTest.
 *
 * The organization association is `nullable: false` in the mapping, so a
 * record without one is a corrupted row rather than a domain state — the
 * mapper must refuse it instead of rehydrating an unscoped intervention.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionMapper::class)]
final class InterventionMapperTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testToDomainRefusesARecordWithoutAnOrganization(): void
  {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Intervention record must reference an organization.');

    InterventionMapper::toDomain(new InterventionRecord());
  }
  // #endregion
}
