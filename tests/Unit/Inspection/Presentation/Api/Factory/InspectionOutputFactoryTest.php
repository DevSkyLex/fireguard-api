<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Factory;

use DateTimeImmutable;
use Inspection\Application\UseCase\Command\Inspection\CreateInspection\CreateInspectionResult;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Presentation\Api\Dto\Output\Inspection\{InspectionOutput, InspectorOutput};
use Inspection\Presentation\Api\Factory\InspectionOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use User\Application\Contract\User\UserView;

/**
 * Test InspectionOutputFactoryTest.
 *
 * The factory maps the Application-owned inspector snapshot and keeps the
 * stored inspector name when that optional profile is unavailable.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionOutputFactory::class)]
final class InspectionOutputFactoryTest extends TestCase
{
  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440801';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440802';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440803';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440804';

  #[Test]
  public function testItMapsTheInspectorProfileFromTheApplicationSnapshot(): void
  {
    $output = new InspectionOutputFactory()->fromGetResult($this->getResult($this->userView()));

    $inspector = $this->inspector($output);

    self::assertSame(self::INSPECTION_ID, $output->id);
    self::assertSame('Ada', $inspector->firstName);
    self::assertSame('Lovelace', $inspector->lastName);
    self::assertSame('Ada Lovelace', $inspector->displayName);
    self::assertSame('/avatars/ada.png', $inspector->avatarUrl);
  }

  #[Test]
  public function testItKeepsTheStoredNameWhenTheOptionalProfileIsUnavailable(): void
  {
    $output = new InspectionOutputFactory()->fromGetResult($this->getResult());

    $inspector = $this->inspector($output);

    self::assertSame('Inspector', $inspector->displayName);
    self::assertNull($inspector->firstName);
    self::assertNull($inspector->avatarUrl);
  }

  #[Test]
  public function testItKeepsTheStoredNameWhenTheProfileNameIsBlank(): void
  {
    $output = new InspectionOutputFactory()->fromGetResult($this->getResult($this->userView('', '')));

    self::assertSame('Inspector', $this->inspector($output)->displayName);
  }

  #[Test]
  public function testItDoesNotReuseAnInspectorProfileFromAnEarlierProjection(): void
  {
    $factory = new InspectionOutputFactory();
    $first = $factory->fromGetResult($this->getResult($this->userView()));
    $second = $factory->fromGetResult($this->getResult());
    self::assertSame('Ada Lovelace', $this->inspector($first)->displayName);
    self::assertSame('Inspector', $this->inspector($second)->displayName);
    self::assertNull($this->inspector($second)->avatarUrl);
  }

  #[Test]
  public function testItPreservesTheExternalInspectorWithoutUsingAnAccountProfile(): void
  {
    $output = new InspectionOutputFactory()->fromCreateResult(new CreateInspectionResult(
      inspectionId: self::INSPECTION_ID,
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIPMENT_ID,
      facilityId: null,
      result: 'pass',
      status: 'draft',
      performedAt: '2026-01-01T08:00:00+00:00',
      inspectorType: 'external',
      inspectorName: 'Bureau Veritas',
      inspectorUserId: null,
      inspectorOrganizationName: 'Bureau Veritas SA',
      checklistId: null,
      notes: null,
      signature: null,
      createdAt: new DateTimeImmutable('2026-01-01T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
      inspectorProfile: $this->userView(),
    ));

    $inspector = $this->inspector($output);

    self::assertSame('external', $inspector->type);
    self::assertSame('Bureau Veritas', $inspector->displayName);
    self::assertSame('Bureau Veritas SA', $inspector->organizationName);
    self::assertNull($inspector->firstName);
    self::assertNull($inspector->avatarUrl);
    self::assertSame(0, $output->nonConformitiesCount);
  }

  private function inspector(InspectionOutput $output): InspectorOutput
  {
    $inspector = $output->inspector;
    self::assertInstanceOf(InspectorOutput::class, $inspector);

    return $inspector;
  }

  private function getResult(?UserView $profile = null): GetInspectionResult
  {
    return new GetInspectionResult(
      inspectionId: self::INSPECTION_ID,
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIPMENT_ID,
      facilityId: null,
      result: 'pass',
      status: 'draft',
      performedAt: '2026-01-01T08:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'Inspector',
      inspectorUserId: self::USER_ID,
      inspectorOrganizationName: null,
      checklistId: null,
      notes: null,
      signature: null,
      nonConformitiesCount: 2,
      createdAt: new DateTimeImmutable('2026-01-01T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
      inspectorProfile: $profile,
    );
  }

  private function userView(string $firstName = 'Ada', string $lastName = 'Lovelace'): UserView
  {
    return new UserView(
      id: self::USER_ID,
      username: 'ada',
      email: 'ada@example.com',
      firstName: $firstName,
      lastName: $lastName,
      avatarUrl: '/avatars/ada.png',
      status: 'active',
      emailVerified: true,
      tenantId: null,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      lastLoginAt: null,
      canLogin: true,
    );
  }
}
