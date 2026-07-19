<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\{Get, GetCollection};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Application\UseCase\Query\Attachment\ListInspectionAttachments\ListInspectionAttachmentsResult;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionAttachmentRecord, InspectionRecord, NonConformityRecord};
use Inspection\Presentation\Api\Dto\Output\Attachment\InspectionAttachmentOutput;
use Inspection\Presentation\Api\Provider\Attachment\InspectionMediaProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

#[CoversClass(InspectionMediaProvider::class)]
final class InspectionMediaProviderTest extends TestCase
{
  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string NON_CONFORMITY_ID = '550e8400-e29b-41d4-a716-446655440004';

  #[Test]
  public function testProvideListsInspectionLevelAttachmentsWhenAuthorized(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION_ID;
    $inspection->organization = $organization;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($inspection);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ListInspectionAttachmentsResult([
      ['id' => 'attachment-id', 'nonConformityId' => null, 'fileName' => 'report.pdf', 'mimeType' => 'application/pdf', 'size' => 5, 'label' => null, 'uploadedAt' => '2026-01-01T00:00:00+00:00'],
    ]));

    $result = new InspectionMediaProvider($entityManager, $queryBus, $authorization, $security)
      ->provide(new GetCollection(), ['inspectionId' => self::INSPECTION_ID]);

    self::assertIsArray($result);
    self::assertCount(1, $result);
    self::assertInstanceOf(InspectionAttachmentOutput::class, $result[0]);
    self::assertNull($result[0]->nonConformityId);
  }

  #[Test]
  public function testProvideListsNonConformityAttachmentsWhenAuthorized(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION_ID;
    $inspection->organization = $organization;
    $nonConformity = new NonConformityRecord();
    $nonConformity->id = self::NON_CONFORMITY_ID;
    $nonConformity->inspection = $inspection;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($nonConformity);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ListInspectionAttachmentsResult([
      ['id' => 'attachment-id', 'nonConformityId' => self::NON_CONFORMITY_ID, 'fileName' => 'photo.jpg', 'mimeType' => 'image/jpeg', 'size' => 5, 'label' => null, 'uploadedAt' => '2026-01-01T00:00:00+00:00'],
    ]));

    $result = new InspectionMediaProvider($entityManager, $queryBus, $authorization, $security)
      ->provide(new GetCollection(), ['nonConformityId' => self::NON_CONFORMITY_ID]);

    self::assertIsArray($result);
    self::assertSame(self::NON_CONFORMITY_ID, $result[0]->nonConformityId);
  }

  #[Test]
  public function testProvideListThrowsAccessDeniedWithoutPermission(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION_ID;
    $inspection->organization = $organization;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($inspection);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $queryBus = $this->createStub(QueryBusPort::class);

    $this->expectException(AccessDeniedHttpException::class);

    new InspectionMediaProvider($entityManager, $queryBus, $authorization, $security)
      ->provide(new GetCollection(), ['inspectionId' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testProvideGetOneReturnsOutput(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION_ID;
    $inspection->organization = $organization;
    $attachment = new InspectionAttachmentRecord();
    $attachment->id = 'attachment-id';
    $attachment->inspection = $inspection;
    $attachment->fileName = 'report.pdf';
    $attachment->mimeType = 'application/pdf';
    $attachment->size = 5;
    $attachment->uploadedAt = new DateTimeImmutable();

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($attachment);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $queryBus = $this->createStub(QueryBusPort::class);

    $result = new InspectionMediaProvider($entityManager, $queryBus, $authorization, $security)
      ->provide(new Get(), ['id' => 'attachment-id']);

    self::assertInstanceOf(InspectionAttachmentOutput::class, $result);
    self::assertSame('attachment-id', $result->id);
  }

  #[Test]
  public function testProvideGetOneThrowsNotFoundWhenMissing(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $security = $this->createStub(Security::class);
    $queryBus = $this->createStub(QueryBusPort::class);

    $this->expectException(NotFoundHttpException::class);

    new InspectionMediaProvider($entityManager, $queryBus, $authorization, $security)
      ->provide(new Get(), ['id' => 'missing-id']);
  }
}
