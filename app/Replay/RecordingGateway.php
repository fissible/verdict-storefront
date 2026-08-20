<?php

declare(strict_types=1);

namespace App\Replay;

use Generator;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Streaming\Events\StreamEvent;
use LogicException;

/**
 * Wraps the live gateway during demo:record-replays and captures each step
 * the real model produces, in exactly the shape resources/replays fixtures
 * store. Recording changes nothing about the run — every response passes
 * through untouched.
 */
final class RecordingGateway implements StepTextGateway
{
    /** @var list<array{text?: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>}> */
    private array $steps = [];

    public function __construct(private readonly StepTextGateway $inner) {}

    /**
     * @param  Message[]  $messages
     * @param  Tool[]  $tools
     * @param  array<string, Type>|null  $schema
     */
    public function generateTextStep(
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
        StepContext $stepContext,
    ): StepResponse {
        $response = $this->inner->generateTextStep(
            $provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout, $stepContext,
        );

        $step = [];

        if ($response->toolCalls !== []) {
            $step['tool_calls'] = array_map(fn (ToolCall $call): array => [
                'id' => $call->id,
                'name' => $call->name,
                'arguments' => $call->arguments,
            ], $response->toolCalls);
        }

        if ($response->text !== '') {
            $step['text'] = $response->text;
        }

        if ($step !== []) {
            $this->steps[] = $step;
        }

        return $response;
    }

    /**
     * @param  Message[]  $messages
     * @param  Tool[]  $tools
     * @param  array<string, Type>|null  $schema
     * @return Generator<int, StreamEvent, mixed, StepResponse|null>
     */
    public function generateStreamStep(
        string $invocationId,
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
        StepContext $stepContext,
    ): Generator {
        throw new LogicException('Recording supports synchronous prompts only.');
    }

    /** @return list<array{text?: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>}> */
    public function steps(): array
    {
        return $this->steps;
    }

    public function reset(): void
    {
        $this->steps = [];
    }
}
