<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Contract\Quota;

use Organization\Application\Contract\Quota\{OrganizationQuotaExceededException, OrganizationQuotaResource};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test OrganizationQuotaExceededException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationQuotaExceededException::class)]
final class OrganizationQuotaExceededExceptionTest extends TestCase
{
  #[Test]
  public function testForResourceBuildsRuntimeException(): void
  {
    $exception = OrganizationQuotaExceededException::forResource(
      OrganizationQuotaResource::MEMBERS->value,
      5,
    );

    self::assertInstanceOf(OrganizationQuotaExceededException::class, $exception);
    self::assertInstanceOf(RuntimeException::class, $exception);
  }

  #[Test]
  public function testForResourceMessageCarriesLimitAndResourceValue(): void
  {
    $exception = OrganizationQuotaExceededException::forResource(
      OrganizationQuotaResource::FACILITIES->value,
      125,
    );

    self::assertSame(
      'The current plan limit of 125 facilities has been reached.',
      $exception->getMessage(),
    );
  }

  /**
   * @param OrganizationQuotaResource $resource the capped resource case
   * @param string $expected the expected exception message
   */
  #[Test]
  #[DataProvider('resourceProvider')]
  public function testForResourceCoversEveryResourceCase(
    OrganizationQuotaResource $resource,
    string $expected,
  ): void {
    $exception = OrganizationQuotaExceededException::forResource($resource->value, 10);

    self::assertSame($expected, $exception->getMessage());
  }

  /**
   * @return iterable<string,array{OrganizationQuotaResource,string}>
   */
  public static function resourceProvider(): iterable
  {
    yield 'members' => [
      OrganizationQuotaResource::MEMBERS,
      'The current plan limit of 10 members has been reached.',
    ];
    yield 'facilities' => [
      OrganizationQuotaResource::FACILITIES,
      'The current plan limit of 10 facilities has been reached.',
    ];
    yield 'equipment' => [
      OrganizationQuotaResource::EQUIPMENT,
      'The current plan limit of 10 equipment has been reached.',
    ];
    yield 'inspections' => [
      OrganizationQuotaResource::INSPECTIONS,
      'The current plan limit of 10 inspections has been reached.',
    ];
  }
}
