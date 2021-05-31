<?php

namespace Dezento;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Collection;
use Symfony\Component\DomCrawler\Crawler;

class Crawlify
{
    private ?Collection $_settings;
    private ?Collection $_requests;
    public ?Collection $_fulfilled;
    public ?Collection $_rejected;


    public function __construct(Collection|array $requests)
    {
        $this->_settings = collect();
        $this->_requests = collect($requests);
        $this->_fulfilled = collect();
        $this->_rejected = collect();
    }


    public function settings(array $settings): Crawlify
    {
        $this->_settings = collect($settings);
        return $this;
    }

    private function client(): Client
    {
        $stack = HandlerStack::create();
        $stack->push(EffectiveUrlMiddleware::middleware());
        $this->_settings->put('handler', $stack);

        return new Client($this->_settings->toArray());
    }

    private function loadToDOMObject(object $result): void
    {
        $dom = new Crawler($result->response, $result->url);
        $this->pushResults((object)[
            'url' => $result->url,
            'response' => $dom
        ]);
    }

    private function pushResults(object $result): void
    {
        $this->_fulfilled->push($result);
    }

    private function sendRequests(\Generator $requests): void
    {
        $responses = collect([]);

        (new Pool(
            $this->client(),
            $requests,
            [
                'concurrency' => $this->_settings->get('concurrency'),
                'fulfilled' => function ($response) use ($responses) {
                    $data = (object)[
                        'url' => $response->getHeaderLine('X-GUZZLE-EFFECTIVE-URL'),
                        'response' => $response
                    ];
                    $responses->push($data);
                },
                'rejected' => function ($reason) {

                    $this->_rejected->push((object)[
                        'url' => $reason->getRequest()->getUri()->getHost(),
                        'response' => $reason->getMessage()
                    ]);
                }
            ]
        ))->promise()->wait();

        $this->processRequests($responses);
    }

    private function processRequests(Collection $responses): void
    {
        $responses
            ->map(fn($result) => (object)['url' => $result->url, 'response' => $result->response->getBody()->getContents()])
            ->each(function ($result) {

                if ($this->_settings->get('type') == 'JSON') {
                    $this->pushResults($result);
                    return;
                }
                $this->loadToDOMObject($result);
            });
    }

    private function createRequests(array $requests): \Generator
    {
        for ($i = 0; $i < count($requests); $i++) {
            yield new Request('GET', $requests[$i]);
        }
    }

    public function fetch(): Collection
    {
        $this->sendRequests($this->createRequests($this->convertToArray($this->_requests)));

        return collect([
            'fulfilled' => $this->_fulfilled,
            'rejected' => $this->_rejected ?? collect([])
        ]);
    }

    private function convertToArray(Collection|array $data): array
    {
        if (!is_array($data)) {
            return $data->toArray();
        }
        return $data;
    }
}
