<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign;

use DateTimeImmutable;
use Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign\GenerateInspectionCampaignCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\CommandMessage;

/**
 * Test GenerateInspectionCampaignCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GenerateInspectionCampaignCommand::class)]
final class GenerateInspectionCampaignCommandTest extends TestCase
{
  #[Test]
  public function testRoundTripsItsProperties(): void
  {
    $dueBefore = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $command = new GenerateInspectionCampaignCommand(
      'org-1',
      'user-1',
      'Q2 inspection sweep',
      'facility-1',
      'fire_extinguisher',
      $dueBefore,
    );

    self::assertInstanceOf(CommandMessage::class, $command);
    self::assertSame('org-1', $command->organizationId);
    self::assertSame('user-1', $command->actorUserId);
    self::assertSame('Q2 inspection sweep', $command->name);
    self::assertSame('facility-1', $command->facilityId);
    self::assertSame('fire_extinguisher', $command->equipmentType);
    self::assertSame($dueBefore, $command->dueBefore);
  }

  #[Test]
  public function testAllowsOptionalFiltersToBeNull(): void
  {
    $command = new GenerateInspectionCampaignCommand(
      'org-1',
      'user-1',
      'All equipment',
      null,
      null,
      new DateTimeImmutable('2026-06-01T00:00:00+00:00'),
    );

    self::assertNull($command->facilityId);
    self::assertNull($command->equipmentType);
  }
}
