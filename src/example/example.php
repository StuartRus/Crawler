<?php
require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Crawler.php';

$crawler = new \Crawler\Crawler(
	'http://example.com',
	array(
        'checkUrlExists' => true,
        'depth' => 5,
        'debug' => true
	)
);

$links = $crawler->crawl()->getLinks();
var_dump($links);