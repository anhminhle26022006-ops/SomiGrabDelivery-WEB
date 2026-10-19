<?php
declare(strict_types=1);

/**
 * Minimal Resend HTTP client for SOMI transactional email.
 *
 * RESEND_API_KEY must be provided by the server environment. The default
 * sender is Resend's test sender; for real recipients configure
 * RESEND_FROM_EMAIL with an address on a verified domain.
 */
final class ResendMailer
{
    private const API_URL = 'https://api.resend.com/emails';

    /**
     * Send a transactional email and return the Resend message ID.
     *
     * @throws RuntimeException when configuration or delivery fails
     */
    public static function send(string $to, string $subject, string $html, ?string $text = null): string
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Recipient email address is invalid.');
        }

        $apiKey = getenv('RESEND_API_KEY');
        if ($apiKey === false || trim($apiKey) === '') {
            throw new RuntimeException('RESEND_API_KEY is not configured for this PHP process.');
        }

        // This sender is for initial testing only. Resend restricts test-mode
        // delivery; configure a sender on a verified domain for customer mail.
        $from = getenv('RESEND_FROM_EMAIL');
        if ($from === false || trim($from) === '') {
            $from = 'onboarding@resend.dev';
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required to send email through Resend.');
        }

        $payload = [
            'from' => $from,
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
        ];
        if ($text !== null && $text !== '') {
            $payload['text'] = $text;
        }

        $curl = curl_init(self::API_URL);
        if ($curl === false) {
            throw new RuntimeException('Could not initialize the Resend HTTP request.');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        $responseBody = curl_exec($curl);
        $curlError = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($responseBody === false) {
            throw new RuntimeException('Resend request failed: ' . ($curlError ?: 'unknown cURL error'));
        }

        $response = json_decode($responseBody, true);
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($response) && isset($response['message'])
                ? (string) $response['message']
                : 'Resend returned HTTP ' . $statusCode . '.';
            throw new RuntimeException('Resend rejected the email: ' . $message);
        }

        if (!is_array($response) || !isset($response['id']) || !is_string($response['id'])) {
            throw new RuntimeException('Resend returned an unexpected response.');
        }

        return $response['id'];
    }
}
