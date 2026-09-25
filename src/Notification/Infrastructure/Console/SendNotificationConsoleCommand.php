<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Console;

use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Application\Port\Inbound\NotificationPort;
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
use function ucfirst;

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
        name: 'organization-id',
        shortcut: null,
        mode: InputOption::VALUE_REQUIRED,
        description: 'Organization to scope the notification to (optional; omit for an account-level or platform-wide notification)',
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
    $request = $this->readRequest($input, $io);

    if (null === $request) {
      return Command::FAILURE;
    }

    return $this->sendRequest($request, $io);
  }

  private function readRequest(InputInterface $input, SymfonyStyle $io): ?SendNotificationRequest
  {
    $type = $this->requiredArgument($input, $io, 'type');
    $subject = null === $type ? null : $this->requiredArgument($input, $io, 'subject');
    $body = null === $subject ? null : $this->requiredArgument($input, $io, 'body');
    if (null === $type || null === $subject || null === $body) {
      return null;
    }
    $userId = $this->nullableOption($input, 'user-id');
    $email = $this->nullableOption($input, 'email');
    $organizationId = $this->nullableOption($input, 'organization-id');

    if (!NotificationType::isValid($type)) {
      $io->warning(sprintf(
        'Unknown type "%s". Known types: %s. Proceeding anyway.',
        $type,
        implode(', ', NotificationType::all()),
      ));
    }

    $channelsRaw = $input->getOption('channels');
    $channels = $this->resolveChannels(is_string($channelsRaw) ? $channelsRaw : '');

    $error = match (true) {
      [] === $channels => sprintf(
        'No valid channel provided. Available: %s',
        implode(', ', array_map(
          static fn (NotificationChannel $c): string => $c->value,
          NotificationChannel::cases(),
        )),
      ),
      in_array(NotificationChannel::EMAIL, $channels, true) && null === $email => 'The email channel requires --email.',
      in_array(NotificationChannel::MERCURE, $channels, true) && null === $userId => 'The mercure channel requires --user-id.',
      default => null,
    };
    if (null !== $error) {
      $io->error($error);

      return null;
    }

    return new SendNotificationRequest(
      type: $type,
      subject: $subject,
      body: $body,
      channels: $channels,
      recipientUserId: $userId,
      recipientEmail: $email,
      organizationId: $organizationId,
    );
  }

  private function sendRequest(SendNotificationRequest $request, SymfonyStyle $io): int
  {
    try {
      $sent = $this->notificationPort->send($request);

      $io->success(sprintf(
        'Notification "%s" sent (ID: %s).',
        $request->type,
        $sent->id,
      ));

      return Command::SUCCESS;
    } catch (Throwable $exception) {
      $io->error(sprintf('Failed to send notification: %s', $exception->getMessage()));

      return Command::FAILURE;
    }
  }

  private function requiredArgument(InputInterface $input, SymfonyStyle $io, string $name): ?string
  {
    $raw = $input->getArgument($name);
    if (!is_string($raw) || '' === trim($raw)) {
      $io->error(sprintf('%s is required.', ucfirst($name)));

      return null;
    }

    return trim($raw);
  }

  private function nullableOption(InputInterface $input, string $name): ?string
  {
    $raw = $input->getOption($name);

    return is_string($raw) && '' !== trim($raw) ? trim($raw) : null;
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
