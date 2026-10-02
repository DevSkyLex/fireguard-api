<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\EventSubscriber;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\EventSubscriber\EmbedBrandMarkSubscriber;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\{Email, RawMessage};

use function dirname;

/**
 * Test EmbedBrandMarkSubscriberTest.
 *
 * @category EventSubscriber Tests
 */
#[CoversClass(className: EmbedBrandMarkSubscriber::class)]
final class EmbedBrandMarkSubscriberTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testItListensToTheMailerMessageEvent(): void
  {
    self::assertSame(
      [MessageEvent::class => 'onMessage'],
      EmbedBrandMarkSubscriber::getSubscribedEvents(),
    );
  }

  #[Test]
  public function testItEmbedsTheMarkWhenTheHtmlReferencesItsContentId(): void
  {
    $email = $this->email('<img src="cid:fireguard-mark" alt="">');

    $this->subscriber()->onMessage($this->event($email));

    $mime = $email->toString();
    self::assertStringContainsString('Content-Type: image/png', $mime);
    self::assertStringContainsString('Content-Disposition: inline', $mime);
    self::assertStringNotContainsString('src="cid:fireguard-mark"', $mime, 'Symfony rewrites the CID to the generated Content-ID.');
  }

  #[Test]
  public function testItLeavesTheMessageAloneWhenTheHtmlDoesNotReferenceTheMark(): void
  {
    $email = $this->email('<p>Plain body</p>');

    $this->subscriber()->onMessage($this->event($email));

    self::assertStringNotContainsString('image/png', $email->toString());
  }

  #[Test]
  public function testItIgnoresTheQueuedDispatchOfMessengerBackedMailers(): void
  {
    $email = $this->email('<img src="cid:fireguard-mark" alt="">');

    $this->subscriber()->onMessage($this->event($email, queued: true));

    self::assertStringNotContainsString('image/png', $email->toString());
  }

  #[Test]
  public function testItIgnoresMessagesThatAreNotEmails(): void
  {
    $event = new MessageEvent(
      message: new RawMessage('raw'),
      envelope: new Envelope(new Address('from@example.test'), [new Address('to@example.test')]),
      transport: 'null',
    );

    $this->subscriber()->onMessage($event);

    self::assertSame('raw', $event->getMessage()->toString());
  }

  #[Test]
  public function testAMissingFileNeverBlocksTheSend(): void
  {
    $email = $this->email('<img src="cid:fireguard-mark" alt="">');

    new EmbedBrandMarkSubscriber(markPath: '/nonexistent/fireguard-mark.png')->onMessage($this->event($email));

    self::assertStringNotContainsString('image/png', $email->toString());
  }

  #[Test]
  public function testTheShippedMarkIsWhereTheContainerWiringExpectsIt(): void
  {
    self::assertFileExists($this->markPath());
  }
  // #endregion

  // #region Helpers
  private function markPath(): string
  {
    return dirname(__DIR__, 5) . '/templates/notification/email/fireguard-mark.png';
  }

  private function subscriber(): EmbedBrandMarkSubscriber
  {
    return new EmbedBrandMarkSubscriber(markPath: $this->markPath());
  }

  private function email(string $html): Email
  {
    return new Email()
      ->from('from@example.test')
      ->to('to@example.test')
      ->subject('Subject')
      ->html($html);
  }

  private function event(Email $email, bool $queued = false): MessageEvent
  {
    return new MessageEvent(
      message: $email,
      envelope: Envelope::create($email),
      transport: 'null',
      queued: $queued,
    );
  }
  // #endregion
}
