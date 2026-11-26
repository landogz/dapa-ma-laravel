<?php

namespace App\Support\Password;

use Exception;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Stringable;

/**
 * Same as Laravel's NotPwnedVerifier, but with a short connect timeout so
 * register/login cannot stall when DNS to api.pwnedpasswords.com hangs.
 * On any transport failure the password is allowed (fail open).
 */
final class FastNotPwnedVerifier implements UncompromisedVerifier
{
    public function __construct(
        private readonly Factory $factory,
        private readonly int $timeout = 3,
        private readonly int $connectTimeout = 2,
    ) {
    }

    public function verify($data): bool
    {
        $value = (string) ($data['value'] ?? '');
        $threshold = (int) ($data['threshold'] ?? 0);

        if ($value === '') {
            return false;
        }

        $hash = strtoupper(sha1($value));
        $hashPrefix = substr($hash, 0, 5);

        return ! $this->search($hashPrefix)
            ->contains(function ($line) use ($hash, $hashPrefix, $threshold) {
                [$hashSuffix, $count] = explode(':', $line);

                return $hashPrefix.$hashSuffix === $hash && (int) $count > $threshold;
            });
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function search(string $hashPrefix)
    {
        try {
            $response = $this->factory->withHeaders([
                'Add-Padding' => true,
            ])
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->get('https://api.pwnedpasswords.com/range/'.$hashPrefix);
        } catch (Exception $e) {
            report($e);
        }

        $body = (isset($response) && $response->successful())
            ? $response->body()
            : '';

        return (new Stringable($body))->trim()->explode("\n")->filter(function ($line) {
            return str_contains($line, ':');
        });
    }
}
