<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Gateways\AuthorizeNetGateway;
use InvalidArgumentException;

class GatewayService
{
    /** @var array<string, class-string<PaymentGateway>> */
    private static array $drivers = [
        'authorizenet' => AuthorizeNetGateway::class,
    ];

    /**
     * Resolve a gateway driver by slug.
     *
     * @throws InvalidArgumentException
     */
    public static function driver(string $slug): PaymentGateway
    {
        if (! isset(static::$drivers[$slug])) {
            throw new InvalidArgumentException("Unknown payment gateway: {$slug}");
        }

        return new static::$drivers[$slug];
    }

    /**
     * Register a custom gateway driver (useful for plugins/modules).
     *
     * @param  class-string<PaymentGateway>  $class
     */
    public static function register(string $slug, string $class): void
    {
        static::$drivers[$slug] = $class;
    }

    /**
     * Return all registered gateway slugs.
     *
     * @return string[]
     */
    public static function available(): array
    {
        return array_keys(static::$drivers);
    }
}
