<?php

declare(strict_types=1);

namespace Tests\Feature\Replay;

use App\Replay\RecordingGateway;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\Usage;
use Tests\TestCase;

/**
 * The recorder that demo:record-replays wraps around the live gateway: it
 * captures each step the real model produces — tool calls, then closing text
 * — in exactly the shape resources/replays fixtures store.
 */
final class RecordingGatewayTest extends TestCase
{
    public function test_it_captures_steps_and_passes_responses_through(): void
    {
        $inner = new class implements StepTextGateway
        {
            public int $calls = 0;

            public function generateTextStep($provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout, $stepContext): StepResponse
            {
                $this->calls++;

                return $this->calls === 1
                    ? new StepResponse('', [new ToolCall('live-1', 'LookupOrderTool', ['order_number' => 'ORD-1001'])], FinishReason::ToolCalls, new Usage, new Meta('ollama', 'test-model'))
                    : new StepResponse('Here is your order.', [], FinishReason::Stop, new Usage, new Meta('ollama', 'test-model'));
            }

            public function generateStreamStep($invocationId, $provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout, $stepContext): \Generator
            {
                yield from [];
            }
        };

        $recorder = new RecordingGateway($inner);
        $provider = $this->createStub(TextProvider::class);

        $first = $recorder->generateTextStep($provider, 'test-model', null, [], [], null, null, null, new StepContext(0));
        $second = $recorder->generateTextStep($provider, 'test-model', null, [], [], null, null, null, new StepContext(1));

        $this->assertSame('LookupOrderTool', $first->toolCalls[0]->name);
        $this->assertSame('Here is your order.', $second->text);

        $this->assertSame([
            ['tool_calls' => [['id' => 'live-1', 'name' => 'LookupOrderTool', 'arguments' => ['order_number' => 'ORD-1001']]]],
            ['text' => 'Here is your order.'],
        ], $recorder->steps());
    }

    public function test_reset_clears_captured_steps(): void
    {
        $inner = new class implements StepTextGateway
        {
            public function generateTextStep($provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout, $stepContext): StepResponse
            {
                return new StepResponse('hello', [], FinishReason::Stop, new Usage, new Meta('ollama', 'test-model'));
            }

            public function generateStreamStep($invocationId, $provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout, $stepContext): \Generator
            {
                yield from [];
            }
        };

        $recorder = new RecordingGateway($inner);
        $recorder->generateTextStep($this->createStub(TextProvider::class), 'm', null, [], [], null, null, null, new StepContext(0));

        $recorder->reset();

        $this->assertSame([], $recorder->steps());
    }
}
