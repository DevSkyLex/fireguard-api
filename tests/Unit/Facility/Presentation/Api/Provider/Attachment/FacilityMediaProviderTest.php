<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\{Get, GetCollection};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\UseCase\Query\Attachment\ListFacilityAttachments\ListFacilityAttachmentsResult;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput;
use Facility\Presentation\Api\Provider\Attachment\FacilityMediaProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

#[CoversClass(FacilityMediaProvider::class)]
final class FacilityMediaProviderTest extends TestCase
{
  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testProvideListsAttachmentsWhenAuthorized(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($facility);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ListFacilityAttachmentsResult([
      ['id' => 'attachment-id', 'fileName' => 'photo.jpg', 'mimeType' => 'image/jpeg', 'size' => 5, 'label' => null, 'uploadedAt' => '2026-01-01T00:00:00+00:00'],
    ]));

    $result = new FacilityMediaProvider($entityManager, $queryBus, $authorization, $security)
      ->provide(new GetCollection(), ['facilityId' => self::FACILITY_ID]);

    self::assertIsArray($result);
    self::assertCount(1, $result);
    self::assertInstanceOf(FacilityAttachmentOutput::class, $result[0]);
    self::assertSame('attachment-id', $result[0]->id);
  }

  #[Test]
  public function testProvideListThrowsAccessDeniedWithoutPermission(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($facility);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $queryBus = $this->createStub(QueryBusPort::class);

    $this->expectException(AccessDeniedHttpException::class);

    new FacilityMediaProvider($entityManager, $queryBus, $authorization, $security)
      ->provide(new GetCollection(), ['facilityId' => self::FACILITY_ID]);
  }

  #[Test]
  public function testProvideGetOneReturnsOutput(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $attachment = new FacilityAttachmentRecord();
    $attachment->id = 'attachment-id';
    $attachment->facility = $facility;
    $attachment->fileName = 'photo.jpg';
    $attachment->mimeType = 'image/jpeg';
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

    $result = new FacilityMediaProvider($entityManager, $queryBus, $authorization, $security)
      ->provide(new Get(), ['id' => 'attachment-id']);

    self::assertInstanceOf(FacilityAttachmentOutput::class, $result);
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

    new FacilityMediaProvider($entityManager, $queryBus, $authorization, $security)
      ->provide(new Get(), ['id' => 'missing-id']);
  }
}
