<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Factory;

use DateTimeImmutable;
use Inspection\Application\UseCase\Command\Inspection\CreateInspection\CreateInspectionResult;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Presentation\Api\Dto\Output\Inspection\{InspectionOutput, InspectorOutput};
use Inspection\Presentation\Api\Factory\InspectionOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\GetUserResult;

/**
 * Test InspectionOutputFactoryTest.
 *
 * The inspector block is enriched from the User module through the query
 * bus, so the factory has to survive that lookup failing — an unreachable
 * User module must degrade the inspector name, never break the inspection
 * response.
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
  public function testItEnrichesTheInspectorFromTheUserModule(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetUserResult($this->userView()));

    $output = new InspectionOutputFactory($queryBus)->fromGetResult($this->getResult());

    $inspector = $this->inspector($output);

    self::assertSame(self::INSPECTION_ID, $output->id);
    self::assertSame('Ada', $inspector->firstName);
    self::assertSame('Lovelace', $inspector->lastName);
    self::assertSame('Ada Lovelace', $inspector->displayName);
    self::assertSame('/avatars/ada.png', $inspector->avatarUrl);
  }

  #[Test]
  public function testItKeepsTheStoredNameWhenTheUserLookupFails(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('User module unreachable.'));

    $output = new InspectionOutputFactory($queryBus)->fromGetResult($this->getResult());

    $inspector = $this->inspector($output);

    self::assertSame('Inspector', $inspector->displayName);
    self::assertNull($inspector->firstName);
    self::assertNull($inspector->avatarUrl);
  }

  #[Test]
  public function testItKeepsTheStoredNameWhenTheUserIsUnknown(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetUserResult(null));

    $output = new InspectionOutputFactory($queryBus)->fromGetResult($this->getResult());

    self::assertSame('Inspector', $this->inspector($output)->displayName);
  }

  #[Test]
  public function testItAsksTheUserModuleOnlyOncePerInspector(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())->method('ask')->willReturn(new GetUserResult($this->userView()));

    $factory = new InspectionOutputFactory($queryBus);
    $factory->fromGetResult($this->getResult());
    $second = $factory->fromGetResult($this->getResult());

    self::assertSame('Ada Lovelace', $this->inspector($second)->displayName);
  }

  #[Test]
  public function testItSkipsTheLookupForAnExternalInspector(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $output = new InspectionOutputFactory($queryBus)->fromCreateResult(new CreateInspectionResult(
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
    ));

    $inspector = $this->inspector($output);

    self::assertSame('external', $inspector->type);
    self::assertSame('Bureau Veritas', $inspector->displayName);
    self::assertSame('Bureau Veritas SA', $inspector->organizationName);
    self::assertSame(0, $output->nonConformitiesCount);
  }

  private function inspector(InspectionOutput $output): InspectorOutput
  {
    $inspector = $output->inspector;
    self::assertInstanceOf(InspectorOutput::class, $inspector);

    return $inspector;
  }

  private function getResult(): GetInspectionResult
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
    );
  }

  private function userView(): UserView
  {
    return new UserView(
      id: self::USER_ID,
      username: 'ada',
      email: 'ada@example.com',
      firstName: 'Ada',
      lastName: 'Lovelace',
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
