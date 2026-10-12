<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventSubscriber;

use Shared\Application\Port\Outbound\ProductConfigPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

use function is_file;
use function is_string;
use function str_contains;

/**
 * Subscriber EmbedBrandMarkSubscriber.
 *
 * Embeds the configured product mark into any outgoing email whose HTML references
 * its configured Content-ID (the shared `notification/email/layout.html.twig` header).
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
  // #region Constructor
  /**
   * Method __construct
   *
   * Shares the same brand configuration as the email layout.
   *
   * @access public
   *
   * @param ProductConfigPort $product the server-owned product configuration
   *
   * @return void
   */
  public function __construct(
    private ProductConfigPort $product,
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
    $brand = $this->product->emailBrand();

    if (!is_string(value: $html) || !str_contains(haystack: $html, needle: 'cid:' . $brand->contentId)) {
      return;
    }

    if (!is_file(filename: $brand->markPath)) {
      return;
    }

    $message->embedFromPath(path: $brand->markPath, name: $brand->contentId, contentType: 'image/png');
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
