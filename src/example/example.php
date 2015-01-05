<?php
require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Crawler.php';

$crawler = new \Crawler\Crawler(
	'http://example.com',
	array(
        'checkUrlExists' => true,
        'depth' => 4,
        'debug' => true
	)
)->crawl();

$links = $crawler->getLinks();
$internalLinks = $crawler->getInternalLinks();
$externalLinks = $crawler->getExternalLinks();
var_dump($links);
var_dump($internalLinks);
var_dump($externalLinks);
