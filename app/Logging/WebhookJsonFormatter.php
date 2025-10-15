<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

class WebhookJsonFormatter extends JsonFormatter
{
    /**
     * Format a log record.
     */
    public function format(LogRecord $record): string
    {
        $data = $record->toArray();
        
        // Add webhook-specific context
        $formatted = [
            'timestamp' => $record->datetime->format('Y-m-d\TH:i:s.uP'),
            'level' => $record->level_name,
            'message' => $record->message,
            'channel' => $record->channel,
            'context' => $this->formatContext($record->context),
            'extra' => $record->extra,
        ];

        // Add performance metrics if available
        if (isset($record->extra['memory_usage'])) {
            $formatted['performance'] = [
                'memory_usage' => $record->extra['memory_usage'],
                'memory_peak' => $record->extra['memory_peak'] ?? null,
                'execution_time' => $record->extra['execution_time'] ?? null,
            ];
        }

        // Add request correlation if available
        if (isset($record->extra['request_id'])) {
            $formatted['correlation'] = [
                'request_id' => $record->extra['request_id'],
                'trace_id' => $record->extra['trace_id'] ?? null,
                'span_id' => $record->extra['span_id'] ?? null,
            ];
        }

        // Add webhook-specific fields
        if (isset($record->context['platform'])) {
            $formatted['webhook'] = [
                'platform' => $record->context['platform'],
                'event_type' => $record->context['event_type'] ?? null,
                'event_id' => $record->context['event_id'] ?? null,
                'account_id' => $record->context['account_id'] ?? null,
                'user_id' => $record->context['user_id'] ?? null,
            ];
        }

        return $this->toJson($formatted, true) . "\n";
    }

    /**
     * Format context data.
     */
    protected function formatContext(array $context): array
    {
        $formatted = [];
        
        foreach ($context as $key => $value) {
            // Skip webhook-specific fields that are handled separately
            if (in_array($key, ['platform', 'event_type', 'event_id', 'account_id', 'user_id'])) {
                continue;
            }
            
            // Handle special cases
            if ($value instanceof \Throwable) {
                $formatted[$key] = [
                    'type' => 'exception',
                    'class' => get_class($value),
                    'message' => $value->getMessage(),
                    'file' => $value->getFile(),
                    'line' => $value->getLine(),
                    'trace' => $value->getTraceAsString(),
                ];
            } elseif (is_resource($value)) {
                $formatted[$key] = ['type' => 'resource'];
            } elseif (is_object($value)) {
                $formatted[$key] = [
                    'type' => 'object',
                    'class' => get_class($value),
                    'properties' => $this->getObjectProperties($value),
                ];
            } else {
                $formatted[$key] = $value;
            }
        }
        
        return $formatted;
    }

    /**
     * Get object properties for logging.
     */
    protected function getObjectProperties(object $object): array
    {
        try {
            if (method_exists($object, 'toArray')) {
                return $object->toArray();
            }
            
            if (method_exists($object, 'jsonSerialize')) {
                return $object->jsonSerialize();
            }
            
            return get_object_vars($object);
        } catch (\Throwable $e) {
            return ['error' => 'Could not serialize object: ' . $e->getMessage()];
        }
    }
}