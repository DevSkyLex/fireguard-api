<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

use function is_file;
use function is_string;
use function str_contains;

/**
 * Subscriber EmbedBrandMarkSubscriber.
 *
 * Embeds the Fireguard mark into any outgoing email whose HTML references it as
 * `cid:fireguard-mark` (the shared `notification/email/layout.html.twig` header).
 *
 * A CID attachment is the only way to show an image reliably in every mail client:
 * remote images are blocked by default in many of them and Gmail strips `data:` URIs.
 * Listening on the mailer event rather than in one adapter covers every send path
 * (`MailerAdapter` and the OTP notifier build their messages independently).
 *
 * @category EventSubscriber
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EmbedBrandMarkSubscriber implements EventSubscriberInterface
{
  // #region Constants
  /**
   * Constant CONTENT_ID
   *
   * Content-ID the layout references as `cid:fireguard-mark`.
   *
   * @access public
   */
  public const string CONTENT_ID = 'fireguard-mark';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the path of the PNG that ships next to the email layout.
   *
   * @access public
   *
   * @param string $markPath absolute path of the brand mark PNG
   *
   * @return void
   */
  public function __construct(
    #[Autowire('%kernel.project_dir%/templates/notification/email/fireguard-mark.png')]
    private string $markPath,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method onMessage
   *
   * Embeds the mark when the HTML body references it. Messenger-backed mailers dispatch
   * the event twice, first for a throwaway clone (queued) then for the message the
   * transport really sends; only the second one is acted on. A missing file never blocks
   * the send: the wordmark next to the image stays readable without it.
   *
   * @access public
   *
   * @param MessageEvent $event the mailer message event
   *
   * @return void
   */
  public function onMessage(MessageEvent $event): void
  {
    $message = $event->getMessage();

    if ($event->isQueued() || !$message instanceof Email) {
      return;
    }

    $html = $message->getHtmlBody();

    if (!is_string(value: $html) || !str_contains(haystack: $html, needle: 'cid:' . self::CONTENT_ID)) {
      return;
    }

    if (!is_file(filename: $this->markPath)) {
      return;
    }

    $message->embedFromPath(path: $this->markPath, name: self::CONTENT_ID, contentType: 'image/png');
  }

  /**
   * Method getSubscribedEvents
   *
   * Registers the listener on the mailer message event.
   *
   * @access public
   *
   * @static
   *
   * @return array<string, string> mailer event mapped to its listener
   */
  public static function getSubscribedEvents(): array
  {
    return [
      MessageEvent::class => 'onMessage',
    ];
  }
  // #endregion
}
