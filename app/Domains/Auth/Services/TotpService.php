<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use Illuminate\Support\Facades\Cache;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const STEP_SECONDS = 30;

    public function generateSecret(int $length = 32): string
    {
        $length = max(16, $length);
        $secret = '';
        $maxIndex = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, $maxIndex)];
        }

        return $secret;
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        return $this->findMatchingTimeSlice($secret, $code, $window) !== null;
    }

    public function verifyOnce(string $secret, string $code, string $subjectKey, int $window = 1): bool
    {
        $matchedSlice = $this->findMatchingTimeSlice($secret, $code, $window);
        if ($matchedSlice === null) {
            return false;
        }

        if (! (bool) config('security.totp_replay.enabled', true)) {
            return true;
        }

        $replayKey = $this->replayCacheKey($subjectKey, $code, $matchedSlice);
        $ttlSeconds = $this->replayTtlSeconds($window);

        return Cache::add($replayKey, 1, now()->addSeconds($ttlSeconds));
    }

    public function currentCode(string $secret): string
    {
        $secret = strtoupper(trim($secret));

        return $this->generateCode($secret, (int) floor(time() / self::STEP_SECONDS));
    }

    public function codeForOffset(string $secret, int $offsetSlices = 0): string
    {
        $secret = strtoupper(trim($secret));
        $timeSlice = (int) floor(time() / self::STEP_SECONDS);

        return $this->generateCode($secret, $timeSlice + $offsetSlices);
    }

    public function otpauthUrl(string $appName, string $accountName, string $secret): string
    {
        $issuer = rawurlencode($appName);
        $label = rawurlencode($appName.':'.$accountName);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            rawurlencode($secret),
            $issuer
        );
    }

    private function generateCode(string $secret, int $timeSlice): string
    {
        $secretKey = $this->base32Decode($secret);
        $time = pack('N*', 0).pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;

        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function findMatchingTimeSlice(string $secret, string $code, int $window): ?int
    {
        $secret = strtoupper(trim($secret));
        $code = trim($code);

        if ($secret === '' || ! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $timeSlice = (int) floor(time() / self::STEP_SECONDS);
        $window = max(0, $window);

        for ($i = -$window; $i <= $window; $i++) {
            $candidateSlice = $timeSlice + $i;

            if (hash_equals($this->generateCode($secret, $candidateSlice), $code)) {
                return $candidateSlice;
            }
        }

        return null;
    }

    private function replayCacheKey(string $subjectKey, string $code, int $matchedSlice): string
    {
        return sprintf(
            '%s:%s:%d:%s',
            (string) config('security.totp_replay.cache_prefix', 'auth:totp:used'),
            sha1($subjectKey),
            $matchedSlice,
            $code
        );
    }

    private function replayTtlSeconds(int $window): int
    {
        $configured = (int) config('security.totp_replay.ttl_seconds', 0);
        if ($configured > 0) {
            return $configured;
        }

        return max(self::STEP_SECONDS, ((max(0, $window) * 2) + 1) * self::STEP_SECONDS + 5);
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper($secret);
        $secret = str_replace('=', '', $secret);

        $buffer = 0;
        $bitsLeft = 0;
        $result = '';

        foreach (str_split($secret) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $index;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $result;
    }
}
