<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Infrastructure\Adapter\Channel;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Notification\Domain\Model\Notification\{Notification, NotificationTarget};
use Notification\Domain\ValueObject\NotificationId;
use Notification\Infrastructure\Adapter\Channel\EmailNotificationChannelAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\MailerPort;
use Shared\Domain\ValueObject\Email;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\{ArrayLoader, FilesystemLoader};

use function dirname;

use const LIBXML_NOERROR;
use const LIBXML_NOWARNING;

#[CoversClass(EmailNotificationChannelAdapter::class)]
final class EmailNotificationChannelAdapterTest extends TestCase
{
  #[Test]
  public function testSendRendersDefaultTemplateWhenNoTemplateProvided(): void
  {
    $twig = new Environment(new ArrayLoader([
      'notification/email/default.html.twig' => '<h1>{{ subject }}</h1><div>{% if bodyIsHtml %}{{ body|raw }}{% else %}{{ body }}{% endif %}</div>',
    ]));

    /** @var MailerPort&MockObject $mailer */
    $mailer = $this->createMock(MailerPort::class);
    $mailer->expects(self::once())
      ->method('send')
      ->with(
        ['member@example.com'],
        'Invitation to join Fireguard HQ',
        '<h1>Invitation to join Fireguard HQ</h1><div>&lt;p&gt;Open invitation details.&lt;/p&gt;</div>',
        [],
        [],
        [],
      );

    $adapter = new EmailNotificationChannelAdapter(
      mailer: $mailer,
      twig: $twig,
    );

    $adapter->send($this->createNotification());
  }

  #[Test]
  public function testSendRendersCustomTemplateWithContext(): void
  {
    $twig = new Environment(new ArrayLoader([
      'notification/email/default.html.twig' => '<h1>{{ subject }}</h1><div>{% if bodyIsHtml %}{{ body|raw }}{% else %}{{ body }}{% endif %}</div>',
      'notification/email/organization_invitation.html.twig' => '{{ organizationName }}|{{ token }}|{{ expiresAt }}|{{ subject }}',
    ]));

    /** @var MailerPort&MockObject $mailer */
    $mailer = $this->createMock(MailerPort::class);
    $mailer->expects(self::once())
      ->method('send')
      ->with(
        ['member@example.com'],
        'Invitation to join Fireguard HQ',
        'Fireguard HQ|ABC123|2026-02-20T10:00:00+00:00|Invitation to join Fireguard HQ',
        [],
        [],
        [],
      );

    $adapter = new EmailNotificationChannelAdapter(
      mailer: $mailer,
      twig: $twig,
    );

    $adapter->send(
      notification: $this->createNotification(),
      channelPayload: [
        'template' => 'notification/email/organization_invitation.html.twig',
        'context' => [
          'organizationName' => 'Fireguard HQ',
          'token' => 'ABC123',
          'expiresAt' => '2026-02-20T10:00:00+00:00',
        ],
      ],
    );
  }

  #[Test]
  public function testSendSkipsWhenNotificationHasNoRecipientEmail(): void
  {
    $twig = new Environment(new ArrayLoader([
      'notification/email/default.html.twig' => '<h1>{{ subject }}</h1><div>{% if bodyIsHtml %}{{ body|raw }}{% else %}{{ body }}{% endif %}</div>',
    ]));

    /** @var MailerPort&MockObject $mailer */
    $mailer = $this->createMock(MailerPort::class);
    $mailer->expects(self::never())
      ->method('send');

    $adapter = new EmailNotificationChannelAdapter(
      mailer: $mailer,
      twig: $twig,
    );

    $adapter->send($this->createNotification(null));
  }

  #[Test]
  public function testSendPrefersTheChannelBodyOverrideAndSkipsNonStringContextKeys(): void
  {
    $twig = new Environment(new ArrayLoader([
      'notification/email/default.html.twig' => '<h1>{{ subject }}</h1><div>{% if bodyIsHtml %}{{ body|raw }}{% else %}{{ body }}{% endif %}</div><span>{{ cta|default("none") }}</span>',
    ]));

    /** @var MailerPort&MockObject $mailer */
    $mailer = $this->createMock(MailerPort::class);
    $mailer->expects(self::once())
      ->method('send')
      ->with(
        ['member@example.com'],
        'Invitation to join Fireguard HQ',
        '<h1>Invitation to join Fireguard HQ</h1><div>&lt;p&gt;Channel-specific body.&lt;/p&gt;</div><span>Accept</span>',
        [],
        [],
        [],
      );

    $adapter = new EmailNotificationChannelAdapter(
      mailer: $mailer,
      twig: $twig,
    );

    $adapter->send(
      notification: $this->createNotification(),
      channelPayload: [
        'body' => '<p>Channel-specific body.</p>',
        'context' => [
          0 => 'dropped-numeric-key',
          'cta' => 'Accept',
        ],
      ],
    );
  }

