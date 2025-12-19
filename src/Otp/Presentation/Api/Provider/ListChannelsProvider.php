<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Otp\Domain\ValueObject\OtpChannel;
use Otp\Presentation\Api\Dto\ChannelOutput;

/**
 * Provider ListChannelsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<ChannelOutput>
 */
final class ListChannelsProvider implements ProviderInterface
{
    // #region Methods
    /**
     * Method provide
     * {@inheritDoc}
     *
     * @param Operation            $operation    the operation
     * @param array<string, mixed> $uriVariables the URI variables
     * @param array<string, mixed> $context      the context
     *
     * @return list<ChannelOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $channels = [];

        foreach (OtpChannel::cases() as $channel) {
            $output = new ChannelOutput();
            $output->value = $channel->value;
            $output->label = $channel->getLabel();
            $output->requiresDelivery = $channel->requiresDelivery();

            $channels[] = $output;
        }

        return $channels;
    }
    // #endregion
}
