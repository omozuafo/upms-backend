<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeAndProtectInput
{
    /**
     * Keys that must never be altered or stripped (passwords, tokens).
     */
    protected array $except = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'APP_KEY',
        'JWT_SECRET',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        // 1. Check for operator injection (NoSQL / SQL operators like $ne, $gt, etc.)
        if ($this->hasOperatorInjection($input)) {
            return response()->json([
                'error' => 'Bad Request: Malformed input or operator injection detected.'
            ], 400);
        }

        // 2. Sanitize and escape input fields
        $sanitized = $this->sanitizeArray($input);
        $request->replace($sanitized);

        $response = $next($request);

        // 3. Add security headers to protect against XSS and MIME-sniffing
        if (method_exists($response, 'header')) {
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('X-XSS-Protection', '1; mode=block');
            $response->header('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }

    /**
     * Check recursively for keys with operator injection patterns ($ne, $gt, etc.).
     */
    protected function hasOperatorInjection($data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                // Check if key starts with '$' (NoSQL operator)
                if (str_starts_with($key, '$')) {
                    return true;
                }
            }

            if (is_array($value)) {
                if ($this->hasOperatorInjection($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Recursively sanitize input values.
     */
    protected function sanitizeArray(array $data): array
    {
        $cleaned = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $this->except, true)) {
                $cleaned[$key] = $value;
                continue;
            }

            if (is_array($value)) {
                $cleaned[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                // Remove null bytes
                $val = str_replace(chr(0), '', $value);
                // Strip tags to prevent stored XSS
                $val = strip_tags($val);
                $cleaned[$key] = trim($val);
            } else {
                $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }
}
