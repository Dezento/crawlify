## Crawlify


### Installation

```
composer require dezento/crawlify
```

### Overview

Crawlify is a lightweight crawler for manipulating HTML,XML and JSON using [DomCrawler](https://symfony.com/doc/current/components/dom_crawler.html).
It uses [GuzzleHttp\Pool](https://docs.guzzlephp.org/en/stable/quickstart.html#concurrent-requests) to make concurrent request and that means you can use all [Request Options](https://docs.guzzlephp.org/en/stable/request-options.html) available.  
The result it gives back is wrapped with [Laravel Collections](https://laravel.com/docs/8.x/collections).

### Examples


##### CRAWL JSON 

```
use Dezento\Crawlify;


$links = [];
for ($i = 1; $i <= 100; $i++) {
    $links[] = 'https://jsonplaceholder.typicode.com/posts/' . $i ;
}

$json = (new Crawlify(collect($links))) // you can pass Array or Collection of links
->settings([
  'type' => 'JSON'  //this is Crawlify Option
])
->fetch()
->get('fulfilled')
->map(fn ($p) => collect(json_decode($p->response)))
->dd();
```

##### CRAWL XML 

For traversing XML refer to [DomCrawler](https://symfony.com/doc/current/components/dom_crawler.html) documentation.

```
$xml = (new Crawlify([
    'https://www.nytimes.com/svc/collections/v1/publish/https://www.nytimes.com/section/world/rss.xml',
]))
->fetch()
->get('fulfilled')
->map(fn ($item) =>
  collect($item->response->filter('item')->children())
  ->map(fn ($data) => $data->textContent)
)->dd();

```

##### CRAWL HTML 

For traversing HTML refer to [DomCrawler](https://symfony.com/doc/current/components/dom_crawler.html) documentation.

```
$html = (new Crawlify([
  'https://en.wikipedia.org/wiki/Category:Lists_of_spider_species_by_family'
]))
->settings([
  #'proxy' => 'http://username:password@192.168.16.1:10',
  'concurrency' => 5,
  'delay' => 0
])
->fetch()
->get('fulfilled')
->map(fn ($item) =>
  collect($item->response->filter('a')->links())
  ->map(fn($el) => $el->getUri())
)
->reject(fn($a) => $a->isEmpty())
->dd();

```

##### OPTIONS

```
->settings([
  'proxy' => 'http://username:password@192.168.16.1:10',
  'concurrency' => 5,
  'delay' => 0,
  ....
])
``` 

For options you can refer to [Request Options](https://docs.guzzlephp.org/en/stable/request-options.html) documentation.
The only Crawlify custom options is ```  'type' => 'JSON' ``` 

#### Note

Before using ``` dd() ``` helper you must install it. 

```  composer require symfony/var-dumper ```
  
