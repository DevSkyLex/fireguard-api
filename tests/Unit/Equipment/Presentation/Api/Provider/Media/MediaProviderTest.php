<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Media;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentAttachmentRecord, EquipmentRecord};
use Equipment\Presentation\Api\Dto\Output\Equipment\AttachmentOutput;
use Equipment\Presentation\Api\Provider\Media\MediaProvider;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Throwable;

/**
 * Test MediaProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MediaProvider::class)]
final class MediaProviderTest extends TestCase
{
  use \Tests\Unit\Equipment\Support\EquipmentHttpFailures;

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testProvideReturnsTheAttachmentOutput(): void
  {
    $record = $this->record();

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($record);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $output = $this->provider($entityManager, $authorization, $this->securityWithUser())
      ->provide(new Get(), ['id' => 'attachment-id']);

    self::assertInstanceOf(AttachmentOutput::class, $output);
    self::assertSame('attachment-id', $output->id);
    self::assertSame(self::EQUIPMENT_ID, $output->equipmentId);
    self::assertSame('report.pdf', $output->fileName);
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenTheIdUriVariableIsMissing(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->provider(
      $this->createStub(EntityManagerInterface::class),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->createStub(Security::class),
    )->provide(new Get(), []);
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenTheRecordDoesNotExist(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);

    $this->expectException(NotFoundHttpException::class);

    $this->provider(
      $entityManager,
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->createStub(Security::class),
    )->provide(new Get(), ['id' => 'missing-id']);
  }

  #[Test]
  public function testProvideThrowsAccessDeniedWithoutTheReadPermission(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->record());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(AccessDeniedHttpException::class);

    $this->provider($entityManager, $authorization, $this->securityWithUser())
      ->provide(new Get(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenOrganizationIsOutsideCallerScope(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->record());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(NotFoundHttpException::class);

    $this->provider($entityManager, $authorization, $this->securityWithUser())
      ->provide(new Get(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function testProvideThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->record());

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $this->expectException(AccessDeniedHttpException::class);

    $this->provider($entityManager, $this->createStub(OrganizationAuthorizationPort::class), $security)
      ->provide(new Get(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function testOutputThrowsNotFoundWhenTheRecordHasNoEquipment(): void
  {
    $record = $this->record();
    $record->equipment = null;

    $this->expectException(NotFoundHttpException::class);

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($record);
    $this->provider($entityManager, $this->createStub(OrganizationAuthorizationPort::class), $this->securityWithUser())->provide(new Get(), ['id' => 'attachment-id']);
  }

  private function record(): EquipmentAttachmentRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;
    $equipment->organizationId = $organization->id;

    $record = new EquipmentAttachmentRecord();
    $record->id = 'attachment-id';
    $record->equipment = $equipment;
    $record->fileName = 'report.pdf';
    $record->storagePath = 'equipment/x/attachments/report.pdf';
    $record->mimeType = 'application/pdf';
    $record->size = 42;
    $record->label = 'Report';
    $record->uploadedAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return $record;
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return $security;
  }

  private function provider(EntityManagerInterface $entityManager, OrganizationAuthorizationPort $authorization, Security $security): MediaProvider
  {
    $access = new \Equipment\Application\Service\EquipmentMediaAccess($authorization, $this->createStub(\Intervention\Application\Port\Inbound\InterventionResourcesPort::class));
    $handler = new \Equipment\Application\UseCase\Query\Media\GetEquipmentMedia\GetEquipmentMediaHandler(new \Equipment\Infrastructure\Persistence\Doctrine\Repository\EquipmentMediaReadRepository($entityManager), $access);
    $queries = $this->createStub(\Shared\Application\Port\Inbound\QueryBusPort::class);
    $queries->method('ask')->willReturnCallback(function (\Equipment\Application\UseCase\Query\Media\GetEquipmentMedia\GetEquipmentMediaQuery $query) use ($handler): object {
      try {
        return $handler($query);
      } catch (Throwable $failure) {
        throw $this->httpFailure($failure);
      }
    });

    return new MediaProvider($queries, $security);
  }
}
