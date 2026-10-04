<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class TelegramTokenVerifier
{
    public function verify(string $token, string $nonce): array
    {
        try {
            $jwks = Cache::remember('telegram.jwks', 3600, fn () => Http::connectTimeout(3)->timeout(5)->get(config('fitspot.telegram_jwks_url'))->throw()->json());
            $claims = (array) JWT::decode($token, JWK::parseKeySet($jwks, 'RS256'));
            if (($claims['iss'] ?? null) !== 'https://oauth.telegram.org' || (string) ($claims['aud'] ?? '') !== (string) config('fitspot.telegram_client_id') || ! isset($claims['exp'],$claims['iat'],$claims['sub'],$claims['nonce']) || $claims['exp'] <= time() || $claims['iat'] > time() + 30 || $claims['iat'] < time() - 600 || ! hash_equals($nonce, (string) $claims['nonce']) || ! preg_match('/^\d+$/', (string) $claims['sub'])) {
                throw new \RuntimeException('Invalid claims');
            }
            // Reject non-RS256 even if a different algorithm appears in a supplied JWKS.
            $header = json_decode(JWT::urlsafeB64Decode(explode('.', $token)[0]), true);
            if (($header['alg'] ?? '') !== 'RS256') {
                throw new \RuntimeException('Invalid algorithm');
            }
            if (! Cache::add('telegram.used.'.hash('sha256', $nonce), true, 720)) {
                throw new \RuntimeException('Replayed nonce');
            }

            return ['subject' => (string) $claims['sub'], 'name' => mb_substr((string) ($claims['name'] ?? 'Тренер'), 0, 100)];
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['telegram' => 'Не удалось подтвердить вход через Telegram. Попробуйте ещё раз.']);
        }
    }
}
