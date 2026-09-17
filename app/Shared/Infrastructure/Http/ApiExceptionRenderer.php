<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Error\SystemErrorCode;
use App\Shared\Domain\Exception\DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The only place an API error becomes a response body. Nothing else formats errors.
 */
final class ApiExceptionRenderer
{
    /**
     * Rule names (snake_case) whose arguments are values a translation
     * genuinely needs to render — a bound, a format, an allowed set — rather
     * than an internal schema identifier such as a table or column name.
     *
     * @var list<string>
     */
    private const RULES_WITH_SAFE_ARGS = [
        'min', 'max', 'size', 'between', 'in', 'not_in',
        'digits', 'digits_between', 'after', 'before', 'date_format',
    ];

    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        $traceId = (string) Str::ulid();

        return match (true) {
            $e instanceof DomainException => $this->envelope(
                $e->status(), $e->errorCode(), $e->params(), $traceId, $e->errorCode(), $request,
            ),
            $e instanceof ValidationException => $this->validation($e, $traceId, $request),
            $e instanceof AuthenticationException => $this->envelope(
                401, SystemErrorCode::Unauthenticated->value, [], $traceId, 'Unauthenticated.', $request,
            ),
            // Laravel's Handler::prepareException() runs before this callback and
            // converts an AuthorizationException into AccessDeniedHttpException (or a
            // plain HttpException when a custom status was set), so this arm never
            // fires in the wired pipeline. Kept for correctness if the renderer is
            // ever invoked directly with the raw exception.
            $e instanceof AuthorizationException => $this->envelope(
                403, SystemErrorCode::Forbidden->value, [], $traceId, 'Forbidden.', $request,
            ),
            $e instanceof AccessDeniedHttpException => $this->envelope(
                403, SystemErrorCode::Forbidden->value, [], $traceId, 'Forbidden.', $request,
            ),
            // Same as above: prepareException() converts ModelNotFoundException into
            // NotFoundHttpException before this callback runs. Kept as belt-and-braces,
            // not as a distinction this code is actually making.
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => $this->envelope(
                404, SystemErrorCode::NotFound->value, [], $traceId, 'Not found.', $request,
            ),
            $e instanceof MethodNotAllowedHttpException => $this->envelope(
                405, SystemErrorCode::MethodNotAllowed->value, [], $traceId, 'Method not allowed.', $request,
            ),
            $e instanceof ThrottleRequestsException => $this->envelope(
                429, SystemErrorCode::TooManyRequests->value, [], $traceId, 'Too many requests.', $request,
            ),
            // Catch-all for any other framework HTTP exception (413, 410, 415, ...)
            // that reaches here with its own status code. Must stay after the more
            // specific HttpException subclasses above, since they all implement
            // this same interface.
            $e instanceof HttpExceptionInterface => $this->envelope(
                $e->getStatusCode(), SystemErrorCode::HttpError->value, [], $traceId, 'HTTP error.', $request,
            ),
            default => $this->unexpected($e, $traceId, $request),
        };
    }

    /**
     * The catch-all. The response carries a code and a trace id; everything else goes to the log.
     */
    private function unexpected(Throwable $e, string $traceId, Request $request): JsonResponse
    {
        Log::error('Unhandled exception reached the API boundary', [
            'trace_id' => $traceId,
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'location' => $e->getFile().':'.$e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return $this->envelope(
            500, SystemErrorCode::UnexpectedError->value, [], $traceId, 'Unexpected error.', $request,
            alreadyLogged: true,
        );
    }

    private function validation(ValidationException $e, string $traceId, Request $request): JsonResponse
    {
        $fields = [];

        foreach ($e->validator->failed() as $attribute => $rules) {
            foreach ($rules as $rule => $args) {
                $fields[$attribute][] = $this->fieldFailure($attribute, (string) $rule, $args);
            }
        }

        return $this->envelope(
            422, SystemErrorCode::ValidationFailed->value, [], $traceId, 'Validation failed.', $request, $fields,
        );
    }

    /**
     * A hand-written Rule/ValidationRule object leaves its own class name as the
     * failed-rule identifier instead of a plain rule keyword — e.g.
     * "App\Modules\Content\...\TopicNameIsUnique". Interpolating that into the
     * client-facing code would leak an internal detail and hand the frontend an
     * unstable code that breaks the moment the class is renamed or moved. Any
     * identifier that isn't plain [A-Za-z0-9_] falls back to the established
     * validation.invalid code instead.
     *
     * @return array{code: string, params: object}
     */
    private function fieldFailure(string $attribute, string $rule, mixed $args): array
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $rule) !== 1) {
            return [
                'code' => SystemErrorCode::ValidationInvalid->value,
                'params' => (object) ['attribute' => $attribute],
            ];
        }

        $params = ['attribute' => $attribute];

        // Only rules whose arguments a translation genuinely needs (a bound, a
        // format, an allowed set of values) get their positional args back.
        // Everything else — `unique:table,column`, `exists:table,column`, a
        // future custom DB-backed rule — would otherwise interpolate schema
        // identifiers (table/column names) straight into an unauthenticated
        // response. Those rules still get their code and `attribute`, just no
        // `arg0`, `arg1`, ...
        if (in_array(Str::snake($rule), self::RULES_WITH_SAFE_ARGS, true)) {
            foreach (array_values((array) $args) as $index => $arg) {
                $params['arg'.$index] = is_scalar($arg) ? (string) $arg : null;
            }
        }

        return [
            'code' => 'validation.'.Str::snake($rule),
            'params' => (object) $params,
        ];
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @param  array<string, list<array{code: string, params: object}>>  $fields
     */
    private function envelope(
        int $status,
        string $code,
        array $params,
        string $traceId,
        string $message,
        Request $request,
        array $fields = [],
        bool $alreadyLogged = false,
    ): JsonResponse {
        // Every arm but unexpected() lands here without having logged anything
        // itself, so this is the one place that guarantees trace_id actually
        // correlates with a log line — not just for the 500 case. unexpected()
        // already wrote its own, richer entry (exception class, stack trace),
        // so it opts out via $alreadyLogged instead of getting a second, thinner
        // line for the same failure.
        if (! $alreadyLogged) {
            $context = ['trace_id' => $traceId, 'code' => $code, 'path' => $request->path()];

            if ($status >= 500) {
                Log::error('API request failed', $context);
            } else {
                Log::warning('API request failed', $context);
            }
        }

        $body = [
            'error' => [
                'code' => $code,
                'params' => (object) $params,
                'message' => $message,
                'trace_id' => $traceId,
            ],
        ];

        if ($fields !== []) {
            $body['errors'] = $fields;
        }

        return new JsonResponse($body, $status);
    }
}
