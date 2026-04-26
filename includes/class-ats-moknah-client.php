<?php

namespace AtsMoknahPlugin;

if (!defined('ABSPATH')) exit;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class MoknahClient {
    private string $apiEndpoint;
    private string $privateKey;
    private Client $http;

    public function __construct(string $apiEndpoint, string $privateKey) {
        $this->apiEndpoint = $apiEndpoint;
        $this->privateKey = $privateKey;
        $this->http = new Client(['timeout' => 30]);
    }

    public function processText(
        string $text,
        ?string $name,
        string $articleId,
        string $voiceId,
        string $callbackURL,
        string $preprocessType = "2", # 1: default, 2: AI enhanced ( AI enhanced not working correct now, so use 1 for testing )
        bool $regenerate = false
    ): string {
        $payload = [
            'text' => $text,
            'postData' => [
                'name' => $name,
                'articleId' => $articleId,
                'voiceId' => $voiceId,
                'callbackURL' => $callbackURL,
                'preprocessType' => $preprocessType,
                'regenerate' => $regenerate,
            ],
        ];
        try {

            $response = $this->http->post($this->apiEndpoint, [
                'headers' => [
                    'Authorization' => "Bearer {$this->privateKey}",
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload,
                'verify' => true,
            ]);
            update_post_meta(
                intval($articleId),
                '_moknah_preprocess_type',
                sanitize_text_field($preprocessType)
            );
            return (string)$response->getBody();
        } catch (RequestException $e) {
            $msg = $e->hasResponse()
                ? wp_strip_all_tags( (string) $e->getResponse()->getBody()->getContents() )
                : $e->getMessage();
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new \Exception(sanitize_text_field( $msg ));

        }
    }
}
