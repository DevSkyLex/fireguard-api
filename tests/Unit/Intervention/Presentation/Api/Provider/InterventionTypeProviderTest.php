<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use Intervention\Presentation\Api\Provider\InterventionTypeProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_column;

/**
 * Test InterventionTypeProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionTypeProvider::class)]
final class InterventionTypeProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideReturnsTheThreeInterventionTypes(): void
  {
    $outputs = new InterventionTypeProvider()->provide(new GetCollection());

    self::assertCount(3, $outputs);
    self::assertSame(
      ['site_setup', 'inventory', 'inspection_campaign'],
      array_column($outputs, 'id'),
    );
  }

  #[Test]
  public function testProvideDescribesTheActionsEachTypeUnlocks(): void
  {
    $outputs = new InterventionTypeProvider()->provide(new GetCollection());

    self::assertSame('Site setup', $outputs[0]->label);
    self::assertSame(['site_setup', 'inventory'], $outputs[0]->actions);
    self::assertSame(['inventory'], $outputs[1]->actions);
    self::assertSame(['inspection'], $outputs[2]->actions);
    self::assertNotSame('', $outputs[2]->description);
  }
  // #endregion
}
