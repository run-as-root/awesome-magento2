<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Guzzle retry middleware tuned for GitHub/Packagist/RSS calls:
 *   - Retries up to 3 times on 429 (rate-limited), 5xx, and connection errors.
 *   - Honours GitHub's `Retry-After` / `X-RateLimit-Reset` headers when present.
 *   - Exponential backoff (500ms, 1s, 2s) otherwise, capped.
 *
 * The public entry point is `callable(): \Closure` — wrap it into a HandlerStack.
 */
final class HttpRetry
{
    private const MAX_RETRIES = 3;
    private const CAP_SECONDS = 60;

    public static function middleware(): callable
    {
        return Middleware::retry(
            static function (
                int $attempt,
                RequestInterface $request,
                ?ResponseInterface $response = null,
                ?\Throwable $reason = null,
            ): bool {
                if ($attempt >= self::MAX_RETRIES) {
                    return false;
                }
                if ($reason instanceof ConnectException) {
                    return true;
                }
                if ($response === null) {
                    return false;
                }
                $code = $response->getStatusCode();
                if ($code === 429 || $code >= 500) {
                    return true;
                }
                // GitHub returns 403 with `X-RateLimit-Remaining: 0` on rate-limited reads.
                if ($code === 403 && $response->getHeaderLine('X-RateLimit-Remaining') === '0') {
                    return true;
                }
                return false;
            },
            static function (int $attempt, ?ResponseInterface $response = null): int {
                if ($response !== null) {
                    if ($retryAfter = $response->getHeaderLine('Retry-After')) {
                        return min(self::CAP_SECONDS, (int) $retryAfter) * 1000;
                    }
                    if ($reset = $response->getHeaderLine('X-RateLimit-Reset')) {
                        $delta = (int) $reset - time();
                        if ($delta > 0) {
                            return min(self::CAP_SECONDS, $delta) * 1000;
                        }
                    }
                }
                // Exponential backoff: 500ms, 1s, 2s.
                return (int) (500 * (2 ** ($attempt - 1)));
            },
        );
    }
}
