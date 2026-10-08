<?php

declare(strict_types=1);

namespace Tests\Support\Notification;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Notification\Application\Port\Outbound\{MercureNotificationChannelPort, NotificationPreferenceRepositoryPort, NotificationRepositoryPort, RecipientDirectoryPort};
use Notification\Application\Service\NotificationService;
use Notification\Application\UseCase\Command\Notification\SendNotification\SendNotificationHandler;
use Notification\Domain\Model\Notification\Notification;
use Notification\Infrastructure\Adapter\Channel\EmailNotificationChannelAdapter;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{LoggerPort, MailerPort, UuidGeneratorPort};
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function dirname;

use const LIBXML_NOERROR;
use const LIBXML_NOWARNING;

trait EmailPipelineTestTrait
{
  private ?Notification $storedEmailNotification = null;

  private ?Notification $mercureEmailNotification = null;

  private ?string $renderedNotificationEmail = null;

  private function emailPipeline(string $userId, string $email, string $category, string $subject, bool $resolveEmail): NotificationService
  {
    $repository = $this->createMock(NotificationRepositoryPort::class);
    $repository->expects(self::once())->method('save')
      ->willReturnCallback(function (Notification $notification): void {
        $this->storedEmailNotification = $notification;
      });
    $preferences = $this->createMock(NotificationPreferenceRepositoryPort::class);
    $preferences->expects(self::once())->method('findByUserIdAndCategory')
      ->with($userId, $category)->willReturn(null);
    $mercure = $this->createMock(MercureNotificationChannelPort::class);
    $mercure->expects(self::once())->method('publish')->with(self::isInstanceOf(Notification::class), [])
      ->willReturnCallback(function (Notification $notification, array $payload): void {
        $this->mercureEmailNotification = $notification;
      });
    $directory = $this->createMock(RecipientDirectoryPort::class);
    if ($resolveEmail) {
      $directory->expects(self::once())->method('emailForUserId')->with($userId)->willReturn($email);
    } else {
      $directory->expects(self::never())->method('emailForUserId');
    }
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');
    $generator = $this->createMock(UuidGeneratorPort::class);
    $generator->expects(self::once())->method('generate')->willReturn('550e8400-e29b-41d4-a716-446655449010');
    $mailer = $this->createMock(MailerPort::class);
    $mailer->expects(self::once())->method('send')->with([$email], $subject, self::isString())
      ->willReturnCallback(function (array $to, string $mailSubject, string $body): void {
        $this->renderedNotificationEmail = $body;
      });
    $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 3) . '/templates'), ['autoescape' => 'html']);
    $twig->addExtension(new TranslationExtension(new Translator('en')));

    return new NotificationService(new SendNotificationHandler(
      notificationRepository: $repository,
      preferenceRepository: $preferences,
      emailChannel: new EmailNotificationChannelAdapter($mailer, $twig),
      mercureChannel: $mercure,
      recipientDirectory: $directory,
      logger: $logger,
      uuidFactory: new UuidFactory($generator),
    ));
  }

  /**
   * @param array<string, mixed> $payload the source producer's persistent payload
   */
  private function assertEmailPipelinePreservesPlainText(string $type, string $body, array $payload, string $userId, string $organizationId): void
  {
    $stored = $this->storedEmailNotification;
    $mercure = $this->mercureEmailNotification;
    $rendered = $this->renderedNotificationEmail;
    self::assertInstanceOf(Notification::class, $stored);
    self::assertSame($type, $stored->type());
    self::assertSame($body, $stored->body());
    self::assertSame($payload, $stored->payload());
    self::assertSame($userId, $stored->recipientUserId());
    self::assertSame($organizationId, $stored->organizationId());
    self::assertSame($stored, $mercure);
    self::assertInstanceOf(Notification::class, $mercure);
    self::assertSame($body, $mercure->body());
    self::assertIsString($rendered);

    $document = new DOMDocument();
    self::assertTrue($document->loadHTML($rendered, LIBXML_NOERROR | LIBXML_NOWARNING));
    $xpath = new DOMXPath($document);
    $bodyCells = $xpath->query('//td[@class="prose"]');
    self::assertNotFalse($bodyCells);
    self::assertCount(1, $bodyCells);
    $bodyCell = $bodyCells->item(0);
    self::assertInstanceOf(DOMElement::class, $bodyCell);
    self::assertSame($body, $bodyCell->textContent);
    $descendants = $xpath->query('.//*', $bodyCell);
    self::assertNotFalse($descendants);
    self::assertCount(0, $descendants);
  }
}
