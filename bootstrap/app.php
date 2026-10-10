<?php

use App\Exceptions\ToolException;
use App\Http\Middleware\CleanupLottery;
use App\Http\Middleware\SecurityHeaders;
use App\Services\Seo\Redirector;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            CleanupLottery::class,
        ]);

        // Trusted reverse proxies come from config/trustedproxy.php (TRUSTED_PROXIES).

        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Expected, user-facing errors are not worth a log entry.
        $exceptions->dontReport([ToolException::class]);

        $wantsJson = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $json = fn (string $code, int $status, ?string $message = null) => response()->json([
            'status' => 'failed',
            'error' => ['code' => $code, 'message' => $message ?? ToolException::MESSAGES[$code] ?? ToolException::MESSAGES['server_error']],
        ], $status);

        // Unknown pages: admin-defined redirects, otherwise count it in the 404 monitor.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($wantsJson) {
            if ($wantsJson($request) || ! $request->isMethodSafe()) {
                return null;
            }

            return app(Redirector::class)->handle($request);
        });

        $exceptions->render(function (ToolException $e, Request $request) use ($wantsJson, $json) {
            if ($wantsJson($request)) {
                return $json($e->errorCode, $e->status, $e->getMessage());
            }

            return response()->view('errors.tool', ['message' => $e->getMessage()], $e->status);
        });

        $exceptions->render(function (PostTooLargeException $e, Request $request) use ($wantsJson, $json) {
            if ($wantsJson($request)) {
                return $json('file_too_large', 413, 'The upload is larger than the server allows ('.ini_get('post_max_size').'B).');
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($wantsJson, $json) {
            if ($wantsJson($request)) {
                return $json('rate_limited', 429)->withHeaders($e->getHeaders());
            }
        });

        $exceptions->render(function (InvalidSignatureException $e, Request $request) use ($wantsJson, $json) {
            if ($wantsJson($request)) {
                return $json('job_not_found', 404, 'This link has expired. Please process your file again.');
            }

            return response()->view('errors.tool', ['message' => 'This download link has expired. Files are deleted automatically – please process your file again.'], 410);
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) use ($wantsJson, $json) {
            if ($wantsJson($request)) {
                return $json('server_error', 419, 'Your session has expired. Please reload the page and try again.');
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($wantsJson, $json) {
            if ($wantsJson($request)) {
                return $json('invalid_options', 422, $e->validator->errors()->first());
            }
        });

        // Any other error on the API: generic message, details only in the log.
        $exceptions->render(function (Throwable $e, Request $request) use ($wantsJson, $json) {
            if (! $wantsJson($request)) {
                return null;
            }

            if ($e instanceof NotFoundHttpException) {
                return $json('job_not_found', 404, 'Not found.');
            }

            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                return $json('invalid_options', $e->getStatusCode(), 'The request could not be processed.');
            }

            return $json('server_error', 500);
        });
    })->create();
