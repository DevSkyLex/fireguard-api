<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Console;

use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function sprintf;
use function trim;

/**
 * Command SendNotificationConsoleCommand.
 *
 * @category Console Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(
  name: 'app:notification:send',
  description: 'Send a notification to a user',
  aliases: ['notification:send'],
)]
final class SendNotificationConsoleCommand extends Command
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NotificationPort $notificationPort the notification port
   */
  public function __construct(
    private readonly NotificationPort $notificationPort,
  ) {
    parent::__construct();
  }
  // #endregion

  // #region Methods
  /**
   * Method configure.
   * {@inheritDoc}
   *
   * @since 1.0.0
   */
  protected function configure(): void
  {
    $knownTypes = implode(', ', NotificationType::all());
    $availableChannels = implode(', ', array_map(
      static fn (NotificationChannel $c): string => $c->value,
      NotificationChannel::cases(),
    ));

    $this
      ->addArgument(
        name: 'type',
        mode: InputArgument::REQUIRED,
        description: sprintf('Notification type. Known types: %s', $knownTypes),
      )
      ->addArgument(
        name: 'subject',
        mode: InputArgument::REQUIRED,
        description: 'Subject line of the notification',
      )
      ->addArgument(
        name: 'body',
        mode: InputArgument::REQUIRED,
        description: 'Body of the notification (plain text or HTML)',
      )
      ->addOption(
        name: 'user-id',
        shortcut: 'u',
        mode: InputOption::VALUE_REQUIRED,
        description: 'Recipient user ID (required for the mercure channel)',
      )
      ->addOption(
        name: 'email',
        shortcut: null,
        mode: InputOption::VALUE_REQUIRED,
        description: 'Recipient e-mail address (required for the email channel)',
      )
      ->addOption(
        name: 'channels',
        shortcut: 'c',
        mode: InputOption::VALUE_REQUIRED,
        description: sprintf('Comma-separated delivery channels. Available: %s', $availableChannels),
        default: NotificationChannel::EMAIL->value,
      )
      ->setHelp(
        <<<'HELP'
The <info>%command.name%</info> command sends a notification to a user.

Send via e-mail only:
  <info>php %command.full_name% organization.invitation "You're invited" "<p>Join us!</p>" --email=user@example.com</info>

Send via both channels:
  <info>php %command.full_name% system.announcement "Maintenance" "Scheduled maintenance tonight." --user-id=<uuid> --email=user@example.com --channels=email,mercure</info>

HELP
      );
  }

  /**
   * Method execute.
   * {@inheritDoc}
   *
   * @since 1.0.0
   *
   * @param InputInterface $input the input
   * @param OutputInterface $output the output
   *
   * @return int the exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);

    $typeRaw = $input->getArgument('type');
    $subjectRaw = $input->getArgument('subject');
    $bodyRaw = $input->getArgument('body');
    $userIdRaw = $input->getOption('user-id');
    $emailRaw = $input->getOption('email');
    $channelsRaw = $input->getOption('channels');

    if (!is_string($typeRaw) || '' === trim($typeRaw)) {
      $io->error('Type is required.');

      return Command::FAILURE;
    }

    if (!is_string($subjectRaw) || '' === trim($subjectRaw)) {
      $io->error('Subject is required.');

      return Command::FAILURE;
    }

    if (!is_string($bodyRaw) || '' === trim($bodyRaw)) {
      $io->error('Body is required.');

      return Command::FAILURE;
    }

    $type = trim($typeRaw);
    $subject = trim($subjectRaw);
    $body = trim($bodyRaw);
    $userId = is_string($userIdRaw) && '' !== trim($userIdRaw) ? trim($userIdRaw) : null;
    $email = is_string($emailRaw) && '' !== trim($emailRaw) ? trim($emailRaw) : null;

    if (!NotificationType::isValid($type)) {
      $io->warning(sprintf(
        'Unknown type "%s". Known types: %s. Proceeding anyway.',
        $type,
        implode(', ', NotificationType::all()),
      ));
    }

    $channels = $this->resolveChannels(is_string($channelsRaw) ? $channelsRaw : '');

    if ([] === $channels) {
      $io->error(sprintf(
        'No valid channel provided. Available: %s',
        implode(', ', array_map(
          static fn (NotificationChannel $c): string => $c->value,
          NotificationChannel::cases(),
        )),
      ));

      return Command::FAILURE;
    }

    if (in_array(NotificationChannel::EMAIL, $channels, true) && null === $email) {
      $io->error('The email channel requires --email.');

      return Command::FAILURE;
    }

    if (in_array(NotificationChannel::MERCURE, $channels, true) && null === $userId) {
      $io->error('The mercure channel requires --user-id.');

      return Command::FAILURE;
    }

    try {
      $sent = $this->notificationPort->send(new SendNotificationRequest(
        type: $type,
        subject: $subject,
        body: $body,
        channels: $channels,
        recipientUserId: $userId,
        recipientEmail: $email,
      ));

      $io->success(sprintf(
        'Notification "%s" sent (ID: %s).',
        $type,
        $sent->id,
      ));

      return Command::SUCCESS;
    } catch (Throwable $exception) {
      $io->error(sprintf('Failed to send notification: %s', $exception->getMessage()));

      return Command::FAILURE;
    }
  }

  /**
   * Method resolveChannels.
   *
   * Parses a comma-separated channel string and returns only valid
   * NotificationChannel enum cases.
   *
   * @since 1.0.0
   *
   * @param string $raw comma-separated channel names
   *
   * @return list<NotificationChannel> the resolved channels
   */
  private function resolveChannels(string $raw): array
  {
    $validValues = array_map(
      static fn (NotificationChannel $c): string => $c->value,
      NotificationChannel::cases(),
    );

    $parsed = array_values(array_filter(
      array_map(
        static fn (string $part): string => trim($part),
        explode(',', $raw),
      ),
      static fn (string $part): bool => in_array($part, $validValues, true),
    ));

    return array_map(
      static fn (string $value): NotificationChannel => NotificationChannel::from($value),
      $parsed,
    );
  }
  // #endregion
}
