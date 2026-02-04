<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Encode JWT (HS256)
 */
function jwt_encode(array $payload, string $key): string
{
    $key = 'WEBMON_SUPER_SECRET_KEY_GANTI_INI'; 
    
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];

    $segments = [];
    $segments[] = base64url_encode(json_encode($header));
    $segments[] = base64url_encode(json_encode($payload));

    $signing_input = implode('.', $segments);

    $signature = hash_hmac('sha256', $signing_input, $key, true);
    $segments[] = base64url_encode($signature);

    return implode('.', $segments);
}

/**
 * Decode & verify JWT (HS256)
 */
function jwt_decode(string $token, string $key)
{
    $key = 'WEBMON_SUPER_SECRET_KEY_GANTI_INI';

    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$header64, $payload64, $signature64] = $parts;

    $signing_input = $header64 . '.' . $payload64;
    $signature = base64url_decode($signature64);
    $expected_signature = hash_hmac('sha256', $signing_input, $key, true);

    if (!hash_equals($signature, $expected_signature)) {
        log_message('error', 'JWT Debug: Signature mismatch. Check your Secret Key.');
        return false;
    }

    $payload = json_decode(base64url_decode($payload64), true);
    
    if (isset($payload['exp']) && time() >= $payload['exp']) {
        log_message('error', 'JWT Debug: Token Expired. Current time: ' . time() . ' Exp: ' . $payload['exp']);
        return false;
    }

    return $payload;
}
/**
 * Base64 URL-safe encode
 */
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL-safe decode (JWT-safe, WITH padding)
 */
function base64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($data, '-_', '+/'));
}
