<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\UseCase\Command\Attachment\SetPrimaryFacilityAttachment\{SetPrimaryFacilityAttachmentCommand, SetPrimaryFacilityAttachmentResult};
use Facility\Domain\Exception\{FacilityAttachmentNotFloorPlanException, FacilityAttachmentNotFoundException};
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use Facility\Presentation\Api\Processor\Attachment\SetPrimaryFacilityAttachmentProcessor;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, ConflictHttpException, NotFoundHttpException};

/**
 * Test SetPrimaryFacilityAttachmentProcessorTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SetPrimaryFacilityAttachmentProcessor::class)]
final class SetPrimaryFacilityAttachmentProcessorTest extends TestCase
{
  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655448001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655448002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655448003';

  #[Test]
  public function testProcessPromotesTheAttachmentWhenAuthorized(): void
  {
    $record = $this->attachmentRecord();

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($record);
    $entityManager->expects(self::once())->method('refresh')->with($record);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $command): bool => $command instanceof SetPrimaryFacilityAttachmentCommand
          && self::ATTACHMENT_ID === $command->attachmentId,
      ))
      ->willReturn(new SetPrimaryFacilityAttachmentResult(
        attachmentId: self::ATTACHMENT_ID,
        facilityId: self::FACILITY_ID,
        fileName: 'plan.png',
        mimeType: 'image/png',
        size: 2048,
        label: null,
        kind: 'floor_plan',
        isPrimaryPlan: true,
        imageWidth: 800,
        imageHeight: 600,
      ));

    $processor = new SetPrimaryFacilityAttachmentProcessor(
      $entityManager,
      $commandBus,
      $authorization,
      $this->userSecurity(),
    );

    $output = $processor->process(null, new Post(), ['id' => self::ATTACHMENT_ID]);

    self::assertSame(self::ATTACHMENT_ID, $output->id);
  }

  #[Test]
  public function testProcessMapsTheDomainRefusalTo409(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->attachmentRecord());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      FacilityAttachmentNotFloorPlanException::forAttachment(self::ATTACHMENT_ID),
    );

    $processor = new SetPrimaryFacilityAttachmentProcessor(
      $entityManager,
      $commandBus,
      $authorization,
      $this->userSecurity(),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(null, new Post(), ['id' => self::ATTACHMENT_ID]);
  }

  #[Test]
  public function testProcessMapsAMissingAttachmentTo404(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->attachmentRecord());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      FacilityAttachmentNotFoundException::withId(self::ATTACHMENT_ID),
    );

    $processor = new SetPrimaryFacilityAttachmentProcessor(
      $entityManager,
      $commandBus,
      $authorization,
      $this->userSecurity(),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Post(), ['id' => self::ATTACHMENT_ID]);
  }

  #[Test]
  public function testProcessRejectsWhenMissingPermission(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->attachmentRecord());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new SetPrimaryFacilityAttachmentProcessor(
      $entityManager,
      $commandBus,
      $authorization,
      $this->userSecurity(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), ['id' => self::ATTACHMENT_ID]);
  }

  #[Test]
  public function testProcessReportsACallerOutsideTheOrganizationAsAttachmentNotFound(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->attachmentRecord());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new SetPrimaryFacilityAttachmentProcessor(
      $entityManager,
      $commandBus,
      $authorization,
      $this->userSecurity(),
    );

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Attachment not found.');

    $processor->process(null, new Post(), ['id' => self::ATTACHMENT_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenTheAttachmentRecordIsMissing(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);

    $processor = new SetPrimaryFacilityAttachmentProcessor(
      $entityManager,
      $this->createStub(CommandBusPort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->userSecurity(),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Post(), ['id' => self::ATTACHMENT_ID]);
  }

  private function attachmentRecord(): FacilityAttachmentRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;

    $record = new FacilityAttachmentRecord();
    $record->id = self::ATTACHMENT_ID;
    $record->facility = $facility;
    $record->kind = 'floor_plan';
    $record->mimeType = 'image/png';
    $record->fileName = 'plan.png';
    $record->storagePath = 'facility/' . self::FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_plan.png';
    $record->size = 2048;
    $record->uploadedAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return $record;
  }

  private function userSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return $security;
  }
}
