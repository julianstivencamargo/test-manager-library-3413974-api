<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Security;

class JwtService
{
    private string $secret;

    public function __construct(?string $secret = null)
    {
        $this->secret = $secret ?? (string) config('app.key', 'base64:ManagerLibrarySecretKey_Default_3413974');
    }

    /**
     * @param array<string, mixed> $extraClaims
     */
    public function generateToken(string $userId, string $role, int $ttlSeconds = 28800, array $extraClaims = []): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = array_merge($extraClaims, [
            'sub' => $userId,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + $ttlSeconds,
        ]);

        $base64Header = $this->base64UrlEncode((string) json_encode($header));
        $base64Payload = $this->base64UrlEncode((string) json_encode($payload));
        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $this->secret, true);
        $base64Signature = $this->base64UrlEncode($signature);

        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64Header, $base64Payload, $base64Signature] = $parts;
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $this->secret, true)
        );

        if (!hash_equals($expectedSignature, $base64Signature)) {
            return null;
        }

        $decodedPayload = json_decode($this->base64UrlDecode($base64Payload), true);
        if (!is_array($decodedPayload) || !isset($decodedPayload['exp']) || $decodedPayload['exp'] < time()) {
            return null;
        }

        return $decodedPayload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
