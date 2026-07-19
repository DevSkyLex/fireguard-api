<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Presentation\Api\Service;

use Audit\Presentation\Api\Service\AuditEventExportCriteriaFactory;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test AuditEventExportCriteriaFactoryTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuditEventExportCriteriaFactory::class)]
final class AuditEventExportCriteriaFactoryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFromRequestParsesAllSupportedFilters(): void
  {
    $factory = new AuditEventExportCriteriaFactory();

    $request = Request::create('/api/audit-events/export', 'GET', [
      'action' => 'auth.login_success',
      'actorType' => 'user',
      'actorId' => 'user-1',
      'subjectType' => 'token',
      'subjectId' => 'token-1',
      'clientId' => 'client-1',
      'tenantId' => 'tenant-1',
      'ipHash' => 'hash-1',
      'from' => '2026-01-01T00:00:00+00:00',
      'to' => '2026-01-31 23:59:59',
    ]);

    $criteria = $factory->fromRequest($request);

    self::assertSame('auth.login_success', $criteria->action);
    self::assertSame('user', $criteria->actorType);
    self::assertSame('user-1', $criteria->actorId);
    self::assertSame('token', $criteria->subjectType);
    self::assertSame('token-1', $criteria->subjectId);
    self::assertSame('client-1', $criteria->clientId);
    self::assertSame('tenant-1', $criteria->tenantId);
    self::assertSame('hash-1', $criteria->ipHash);
    self::assertInstanceOf(DateTimeImmutable::class, $criteria->from);
    self::assertSame('2026-01-01T00:00:00+00:00', $criteria->from->format(DateTimeImmutable::ATOM));
    self::assertInstanceOf(DateTimeImmutable::class, $criteria->to);
    self::assertSame('2026-01-31 23:59:59', $criteria->to->format('Y-m-d H:i:s'));
  }

  #[Test]
  public function testFromRequestWithNoQueryParametersReturnsAllNullCriteria(): void
  {
    $factory = new AuditEventExportCriteriaFactory();

    $criteria = $factory->fromRequest(Request::create('/api/audit-events/export', 'GET'));

    self::assertNull($criteria->action);
    self::assertNull($criteria->actorType);
    self::assertNull($criteria->actorId);
    self::assertNull($criteria->subjectType);
    self::assertNull($criteria->subjectId);
    self::assertNull($criteria->clientId);
    self::assertNull($criteria->tenantId);
    self::assertNull($criteria->ipHash);
    self::assertNull($criteria->from);
    self::assertNull($criteria->to);
  }

  #[Test]
  public function testFromRequestTreatsBlankStringAsAbsent(): void
  {
    $factory = new AuditEventExportCriteriaFactory();

    $criteria = $factory->fromRequest(Request::create('/api/audit-events/export', 'GET', [
      'action' => '',
      'tenantId' => '',
    ]));

    self::assertNull($criteria->action);
    self::assertNull($criteria->tenantId);
  }

  #[Test]
  public function testAppliedFilterKeysReturnsOnlyNamesOfSetFilters(): void
  {
    $factory = new AuditEventExportCriteriaFactory();

    $criteria = $factory->fromRequest(Request::create('/api/audit-events/export', 'GET', [
      'action' => 'auth.login_success',
      'tenantId' => 'tenant-1',
      'from' => '2026-01-01T00:00:00+00:00',
    ]));

    self::assertSame(['action', 'tenantId', 'from'], $factory->appliedFilterKeys($criteria));
  }

  #[Test]
  public function testAppliedFilterKeysReturnsEmptyArrayWhenNoFilterApplied(): void
  {
    $factory = new AuditEventExportCriteriaFactory();

    $criteria = $factory->fromRequest(Request::create('/api/audit-events/export', 'GET'));

    self::assertSame([], $factory->appliedFilterKeys($criteria));
  }

  #[Test]
  public function testAppliedFilterKeysNeverCarriesRawFilterValues(): void
  {
    $factory = new AuditEventExportCriteriaFactory();

    $criteria = $factory->fromRequest(Request::create('/api/audit-events/export', 'GET', [
      'actorId' => 'person-identifying-user-id',
      'subjectId' => 'person-identifying-subject-id',
    ]));

    $keys = $factory->appliedFilterKeys($criteria);

    self::assertSame(['actorId', 'subjectId'], $keys);
    self::assertNotContains('person-identifying-user-id', $keys);
    self::assertNotContains('person-identifying-subject-id', $keys);
  }
  // #endregion
}
