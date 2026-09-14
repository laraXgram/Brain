<?php

declare(strict_types=1);

namespace LaraGram\Brain\Support;

class Npm
{
    /** @var array<int, string> */
    public const FIRST_PARTY_SCOPES = [
        '@laraxgram',
    ];

    /** @var array<int, string> */
    public const FIRST_PARTY_PACKAGES = [
        'laragram-precognition',
        'laragram-precognition-alpine',
        'laragram-precognition-react',
        'laragram-precognition-vue',
        'laragram-vite-plugin',
    ];

    public static function isFirstPartyPackage(string $npmName): bool
    {
        if (collect(self::FIRST_PARTY_SCOPES)->contains(fn (string $scope): bool => str_starts_with($npmName, $scope.'/'))) {
            return true;
        }

        return in_array($npmName, self::FIRST_PARTY_PACKAGES, true);
    }
}
