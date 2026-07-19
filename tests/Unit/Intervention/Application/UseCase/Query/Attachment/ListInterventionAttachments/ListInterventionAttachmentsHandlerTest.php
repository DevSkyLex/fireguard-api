<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Attachment\ListInterventionAttachments;

use DateTimeImmutable;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\{InterventionAttachmentRepositoryPort, InterventionResourceGatewayPort};
use Intervention\Application\UseCase\Query\Attachment\ListInterventionAttachments\{ListInterventionAttachmentsHandler, ListInterventionAttachmentsQuery, ListInterventionAttachmentsResult};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Intervention\Domain\Model\Attachment\InterventionAttachment;
use Intervention\Domain\ValueObject\InterventionAttachmentId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ListInterventionAttachmentsHandlerTest.
 *
 * The handler is the AUTHORITATIVE enforcer of the flat
 * `organization.interventions.read` permission (see
 * `tests/Architecture/Unit/InterventionAuthorizationEnforcementTest`).
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListInterventionAttachmentsHandler::class)]
final class ListInterventionAttachmentsHandlerTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655446000';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446001';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655446002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655446003';

  #[Test]
  public function testInvokeReturnsAttachmentsForTheIntervention(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORG_ID, 'in_progress'),
    );

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.interventions.read')
      ->willReturn(true);

    $attachment = InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString(self::ATTACHMENT_ID),
      interventionId: self::INTERVENTION_ID,
      fileName: 'evidence.jpg',
      storagePath: 'intervention/' . self::INTERVENTION_ID . '/attachments/' . self::ATTACHMENT_ID . '_evidence.jpg',
      mimeType: 'image/jpeg',
      size: 10,
      uploadedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    $attachmentRepository = $this->createStub(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findByInterventionId')->willReturn([$attachment]);

    $handler = new ListInterventionAttachmentsHandler(
      resources: $resources,
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
    );

    $result = $handler->__invoke(new ListInterventionAttachmentsQuery(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
    ));

    self::assertInstanceOf(ListInterventionAttachmentsResult::class, $result);
    self::assertCount(1, $result->attachments);
    self::assertSame(self::ATTACHMENT_ID, $result->attachments[0]['id']);
  }

  #[Test]
  public function testInvokeThrowsWhenInterventionNotFound(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(null);

    $attachmentRepository = $this->createStub(InterventionAttachmentRepositoryPort::class);

    $handler = new ListInterventionAttachmentsHandler(
      resources: $resources,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      attachmentRepository: $attachmentRepository,
    );

    $this->expectException(InterventionNotFoundException::class);

    $handler->__invoke(new ListInterventionAttachmentsQuery(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsAccessDeniedWhenUserLacksReadPermission(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORG_ID, 'in_progress'),
    );

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $attachmentRepository = $this->createMock(InterventionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('findByInterventionId');

    $handler = new ListInterventionAttachmentsHandler(
      resources: $resources,
      authorization: $authorization,
      attachmentRepository: $attachmentRepository,
    );

    $this->expectException(InterventionAccessDeniedException::class);

    $handler->__invoke(new ListInterventionAttachmentsQuery(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
    ));
  }
}
