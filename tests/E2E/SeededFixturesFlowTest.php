<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\DataFixtures\FacilityFixtures;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function array_key_last;
use function is_array;
use function is_int;
use function is_string;
use function json_encode;
use function round;
use function sprintf;

final class SeededFixturesFlowTest extends OAuth2WebTestCase
{
  private const string ADMIN_EMAIL = 'admin@fireguard.local';

  private const string ADMIN_PASSWORD = 'Admin123!';

  private const string INSPECTOR_EMAIL = 'test@fireguard.local';

  private const string INSPECTOR_PASSWORD = 'Test123!';

  private const string TREND_FROM = '2026-03-01T00:00:00Z';

  private const string TREND_TO = '2026-04-02T23:59:59Z';

  /**
   * Memoized result of {@see self::seedShiftDays()}.
   */
  private ?int $seedShiftDays = null;

  #[\PHPUnit\Framework\Attributes\Test]
  public function testSeededAdminCanReadDashboardTrendAndSeededTotals(): void
  {
    $client = static::createClientWithFixtures();

    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $dashboardData = $this->requestDashboard($client, $token, $this->dashboardPeriodQuery());
    /** @var array<string, mixed> $overview */
    $overview = is_array($dashboardData['overview'] ?? null) ? $dashboardData['overview'] : [];
    $membersWidget = $this->normalizeWidget($overview['members'] ?? null);
    $rolesWidget = $this->normalizeWidget($overview['roles'] ?? null);
    $facilitiesWidget = $this->normalizeWidget($overview['facilities'] ?? null);
    $equipmentWidget = $this->normalizeWidget($overview['equipment'] ?? null);
    $inspectionsWidget = $this->normalizeWidget($overview['inspections'] ?? null);
    $nonConformitiesWidget = $this->normalizeWidget($overview['nonConformities'] ?? null);

    self::assertSame(51, $this->summaryValue($membersWidget, 'total'));
    self::assertSame(3, $this->summaryValue($rolesWidget, 'total'));
    self::assertSame(57, $this->summaryValue($facilitiesWidget, 'total'));
    self::assertSame(98, $this->summaryValue($equipmentWidget, 'total'));
    self::assertSame(221, $this->summaryValue($inspectionsWidget, 'total'));
    self::assertSame(112, $this->summaryValue($nonConformitiesWidget, 'total'));
    self::assertSame('total', $this->primaryKey($facilitiesWidget));
    self::assertSame(57, $this->primaryValue($facilitiesWidget));
    self::assertSame('operational', $this->primaryKey($equipmentWidget));
    self::assertSame(81, $this->primaryValue($equipmentWidget));
    self::assertSame('closed', $this->primaryKey($inspectionsWidget));
    self::assertSame(150, $this->primaryValue($inspectionsWidget));
    self::assertSame('open', $this->primaryKey($nonConformitiesWidget));
    self::assertSame(42, $this->primaryValue($nonConformitiesWidget));

    $filteredDashboardData = $this->requestDashboard(
      $client,
      $token,
      $this->dashboardPeriodQuery() . '&equipmentStatus=in_stock&inspectionStatus=submitted&nonConformityStatus=in_progress',
    );
    /** @var array<string, mixed> $filteredOverview */
    $filteredOverview = is_array($filteredDashboardData['overview'] ?? null) ? $filteredDashboardData['overview'] : [];
    $filteredEquipmentWidget = $this->normalizeWidget($filteredOverview['equipment'] ?? null);
    $filteredInspectionsWidget = $this->normalizeWidget($filteredOverview['inspections'] ?? null);
    $filteredNonConformitiesWidget = $this->normalizeWidget($filteredOverview['nonConformities'] ?? null);

    self::assertSame('inStock', $this->primaryKey($filteredEquipmentWidget));
    self::assertSame(1, $this->primaryValue($filteredEquipmentWidget));
    self::assertSame('submitted', $this->primaryKey($filteredInspectionsWidget));
    self::assertSame(70, $this->primaryValue($filteredInspectionsWidget));
    self::assertSame('inProgress', $this->primaryKey($filteredNonConformitiesWidget));
    self::assertSame(24, $this->primaryValue($filteredNonConformitiesWidget));

    $comparisonDashboardData = $this->requestDashboard($client, $token, $this->dashboardPeriodQuery(compare: true));
    $health = $comparisonDashboardData['health'] ?? [];
    $healthMetrics = is_array($health) ? ($health['metrics'] ?? []) : [];
    $comparison = $comparisonDashboardData['comparison'] ?? [];
    $comparisonMetrics = is_array($comparison) ? ($comparison['metrics'] ?? []) : [];
    $comparisonHealth = is_array($comparison) ? ($comparison['health'] ?? []) : [];
    $comparisonHealthMetrics = is_array($comparisonHealth) ? ($comparisonHealth['metrics'] ?? []) : [];

    $healthMetric = $this->findMetricByField($healthMetrics, 'key', 'memberActivationRate');
    self::assertNotNull($healthMetric);
    self::assertSame('percent', $healthMetric['unit'] ?? null);
    $healthMetricMax = $healthMetric['max'] ?? null;
    self::assertTrue(100 === $healthMetricMax || 100.0 === $healthMetricMax);

    $comparisonMetric = $this->findMetricByField($comparisonMetrics, 'key', 'inspections');
    self::assertNotNull($comparisonMetric);
    self::assertSame('Inspections', $comparisonMetric['label'] ?? null);
    self::assertSame(13, $comparisonMetric['current'] ?? null);
    self::assertSame(6, $comparisonMetric['previous'] ?? null);
    self::assertSame('+7', $comparisonMetric['value'] ?? null);
    self::assertSame('up', $comparisonMetric['direction'] ?? null);

    $facilitiesComparisonMetric = $this->findMetricByField($comparisonMetrics, 'key', 'facilities');
    self::assertNotNull($facilitiesComparisonMetric);
    self::assertSame('Facilities', $facilitiesComparisonMetric['label'] ?? null);
    self::assertSame(57, $facilitiesComparisonMetric['current'] ?? null);
    self::assertSame(0, $facilitiesComparisonMetric['previous'] ?? null);
    self::assertSame('+57', $facilitiesComparisonMetric['value'] ?? null);
    self::assertSame('up', $facilitiesComparisonMetric['direction'] ?? null);

    $membersComparisonMetric = $this->findMetricByField($comparisonMetrics, 'key', 'members');
    self::assertNotNull($membersComparisonMetric);
    self::assertSame('Members', $membersComparisonMetric['label'] ?? null);
    self::assertSame(1, $membersComparisonMetric['current'] ?? null);
    self::assertSame(50, $membersComparisonMetric['previous'] ?? null);
    self::assertSame('-49', $membersComparisonMetric['value'] ?? null);
    self::assertSame('down', $membersComparisonMetric['direction'] ?? null);

    $equipmentComparisonMetric = $this->findMetricByField($comparisonMetrics, 'key', 'equipment');
    self::assertNotNull($equipmentComparisonMetric);
    self::assertSame('Equipment', $equipmentComparisonMetric['label'] ?? null);
    self::assertSame(34, $equipmentComparisonMetric['current'] ?? null);
    self::assertSame(0, $equipmentComparisonMetric['previous'] ?? null);
    self::assertSame('+34', $equipmentComparisonMetric['value'] ?? null);
    self::assertSame('up', $equipmentComparisonMetric['direction'] ?? null);

    $nonConformitiesOpenedMetric = $this->findMetricByField($comparisonMetrics, 'key', 'nonConformitiesOpened');
    self::assertNotNull($nonConformitiesOpenedMetric);

    $nonConformitiesResolvedMetric = $this->findMetricByField($comparisonMetrics, 'key', 'nonConformitiesResolved');
    self::assertNotNull($nonConformitiesResolvedMetric);

    $comparisonHealthMetric = $this->findMetricByField($comparisonHealthMetrics, 'key', 'inspectionCompletionRate');
    self::assertNotNull($comparisonHealthMetric);
    self::assertSame('percent', $comparisonHealthMetric['unit'] ?? null);
    $comparisonHealthMetricMax = $comparisonHealthMetric['max'] ?? null;
    self::assertTrue(100 === $comparisonHealthMetricMax || 100.0 === $comparisonHealthMetricMax);
    self::assertArrayHasKey('direction', $comparisonHealthMetric);

    $this->assertTrendSeries($client, $token, 'facilities-created', 'facilities_created', 57, 7, ['2026-03-03' => 1, '2026-03-30' => 2]);
    $this->assertTrendSeries($client, $token, 'equipment-created', 'equipment_created', 34, 4, ['2026-03-06' => 1, '2026-03-30' => 3]);
    $this->assertTrendSeries($client, $token, 'inspections', 'inspections_performed', 13, 13, ['2026-03-11' => 1, '2026-04-02' => 1]);
    $this->assertTrendSeries($client, $token, 'non-conformities-opened', 'non_conformities_opened', 9, 7, ['2026-03-11' => 2, '2026-03-20' => 2]);
    $this->assertTrendSeries($client, $token, 'non-conformities-resolved', 'non_conformities_resolved', 6, 6, ['2026-03-02' => 1, '2026-04-01' => 1]);
  }

