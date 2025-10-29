# DeferredLoggerBundle

## Install

Require the bundle via composer (when published) or add to your `composer.json` and `composer install`.

Register bundle in `config/bundles.php`:

```php
return [
    Barry\DeferredLoggerBundle\BarryDeferredLoggerBundle::class => ['all' => true],
];
```

## Configure (optional)

```yaml
# config/packages/barry_deferred_logger.yaml
barry_deferred_logger:
  logger_channel: app
  auto_flush_on_exception: true
  auto_flush_on_request: true
  enable_sql_logging: false            # Enable automatic Doctrine SQL logging
  inject_trace_id_in_response: true    # Inject trace ID in response headers
  enable_messenger_trace: true         # Enable Messenger trace propagation
```

## Features

### 1. Automatic SQL Logging

When `enable_sql_logging: true`, the bundle automatically captures all Doctrine SQL queries with:
- Original SQL statement
- Query parameters
- Formatted SQL (with parameters interpolated for readability)
- Execution time in milliseconds

No need to manually call `DeferredLogger::contextSql()` anymore!

### 2. Distributed Tracing Support

The bundle provides **production-ready distributed tracing** with the following features:

#### Trace Context Propagation

Automatically extracts trace IDs from incoming requests, supporting multiple standards:

1. **W3C Trace Context** (`traceparent` header) - Industry standard
2. **X-Trace-ID** - Common in microservices
3. **X-Request-ID** - Used by Nginx, load balancers
4. **X-Correlation-ID** - Alternative correlation header

If no trace ID is found, a new one is automatically generated.

#### Response Header Injection

When `inject_trace_id_in_response: true` (default), the bundle adds trace headers to every response:

```http
X-Trace-ID: 550e8400e29b41d4a716446655440000
X-Span-ID: 7f3a9e4c6b2d8f1a
traceparent: 00-550e8400e29b41d4a716446655440000-7f3a9e4c6b2d8f1a-01
```

This allows:
- Frontend clients to correlate requests with logs
- API consumers to trace requests across microservices
- Debugging tools to link distributed operations

#### Microservices Integration

**Service A → Service B example:**

```php
// Service A: Making a request to Service B
$traceId = DeferredLogger::getTraceId();

$response = $httpClient->request('POST', 'https://service-b/api/endpoint', [
    'headers' => [
        'X-Trace-ID' => $traceId,  // Pass trace ID to downstream service
    ],
]);
```

Service B will automatically pick up the trace ID from the header and use it in its logs!

#### Long-Running Process Support (Swoole/RoadRunner)

The bundle properly resets trace context on each request:

```php
// StartRequestSubscriber automatically calls:
$instance->reset();  // Clear buffer and trace context
$traceContext = TraceContext::fromHeaders($request->headers->all());
$instance->setTraceContext($traceContext);
```

#### Log Output Format

All logs include comprehensive trace information:

```json
{
  "trace_id": "550e8400e29b41d4a716446655440000",
  "span_id": "7f3a9e4c6b2d8f1a",
  "parent_span_id": null,
  "sampled": true,
  "info": [...]
}
```

#### Integration with Tracing Systems

The trace format is compatible with:
- **Jaeger** - Uber's distributed tracing system
- **Zipkin** - Twitter's distributed tracing system
- **AWS X-Ray** - Amazon's tracing service
- **Google Cloud Trace** - GCP tracing service
- Any system supporting W3C Trace Context

#### Best Practices

1. **API Gateway**: Extract trace ID from incoming requests or generate at the edge
2. **Propagate Downstream**: Always pass `X-Trace-ID` to downstream services
3. **Log Aggregation**: Use trace_id to group logs across services (e.g., ELK, Datadog)
4. **Frontend Integration**: Return trace ID to clients for support tickets
5. **Monitoring**: Create dashboards grouped by trace_id for request flow visualization

### 3. Symfony Messenger Integration

**Automatic trace propagation across async messages!**

When `enable_messenger_trace: true` (default), the bundle automatically:

#### How It Works

1. **Message Dispatch**: When you dispatch a message, the current trace context is automatically attached as a `TraceContextStamp`
2. **Async Processing**: When a worker processes the message, the trace context is restored
3. **Child Spans**: Async operations create child spans, maintaining the parent-child relationship

#### Example

```php
// In your HTTP controller (Request A)
use Symfony\Component\Messenger\MessageBusInterface;

public function processOrder(MessageBusInterface $bus): Response
{
    // Current trace_id: 550e8400-e29b-41d4-a716-446655440000
    // Current span_id: 7f3a9e4c6b2d8f1a

    DeferredLogger::contextInfo('Dispatching order processing');

    // Dispatch async message - trace context is AUTOMATICALLY attached
    $bus->dispatch(new ProcessOrderMessage($orderId));

    return new JsonResponse(['status' => 'queued']);
}
```

```php
// In your Message Handler (Async Worker)
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessOrderHandler
{
    public function __invoke(ProcessOrderMessage $message): void
    {
        // Trace context is AUTOMATICALLY restored!
        // trace_id: 550e8400-e29b-41d4-a716-446655440000 (SAME as parent)
        // span_id: a1b2c3d4e5f6g7h8 (NEW child span)
        // parent_span_id: 7f3a9e4c6b2d8f1a (points to parent)

        DeferredLogger::contextInfo('Processing order in async worker');

        // All logs here will have the SAME trace_id as the original request!
    }
}
```

#### Benefits

- **Cross-Process Tracing**: Track requests from HTTP handler through async workers
- **No Manual Work**: Zero code changes needed, works automatically
- **Worker Isolation**: Each message gets its own span, properly cleaned up after processing
- **Queue Debugging**: Find all logs related to a specific message across multiple workers

#### Configuration

```yaml
barry_deferred_logger:
  enable_messenger_trace: true  # Enabled by default
```

#### Advanced: Manual Trace Control

If needed, you can access trace info in handlers:

```php
#[AsMessageHandler]
class MyHandler
{
    public function __invoke(MyMessage $message): void
    {
        $traceId = DeferredLogger::getTraceId();

        // Make external API call with trace propagation
        $this->httpClient->request('POST', 'https://external-api.com', [
            'headers' => ['X-Trace-ID' => $traceId],
        ]);
    }
}
```

#### Trace Flow Visualization

```
HTTP Request (trace: 550e8400)
  └─ span: 7f3a9e4c
      ├─ Log: "Order received"
      ├─ Dispatch Message ✉
      └─ Response sent

Async Worker (trace: 550e8400)  ← SAME trace_id!
  └─ span: a1b2c3d4 (parent: 7f3a9e4c)
      ├─ Log: "Processing order"
      ├─ SQL: UPDATE orders...
      └─ Log: "Order completed"
```

All logs searchable by `trace_id: 550e8400` in your log aggregation system!
