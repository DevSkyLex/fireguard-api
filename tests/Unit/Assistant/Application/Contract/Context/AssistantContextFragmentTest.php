<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\Contract\Context;

use Assistant\Application\Contract\Context\AssistantContextFragment;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantContextFragment.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantContextFragment::class)]
final class AssistantContextFragmentTest extends TestCase
{
  #[Test]
  public function testWithTextCarriesTheContribution(): void
  {
    $fragment = AssistantContextFragment::withText('compliance.summary', 'Two extinguishers overdue.');

    self::assertSame('compliance.summary', $fragment->sourceKey);
    self::assertSame('Two extinguishers overdue.', $fragment->text);
    self::assertFalse($fragment->isEmpty());
  }

  #[Test]
  public function testEmptyBuildsANoContributionFragment(): void
  {
    $fragment = AssistantContextFragment::empty('inspection.summary');

    self::assertSame('inspection.summary', $fragment->sourceKey);
    self::assertSame('', $fragment->text);
    self::assertTrue($fragment->isEmpty());
  }

  #[Test]
  public function testIsEmptyIgnoresWhitespaceOnlyText(): void
  {
    $fragment = AssistantContextFragment::withText('maintenance.summary', "  \n\t ");

    self::assertTrue($fragment->isEmpty());
  }
}
