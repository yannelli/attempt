<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Yannelli\Attempt\Facades\Attempt;
use Yannelli\Attempt\Tests\Fixtures\TestAiAgent;

function openAiSuccessBody(string $text = 'Hello!'): array
{
    return [
        'id' => 'resp_123',
        'status' => 'completed',
        'model' => 'gpt-test',
        'output' => [
            [
                'type' => 'message',
                'content' => [
                    ['type' => 'output_text', 'text' => $text],
                ],
            ],
        ],
        'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
    ];
}

describe('Attempt::ai()', function () {
    it('retries transient ai failures', function () {
        $calls = 0;

        $result = Attempt::ai(function () use (&$calls) {
            if (++$calls < 3) {
                throw RateLimitedException::forProvider('openai');
            }

            return 'success';
        })
            ->retry(3)
            ->delayUsing(fn (): int => 0)
            ->run();

        expect($result->succeeded())->toBeTrue()
            ->and($result->value())->toBe('success')
            ->and($result->attempts())->toBe(3);
    });

    it('does not retry permanent ai failures', function () {
        $result = Attempt::ai(function () {
            throw InsufficientCreditsException::forProvider('openai');
        })
            ->retry(3)
            ->delayUsing(fn (): int => 0)
            ->run();

        expect($result->failed())->toBeTrue()
            ->and($result->attempts())->toBe(1)
            ->and($result->exception())->toBeInstanceOf(InsufficientCreditsException::class);
    });

    it('composes with fallbacks', function () {
        $result = Attempt::ai(function () {
            throw RateLimitedException::forProvider('openai');
        })
            ->retry(1)
            ->delayUsing(fn (): int => 0)
            ->fallback(fn () => 'cached response')
            ->run();

        expect($result->succeeded())->toBeTrue()
            ->and($result->value())->toBe('cached response')
            ->and($result->resolvedBy())->toStartWith('fallback:');
    });
});

describe('RetryAiRequests middleware', function () {
    it('retries rate limited agent prompts before failing over', function () {
        Http::fake([
            'api.openai.com/v1/responses' => Http::sequence()
                ->push(['error' => ['message' => 'Rate limited.']], 429, ['Retry-After' => '0'])
                ->push(['error' => ['message' => 'Rate limited.']], 429, ['Retry-After' => '0'])
                ->push(openAiSuccessBody('Recovered!')),
        ]);

        $response = (new TestAiAgent(retries: 2))->prompt('Hello', provider: 'openai', model: 'gpt-test');

        expect($response->text)->toBe('Recovered!');

        Http::assertSentCount(3);
    });

    it('rethrows the original exception after exhausting retries', function () {
        Http::fake([
            'api.openai.com/v1/responses' => Http::response(
                ['error' => ['message' => 'Rate limited.']],
                429,
                ['Retry-After' => '0']
            ),
        ]);

        expect(fn () => (new TestAiAgent(retries: 1))->prompt('Hello', provider: 'openai', model: 'gpt-test'))
            ->toThrow(RateLimitedException::class);

        Http::assertSentCount(2);
    });

    it('does not retry non-transient agent failures', function () {
        Http::fake([
            'api.openai.com/v1/responses' => Http::response(
                ['error' => ['message' => 'Bad request.']],
                400
            ),
        ]);

        expect(fn () => (new TestAiAgent(retries: 3))->prompt('Hello', provider: 'openai', model: 'gpt-test'))
            ->toThrow(Exception::class);

        Http::assertSentCount(1);
    });
});
