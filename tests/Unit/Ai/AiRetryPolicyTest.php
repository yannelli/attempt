<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\NoSuchToolException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Yannelli\Attempt\Ai\AiRetryPolicy;

function requestException(int $status, array $headers = []): RequestException
{
    return new RequestException(
        new Response(new Psr7Response($status, $headers, '{}'))
    );
}

it('retries rate limited exceptions', function () {
    expect(AiRetryPolicy::shouldRetry(RateLimitedException::forProvider('openai')))->toBeTrue();
});

it('retries provider overloaded exceptions', function () {
    expect(AiRetryPolicy::shouldRetry(ProviderOverloadedException::forProvider('openai')))->toBeTrue();
});

it('does not retry insufficient credits exceptions', function () {
    expect(AiRetryPolicy::shouldRetry(InsufficientCreditsException::forProvider('openai')))->toBeFalse();
});

it('does not retry unknown tool exceptions', function () {
    expect(AiRetryPolicy::shouldRetry(new NoSuchToolException('No such tool.')))->toBeFalse();
});

it('does not retry generic ai exceptions', function () {
    expect(AiRetryPolicy::shouldRetry(new AiException('Malformed response.')))->toBeFalse();
});

it('retries connection exceptions', function () {
    expect(AiRetryPolicy::shouldRetry(new ConnectionException('Timed out.')))->toBeTrue();
});

it('retries transient http statuses', function (int $status) {
    expect(AiRetryPolicy::shouldRetry(requestException($status)))->toBeTrue();
})->with([408, 429, 500, 502, 503, 504]);

it('does not retry permanent http statuses', function (int $status) {
    expect(AiRetryPolicy::shouldRetry(requestException($status)))->toBeFalse();
})->with([400, 401, 403, 404, 422]);

it('does not retry unrelated exceptions', function () {
    expect(AiRetryPolicy::shouldRetry(new RuntimeException('Boom.')))->toBeFalse();
});

it('extracts retry-after seconds from the exception chain', function () {
    $exception = RateLimitedException::forProvider(
        'openai',
        429,
        requestException(429, ['Retry-After' => '2'])
    );

    expect(AiRetryPolicy::retryAfter($exception))->toBe(2000);
});

it('extracts retry-after http dates from the exception chain', function () {
    $exception = requestException(429, [
        'Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 10),
    ]);

    expect(AiRetryPolicy::retryAfter($exception))
        ->toBeGreaterThan(8000)
        ->toBeLessThanOrEqual(10000);
});

it('caps retry-after at the given maximum', function () {
    $exception = requestException(429, ['Retry-After' => '3600']);

    expect(AiRetryPolicy::retryAfter($exception, max: 5000))->toBe(5000);
});

it('returns null when no retry-after hint is present', function () {
    expect(AiRetryPolicy::retryAfter(requestException(429)))->toBeNull()
        ->and(AiRetryPolicy::retryAfter(RateLimitedException::forProvider('openai')))->toBeNull()
        ->and(AiRetryPolicy::retryAfter(new RuntimeException('Boom.')))->toBeNull();
});

it('returns null for unparseable retry-after values', function () {
    expect(AiRetryPolicy::retryAfter(requestException(429, ['Retry-After' => 'soon'])))->toBeNull();
});
