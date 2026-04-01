<?php

namespace App\Http\Controllers;

use App\Domains\Assets\Models\AssetCommunicationLog;
use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Domains\Vendors\Models\VendorCommunicationLog;
use App\Models\MailDeliveryEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MailerSendWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $events = $this->normalizeEvents($payload);

        foreach ($events as $event) {
            $this->storeEvent($event);
        }

        return response()->json(['received' => count($events)]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeEvents(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        // MailerSend can send a single event or an array of events.
        if (Arr::isList($payload)) {
            return $payload;
        }

        return [$payload];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function storeEvent(array $event): void
    {
        $eventType = (string) ($event['type'] ?? $event['event'] ?? 'unknown');
        $data = (array) ($event['data'] ?? $event);

        $messageId = (string) ($data['message_id'] ?? $data['messageId'] ?? '');
        $recipient = (string) ($data['recipient'] ?? $data['email'] ?? $data['recipient_email'] ?? '');
        $tags = $this->normalizeTags($data['tags'] ?? $event['tags'] ?? []);
        $eventAt = $this->normalizeEventTime($data['timestamp'] ?? $data['created_at'] ?? null);

        [$logSource, $logId] = $this->resolveLogFromTags($tags);

        MailDeliveryEvent::query()->create([
            'provider' => 'mailersend',
            'event_type' => $eventType,
            'message_id' => $messageId !== '' ? $messageId : null,
            'recipient_email' => $recipient !== '' ? $recipient : null,
            'tags' => $tags !== [] ? $tags : null,
            'payload' => $event,
            'flowdesk_log_id' => $logId,
            'log_source' => $logSource,
            'event_at' => $eventAt,
        ]);

        if ($logId !== null && $logSource !== null) {
            $this->appendDeliveryEventToLog($logSource, $logId, $eventType, $eventAt);
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizeTags(mixed $raw): array
    {
        if (is_string($raw)) {
            return array_values(array_filter(array_map('trim', explode(',', $raw))));
        }

        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }

        return [];
    }

    /**
     * @return array{0:?string,1:?int}
     */
    private function resolveLogFromTags(array $tags): array
    {
        $logSource = null;
        $logId = null;

        foreach ($tags as $tag) {
            if (str_starts_with($tag, 'log-')) {
                $candidate = (int) str_replace('log-', '', $tag);
                if ($candidate > 0) {
                    $logId = $candidate;
                }
            }

            if ($tag === 'requests' || $tag === 'request') {
                $logSource = 'requests';
            }

            if ($tag === 'vendors' || $tag === 'vendor') {
                $logSource = 'vendors';
            }

            if ($tag === 'assets' || $tag === 'asset') {
                $logSource = 'assets';
            }
        }

        return [$logSource, $logId];
    }

    private function normalizeEventTime(mixed $timestamp): ?string
    {
        if (is_numeric($timestamp)) {
            return date('Y-m-d H:i:s', (int) $timestamp);
        }

        if (is_string($timestamp) && trim($timestamp) !== '') {
            return $timestamp;
        }

        return null;
    }

    private function appendDeliveryEventToLog(string $source, int $logId, string $eventType, ?string $eventAt): void
    {
        $model = match ($source) {
            'requests' => RequestCommunicationLog::query()->find($logId),
            'vendors' => VendorCommunicationLog::query()->find($logId),
            'assets' => AssetCommunicationLog::query()->find($logId),
            default => null,
        };

        if (! $model) {
            return;
        }

        $metadata = is_array($model->metadata) ? $model->metadata : [];
        $events = is_array($metadata['delivery_events'] ?? null) ? $metadata['delivery_events'] : [];

        $events[] = array_filter([
            'provider' => 'mailersend',
            'event' => $eventType,
            'occurred_at' => $eventAt,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $metadata['delivery_events'] = $events;
        $metadata['delivery_provider'] = 'mailersend';
        $metadata['delivery_last_event'] = $eventType;

        $model->forceFill(['metadata' => $metadata])->save();
    }
}
