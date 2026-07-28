<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Query\Attachment\DownloadMessageAttachment\{DownloadMessageAttachmentQuery, DownloadMessageAttachmentResult};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingAttachmentNotFoundException};
use Messaging\Presentation\Api\Controller\DownloadMessagingAttachmentController;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Infrastructure\Exception\FileStorageException;
use Shared\Presentation\Api\Attachment\AttachmentDownloadResponder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Throwable;

use function str_contains;

/**
 * Test DownloadMessagingAttachmentControllerTest.
 *
 * @category Controller Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DownloadMessagingAttachmentController::class)]
final class DownloadMessagingAttachmentControllerTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441700';

  private const string ATTACHMENT_ID = 'attachment-1';

  #[Test]
  public function testItThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $controller = new DownloadMessagingAttachmentController(
      $this->createStub(QueryBusPort::class),
      $security,
      new AttachmentDownloadResponder(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $controller(new Request());
  }

  #[Test]
  public function testItThrowsWhenTheIdAttributeIsMissing(): void
  {
    $controller = new DownloadMessagingAttachmentController(
      $this->createStub(QueryBusPort::class),
      $this->securityWithUser(),
      new AttachmentDownloadResponder(),
    );

    $this->expectException(NotFoundHttpException::class);

    $controller(new Request());
  }

  #[Test]
  public function testItServesTheStoredBytesAsADownload(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (DownloadMessageAttachmentQuery $query): bool => self::USER_ID === $query->userId
        && self::ATTACHMENT_ID === $query->attachmentId))
      ->willReturn(new DownloadMessageAttachmentResult('raw-bytes', 'report.pdf', 'application/pdf', 9));

    $controller = new DownloadMessagingAttachmentController(
      $queryBus,
      $this->securityWithUser(),
      new AttachmentDownloadResponder(),
    );

    $response = $controller($this->request());

    self::assertSame(200, $response->getStatusCode());
    self::assertSame('raw-bytes', $response->getContent());
    self::assertSame('application/pdf', $response->headers->get('Content-Type'));
    self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    self::assertTrue(str_contains((string) $response->headers->get('Content-Disposition'), 'attachment'));
  }

  #[Test]
  public function testItMapsMissingStoredBytesToNotFound(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Attachment content not found.');

    $this->invokeWithFailure(FileStorageException::readFailed('/var/storage/attachment-1'));
  }

  #[Test]
  public function testItUnwrapsAStorageFailureFromThePreviousChain(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->invokeWithFailure(new RuntimeException('handler failed', 0, FileStorageException::readFailed('/var/storage/attachment-1')));
  }

  #[Test]
  public function testItDefersOtherFailuresToTheMessagingMapper(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->invokeWithFailure(MessagingAttachmentNotFoundException::withId(self::ATTACHMENT_ID));
  }

  #[Test]
  public function testItMapsAnAccessFailureToForbidden(): void
  {
    $this->expectException(AccessDeniedHttpException::class);

    $this->invokeWithFailure(new MessagingAccessDeniedException('Not a participant.'));
  }

  private function invokeWithFailure(Throwable $failure): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($failure);

    $controller = new DownloadMessagingAttachmentController(
      $queryBus,
      $this->securityWithUser(),
      new AttachmentDownloadResponder(),
    );

    $controller($this->request());
  }

  private function request(): Request
  {
    $request = new Request();
    $request->attributes->set('id', self::ATTACHMENT_ID);

    return $request;
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }
}
