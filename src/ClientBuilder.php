<?php

namespace Softonic\OAuth2\Guzzle\Middleware;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use League\OAuth2\Client\Provider\AbstractProvider as OAuth2Provider;
use Psr\Cache\CacheItemPoolInterface as Cache;

class ClientBuilder
{
    public static function build(
        OAuth2Provider $oauthProvider,
        $tokenOptions,
        Cache $cache,
        $guzzleOptions = []
    ) {
        $cacheHandler = new AccessTokenCacheHandler($cache);

        $stack = isset($guzzleOptions['handler']) ? $guzzleOptions['handler'] : HandlerStack::create();

        $stack->setHandler(new CurlHandler());

        $stack = static::addHeaderMiddlewareToStack(
            $stack,
            $oauthProvider,
            $tokenOptions,
            $cacheHandler
        );
        $stack = static::addRetryMiddlewareToStack(
            $stack,
            $oauthProvider,
            $tokenOptions,
            $cacheHandler
        );

        $defaultOptions = [
            'handler' => $stack,
        ];
        $guzzleOptions = static::mergeOptions($defaultOptions, $guzzleOptions);

        return new Client($guzzleOptions);
    }

    protected static function addHeaderMiddlewareToStack(
        HandlerStack $stack,
        OAuth2Provider $oauthProvider,
        array $tokenOptions,
        AccessTokenCacheHandler $cacheHandler
    ) {
        $addAuthorizationHeader = new AddAuthorizationHeader(
            $oauthProvider,
            $tokenOptions,
            $cacheHandler
        );

        $stack->push(Middleware::mapRequest($addAuthorizationHeader));

        return $stack;
    }

    protected static function addRetryMiddlewareToStack(
        HandlerStack $stack,
        OAuth2Provider $oauthProvider,
        array $tokenOptions,
        AccessTokenCacheHandler $cacheHandler
    ) {
        $retryOnAuthorizationError = new RetryOnAuthorizationError(
            $oauthProvider,
            $tokenOptions,
            $cacheHandler
        );

        $stack->push(Middleware::retry($retryOnAuthorizationError));

        return $stack;
    }

    protected static function mergeOptions(array $defaultOptions, array $options = []): array
    {
        return array_merge($options, $defaultOptions);
    }
}