  #[Test]
  public function testSendKeepsTheAggregateBodyWhenTheChannelBodyIsBlank(): void
  {
    $twig = new Environment(new ArrayLoader([
      'notification/email/default.html.twig' => '<h1>{{ subject }}</h1><div>{% if bodyIsHtml %}{{ body|raw }}{% else %}{{ body }}{% endif %}</div>',
    ]));

    /** @var MailerPort&MockObject $mailer */
    $mailer = $this->createMock(MailerPort::class);
    $mailer->expects(self::once())
      ->method('send')
      ->with(
        ['member@example.com'],
        'Invitation to join Fireguard HQ',
        '<h1>Invitation to join Fireguard HQ</h1><div>&lt;p&gt;Open invitation details.&lt;/p&gt;</div>',
        [],
        [],
        [],
      );

    $adapter = new EmailNotificationChannelAdapter(
      mailer: $mailer,
      twig: $twig,
    );

    $adapter->send(
      notification: $this->createNotification(),
      channelPayload: ['body' => '   ', 'context' => 'not-an-array'],
    );
  }

  /**
   * @param array<string, mixed> $channelPayload
   */
  #[Test]
  #[DataProvider('textOnlyPayloads')]
  public function testRealDefaultTemplateEscapesBodiesWithoutStrictHtmlOptIn(array $channelPayload): void
  {
    $body = '<a href="https://untrusted.invalid">Review & "confirm"</a>';
    $rendered = null;
    $mailer = $this->createMock(MailerPort::class);
    $mailer->expects(self::once())->method('send')
      ->willReturnCallback(static function (array $to, string $subject, string $html) use (&$rendered): void {
        $rendered = $html;
      });
    $adapter = new EmailNotificationChannelAdapter($mailer, $this->realTemplates());
    $adapter->send($this->createNotification(body: $body), $channelPayload);

    self::assertIsString($rendered);
    $document = new DOMDocument();
    self::assertTrue($document->loadHTML($rendered, LIBXML_NOERROR | LIBXML_NOWARNING));
    $xpath = new DOMXPath($document);
    $cells = $xpath->query('//td[@class="prose"]');
    self::assertNotFalse($cells);
    self::assertCount(1, $cells);
    $cell = $cells->item(0);
    self::assertInstanceOf(DOMElement::class, $cell);
    self::assertSame($body, $cell->textContent);
    $elements = $xpath->query('.//*', $cell);
    self::assertNotFalse($elements);
    self::assertCount(0, $elements);
  }

  #[Test]
  public function testRealDefaultTemplatePreservesExplicitTrustedHtmlDespiteContextOverride(): void
  {
    $body = '<p>Trusted <strong>message &amp; details</strong></p>';
    $rendered = null;
    $mailer = $this->createMock(MailerPort::class);
    $mailer->expects(self::once())->method('send')
      ->willReturnCallback(static function (array $to, string $subject, string $html) use (&$rendered): void {
        $rendered = $html;
      });
    $adapter = new EmailNotificationChannelAdapter($mailer, $this->realTemplates());
    $adapter->send($this->createNotification(body: $body), [
      'bodyIsHtml' => true,
      'context' => ['bodyIsHtml' => false],
    ]);

    self::assertIsString($rendered);
    self::assertStringContainsString('<td class="prose">' . $body . '</td>', $rendered);
  }

  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function textOnlyPayloads(): iterable
  {
    yield 'default' => [[]];
    yield 'false' => [['bodyIsHtml' => false]];
    yield 'string true' => [['bodyIsHtml' => 'true']];
    yield 'integer one' => [['bodyIsHtml' => 1]];
    yield 'null' => [['bodyIsHtml' => null]];
    yield 'context cannot enable HTML' => [['context' => ['bodyIsHtml' => true]]];
    yield 'false cannot be overridden by context' => [['bodyIsHtml' => false, 'context' => ['bodyIsHtml' => true]]];
  }

  private function realTemplates(): Environment
  {
    $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 6) . '/templates'), ['autoescape' => 'html']);
    $twig->addExtension(new TranslationExtension(new Translator('en')));

    return $twig;
  }

  private function createNotification(?string $recipientEmail = 'member@example.com', string $body = '<p>Open invitation details.</p>'): Notification
  {
    return Notification::create(
      id: new NotificationId('550e8400-e29b-41d4-a716-446655442300'),
      type: 'organization.invitation',
      subject: 'Invitation to join Fireguard HQ',
      body: $body,
      channels: ['email'],
      payload: ['organizationName' => 'Fireguard HQ'],
      target: new NotificationTarget(
        recipientUserId: null,
        recipientEmail: null !== $recipientEmail ? new Email($recipientEmail) : null,
        organizationId: null,
      ),
    );
  }
}
