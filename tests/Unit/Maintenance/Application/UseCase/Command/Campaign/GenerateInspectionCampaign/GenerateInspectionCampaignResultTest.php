<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign;

use Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign\GenerateInspectionCampaignResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test GenerateInspectionCampaignResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GenerateInspectionCampaignResult::class)]
final class GenerateInspectionCampaignResultTest extends TestCase
{
  #[Test]
  public function testRoundTripsItsProperties(): void
  {
    $result = new GenerateInspectionCampaignResult('intervention-1', 12, 8);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame('intervention-1', $result->interventionId);
    self::assertSame(12, $result->number);
    self::assertSame(8, $result->workItemsCount);
  }
}