  #[\PHPUnit\Framework\Attributes\Test]
  public function testSeededInspectorIsDeniedDashboardButCanReadOperationalCollections(): void
  {
    $client = static::createClientWithFixtures();

    $token = $this->loginAndGetUserAccessToken($client, self::INSPECTOR_EMAIL, self::INSPECTOR_PASSWORD);

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/dashboard',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    self::assertSame(
      Response::HTTP_FORBIDDEN,
      $client->getResponse()->getStatusCode(),
      'Seeded inspector should be denied the aggregate dashboard. Response: ' . $client->getResponse()->getContent(),
    );

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/facilities?itemsPerPage=50',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'Seeded inspector should read facilities.');
    $facilityData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $facilityMembers = $this->getCollectionMembers($facilityData);
    // 56 non-archived facilities (57 seeded minus the archived annex, which
    // the collection hides unless the caller asks for it), capped at the
    // requested page size of 50.
    self::assertCount(50, $facilityMembers);
    self::assertTrue($this->collectionContainsFieldValue($facilityMembers, 'name', 'Server Room'));

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/equipment?itemsPerPage=50',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'Seeded inspector should read equipment.');
    $equipmentData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $equipmentMembers = $this->getCollectionMembers($equipmentData);
    self::assertCount(50, $equipmentMembers);
    self::assertTrue($this->collectionContainsFieldValue($equipmentMembers, 'serialNumber', 'SEED-EXT-001'));
    self::assertTrue($this->collectionContainsFieldValue($equipmentMembers, 'serialNumber', 'SEED-HYD-001'));

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/inspections?itemsPerPage=50',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'Seeded inspector should read inspections.');
    $inspectionData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $inspectionMembers = $this->getCollectionMembers($inspectionData);
    self::assertCount(50, $inspectionMembers);
    self::assertTrue($this->collectionContainsFieldValue($inspectionMembers, 'result', 'pass'));
    self::assertTrue($this->collectionContainsFieldValue($inspectionMembers, 'result', 'fail'));
    self::assertTrue($this->collectionContainsFieldValue($inspectionMembers, 'result', 'partial'));

