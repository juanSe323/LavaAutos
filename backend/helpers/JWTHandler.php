<?php
class JWTHandler {
    // TODO: mover esta clave a una variable de entorno antes de subir a hosting real
    private static $secretKey = "LavaAutos_Clave_Secreta_2026_Cambiar";
    private static $algoritmo = 'HS256';

    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    // Genera un token. Por defecto expira en 8 horas (una jornada laboral)
    public static function generarToken($payloadData, $expiracionSegundos = 28800) {
        $header = json_encode(['typ' => 'JWT', 'alg' => self::$algoritmo]);

        $payloadData['iat'] = time();
        $payloadData['exp'] = time() + $expiracionSegundos;
        $payload = json_encode($payloadData);

        $headerEncoded = self::base64UrlEncode($header);
        $payloadEncoded = self::base64UrlEncode($payload);

        $firma = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", self::$secretKey, true);
        $firmaEncoded = self::base64UrlEncode($firma);

        return "$headerEncoded.$payloadEncoded.$firmaEncoded";
    }

    // Valida firma y expiración. Devuelve el payload decodificado o false.
    public static function validarToken($token) {
        $partes = explode('.', $token);
        if (count($partes) !== 3) {
            return false;
        }
        list($headerEncoded, $payloadEncoded, $firmaEncoded) = $partes;

        $firmaEsperada = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", self::$secretKey, true);
        $firmaEsperadaEncoded = self::base64UrlEncode($firmaEsperada);

        if (!hash_equals($firmaEsperadaEncoded, $firmaEncoded)) {
            return false; // firma inválida -> token alterado o clave incorrecta
        }

        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return false; // token expirado
        }

        return $payload;
    }
}
?>
