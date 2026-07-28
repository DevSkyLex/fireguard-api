<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Pagination;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Pagination\PaginationExtractor;

/**
 * Test PaginationExtractorTest.
 *
 * @category Pagination Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PaginationExtractor::class)]
final class PaginationExtractorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testItDefaultsToTheFirstPage(): void
  {
    $params = PaginationExtractor::fromContext([]);

    self::assertSame(1, $params->page);
    self::assertSame(30, $params->itemsPerPage);
    self::assertSame(0, $params->offset);
  }

  #[Test]
  public function testItReadsPageAndItemsPerPageFromTheFilters(): void
  {
    $params = PaginationExtractor::fromContext([
      'filters' => ['page' => 3, 'itemsPerPage' => 10],
    ]);

    self::assertSame(3, $params->page);
    self::assertSame(10, $params->itemsPerPage);
    self::assertSame(20, $params->offset);
  }

  #[Test]
  public function testItCoercesNumericStrings(): void
  {
    $params = PaginationExtractor::fromContext([
      'filters' => ['page' => '4', 'itemsPerPage' => '25'],
    ]);

    self::assertSame(4, $params->page);
    self::assertSame(25, $params->itemsPerPage);
    self::assertSame(75, $params->offset);
  }

  #[Test]
  public function testItClampsNonPositiveValuesToOne(): void
  {
    $params = PaginationExtractor::fromContext([
      'filters' => ['page' => 0, 'itemsPerPage' => -5],
    ]);

    self::assertSame(1, $params->page);
    self::assertSame(1, $params->itemsPerPage);
    self::assertSame(0, $params->offset);
  }

  #[Test]
  public function testItFallsBackWhenTheValuesAreNotNumeric(): void
  {
    $params = PaginationExtractor::fromContext([
      'filters' => ['page' => 'abc', 'itemsPerPage' => 'xyz'],
    ]);

    self::assertSame(1, $params->page);
    self::assertSame(30, $params->itemsPerPage);
  }

  #[Test]
  public function testItIgnoresANonArrayFiltersEntry(): void
  {
    $params = PaginationExtractor::fromContext(['filters' => 'not-an-array']);

    self::assertSame(1, $params->page);
    self::assertSame(30, $params->itemsPerPage);
    self::assertSame(0, $params->offset);
  }

  #[Test]
  public function testItHonoursACustomDefaultItemsPerPage(): void
  {
    $params = PaginationExtractor::fromContext([], 50);

    self::assertSame(50, $params->itemsPerPage);
    self::assertSame(1, $params->page);
  }

  #[Test]
  public function testTheCustomDefaultAlsoAppliesToNonNumericValues(): void
  {
    $params = PaginationExtractor::fromContext(['filters' => ['itemsPerPage' => null]], 15);

    self::assertSame(15, $params->itemsPerPage);
  }
  // #endregion
}