    $this->assertTrendSeries($client, $token, 'facilities-created', 'facilities_created', 57, 7, ['2026-03-30' => 2]);
    $this->assertTrendSeries($client, $token, 'equipment-created', 'equipment_created', 34, 4, ['2026-03-30' => 3]);
    $this->assertTrendSeries($client, $token, 'inspections', 'inspections_performed', 13, 13, ['2026-03-11' => 1]);
    $this->assertTrendSeries($client, $token, 'non-conformities-opened', 'non_conformities_opened', 9, 7, ['2026-03-11' => 2]);
    $this->assertTrendSeries($client, $token, 'non-conformities-resolved', 'non_conformities_resolved', 6, 6, ['2026-04-01' => 1]);
  }

  /**
   * @return array<string, mixed>
   */
  private function requestDashboard(KernelBrowser $client, string $token, string $query = ''): array
  {
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/dashboard' . $query,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    self::assertSame(
      Response::HTTP_OK,
      $client->getResponse()->getStatusCode(),
      'Seeded admin should read the seeded dashboard. Response: ' . $client->getResponse()->getContent(),
    );

    return $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
  }

  private function loginAndGetUserAccessToken(KernelBrowser $client, string $email, string $password): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => $email,
        'password' => $password,
      ]) ?: '',
    );

    $response = $client->getResponse();
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'User login should succeed for seeded fixture flow. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    self::assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }

  private function dashboardPeriodQuery(bool $compare = false): string
  {
    // The seed fixtures place their dates on the live timeline via SeedTimeline
    // (anchored on now, not a fixed calendar). Shift the window boundaries by
    // the same amount so the period always covers the same relative slice of
    // the seeded data, making the assertions deterministic regardless of the
    // run date.
    $from = $this->seededAt(self::TREND_FROM)->format('Y-m-d\TH:i:s\Z');
    $to = $this->seededAt(self::TREND_TO)->format('Y-m-d\TH:i:s\Z');

    return '?from=' . $from . '&to=' . $to . '&compare=' . ($compare ? 'true' : 'false') . '&granularity=day&timezone=UTC';
  }

  /**
   * Formats a seed-anchored literal date (YYYY-MM-DD) as it lands on the
   * seeded timeline, for asserting specific trend buckets deterministically.
   */
  private function shiftedBucket(string $literalDate): string
  {
    return $this->seededAt($literalDate . 'T00:00:00+00:00')->format('Y-m-d');
  }

  /**
   * Shifts a seed-anchored literal onto the timeline this database carries.
   */
  private function seededAt(string $literal): DateTimeImmutable
  {
    return new DateTimeImmutable($literal)->modify(sprintf('%+d days', $this->seedShiftDays()));
  }

  /**
   * The whole-day offset SeedTimeline applied when this database was seeded.
   *
   * SeedTimeline resolves that offset against the clock *at fixture-load
   * time*, and the baseline is seeded once (`make test-db`) and then reused.
   * Calling SeedTimeline::at() from the test process therefore answers for
   * today rather than for the day the rows were written: the two diverge by a
   * day for every day that passes — and again mid-day, since the anchor sits
   * at 10:00 UTC. Every exact count below would then be measured over a window
   * sitting a day off the data it is meant to cover.
   *
   * So read the offset back off the data instead of recomputing it, from the
   * one seeded facility whose authored literal the fixture publishes.
   */
  private function seedShiftDays(): int
  {
    if (null !== $this->seedShiftDays) {
      return $this->seedShiftDays;
    }

    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

    $site = $entityManager->find(FacilityRecord::class, FacilityFixtures::SITE_ID);
    self::assertInstanceOf(
      FacilityRecord::class,
      $site,
      'Seeded facility ' . FacilityFixtures::SITE_ID . ' is missing. Run `make test-db` to seed the baseline.',
    );

    // Rounded rather than floored: a shift that crosses a DST boundary lands a
    // whole number of days apart in wall-clock terms but an hour off in
    // absolute ones, and the stored value need not be hydrated as UTC.
    $authoredAt = new DateTimeImmutable(FacilityFixtures::SITE_CREATED_AT);

    return $this->seedShiftDays = (int) round(($site->createdAt->getTimestamp() - $authoredAt->getTimestamp()) / 86400);
  }

  /**
   * @param array<string, int> $expectedBuckets
   */
  private function assertTrendSeries(
    KernelBrowser $client,
    string $token,
    string $slug,
    string $expectedMetric,
    int $expectedTotal,
    int $minimumNonZeroBuckets,
    array $expectedBuckets = [],
  ): void {
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/dashboard/trends/' . $slug . $this->dashboardPeriodQuery(),
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    self::assertSame(
      Response::HTTP_OK,
      $client->getResponse()->getStatusCode(),
      'Seeded admin should read the seeded ' . $slug . ' trend. Response: ' . $client->getResponse()->getContent(),
    );

    $trendData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $trendSummary = $this->normalizeWidget($trendData['summary'] ?? null);
    $series = $this->normalizeSeries($trendData['series'] ?? []);

    self::assertSame($expectedMetric, $trendData['metric'] ?? null);
    self::assertSame($expectedTotal, $trendSummary['total'] ?? null);
    self::assertSame($expectedTotal, $this->sumSeries($series));
    self::assertGreaterThanOrEqual($minimumNonZeroBuckets, $this->countNonZeroBuckets($series));
    self::assertSame($this->shiftedBucket('2026-03-01'), $series[0]['bucket'] ?? null);
    self::assertSame($this->shiftedBucket('2026-04-02'), $series[array_key_last($series)]['bucket'] ?? null);

    foreach ($expectedBuckets as $bucket => $value) {
      self::assertSame($value, $this->bucketValue($series, $this->shiftedBucket($bucket)));
    }
  }

  /**
   * @return array<string, mixed>
   */
  private function normalizeWidget(mixed $widget): array
  {
    if (!is_array($widget)) {
      return [];
    }

    $normalized = [];
    foreach ($widget as $key => $value) {
      if (is_string($key)) {
        $normalized[$key] = $value;
      }
    }

    return $normalized;
  }

  /**
   * @param array<string, mixed> $widget
   */
  private function summaryValue(array $widget, string $key): ?int
  {
    $summary = $widget['summary'] ?? null;
    if (!is_array($summary)) {
      return null;
    }

    foreach ($summary as $entry) {
      if (
        is_array($entry)
        && ($entry['key'] ?? null) === $key
        && isset($entry['value'])
        && is_int($entry['value'])
      ) {
        return $entry['value'];
      }
    }

    return null;
  }

  /**
   * @param array<string, mixed> $widget
   */
  private function primaryKey(array $widget): ?string
  {
    $primary = $widget['primary'] ?? null;
    if (!is_array($primary)) {
      return null;
    }

    $key = $primary['key'] ?? null;

    return is_string($key) ? $key : null;
  }

  /**
   * @param array<string, mixed> $widget
   */
  private function primaryValue(array $widget): ?int
  {
    $primary = $widget['primary'] ?? null;
    if (!is_array($primary)) {
      return null;
    }

    $value = $primary['value'] ?? null;

    return is_int($value) ? $value : null;
  }

  private function sumSeries(mixed $series): int
  {
    if (!is_array($series)) {
      return 0;
    }

    $total = 0;
    foreach ($series as $point) {
      if (!is_array($point)) {
        continue;
      }

      $value = $point['value'] ?? null;
      if (is_int($value)) {
        $total += $value;
      }
    }

    return $total;
  }

  private function countNonZeroBuckets(mixed $series): int
  {
    if (!is_array($series)) {
      return 0;
    }

    $count = 0;
    foreach ($series as $point) {
      if (!is_array($point)) {
        continue;
      }

      $value = $point['value'] ?? null;
      if (is_int($value) && $value > 0) {
        ++$count;
      }
    }

    return $count;
  }

  /**
   * @return list<array{bucket: string, value: int}>
   */
  private function normalizeSeries(mixed $series): array
  {
    if (!is_array($series)) {
      return [];
    }

    $normalized = [];
    foreach ($series as $point) {
      if (!is_array($point)) {
        continue;
      }

      $bucket = $point['bucket'] ?? null;
      $value = $point['value'] ?? null;
      if (!is_string($bucket) || !is_int($value)) {
        continue;
      }

      $normalized[] = [
        'bucket' => $bucket,
        'value' => $value,
      ];
    }

    return $normalized;
  }

  /**
   * @param array<string, mixed> $data
   *
   * @return list<array<string, mixed>>
   */
  private function getCollectionMembers(array $data): array
  {
    $members = $data['member'] ?? [];
    if (!is_array($members)) {
      return [];
    }

    $result = [];
    foreach ($members as $member) {
      if (!is_array($member)) {
        continue;
      }

      $normalized = [];
      foreach ($member as $entryKey => $entryValue) {
        if (is_string($entryKey)) {
          $normalized[$entryKey] = $entryValue;
        }
      }

      $result[] = $normalized;
    }

    return $result;
  }

  /**
   * @param list<array<string, mixed>> $collection
   */
  private function collectionContainsFieldValue(array $collection, string $field, string $expectedValue): bool
  {
    foreach ($collection as $item) {
      if (($item[$field] ?? null) === $expectedValue) {
        return true;
      }
    }

    return false;
  }

  /**
   * @param list<array{bucket: string, value: int}> $series
   */
  private function bucketValue(array $series, string $expectedBucket): ?int
  {
    foreach ($series as $point) {
      if ($point['bucket'] === $expectedBucket) {
        return $point['value'];
      }
    }

    return null;
  }

  /**
   * @return array<string, mixed>|null
   */
  private function findMetricByField(mixed $metrics, string $field, string $expectedValue): ?array
  {
    if (!is_array($metrics)) {
      return null;
    }

    foreach ($metrics as $metric) {
      if (!is_array($metric) || ($metric[$field] ?? null) !== $expectedValue) {
        continue;
      }

      $normalized = [];
      foreach ($metric as $key => $value) {
        if (is_string($key)) {
          $normalized[$key] = $value;
        }
      }

      return $normalized;
    }

    return null;
  }
}
