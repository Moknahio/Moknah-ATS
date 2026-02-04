<?php

namespace ATS_Moknah;

if (!defined('ABSPATH')) exit;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

class MoknahClient {
    private string $apiEndpoint;
    private string $privateKey;
    private Client $http;

    public function __construct(string $apiEndpoint, string $privateKey) {
        $this->apiEndpoint = $apiEndpoint;
        $this->privateKey = $privateKey;
        $this->http = new Client(['timeout' => 30]);
    }

    private function hasSkippedAncestor(\DOMNode $node, array $skipClasses): bool {
        while ($node) {
            if ($node instanceof \DOMElement) {
                $class = $node->getAttribute('class');
                foreach ($skipClasses as $skip) {
                    if ($class && str_contains($class, $skip)) {
                        return true;
                    }
                }
            }
            $node = $node->parentNode;
        }
        return false;
    }

    public function extractTextFromHtml(string $html, string $selector, array $skipClasses = []): string {
        $html = preg_replace('/<style[\s\S]*?<\/style>/i', '', $html);
        $crawler = new Crawler($html);
        $crawler->filter('script, style, iframe')->each(function($node) {
            foreach ($node as $n) $n->parentNode->removeChild($n);
        });

        $nodes = $crawler->filter($selector);
        if (!$nodes->count()) throw new \Exception("No elements found for selector: $selector");

        $text = '';
        foreach ($nodes as $domNode) {
            $walker = function($current) use (&$walker, &$text, $skipClasses) {
                if ($this->hasSkippedAncestor($current, $skipClasses)) return;
                if ($current->nodeType === XML_TEXT_NODE) {
                    $value = trim($current->nodeValue);
                    if ($value !== '') $text .= ' ' . $value;
                    return;
                }
                if ($current->nodeType === XML_ELEMENT_NODE) {
                    $tag = strtolower($current->nodeName);
                    if (in_array($tag, ['script','style','iframe'])) return;
                    foreach ($current->childNodes as $child) $walker($child);
                }
            };
            $walker($domNode);
        }
        return trim(preg_replace('/\s+/', ' ', $text));
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
            return (string)$response->getBody();
        } catch (RequestException $e) {
            $msg = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            throw new \Exception("Moknah API request failed: $msg");
        }
    }
}
