<?php
/*
 * Crawler.php
 * 
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */
namespace Crawler;

use DOMDocument;
use LogicException;
use InvalidArgumentException;

/**
 * Crawler
 * A lightweight url crawler to generate link collections.
 *
 * @package   Crawler
 * @author    Kai Ratzeburg <hello@kai-ratzeburg.de>
 * @copyright 2014 Kai Ratzeburg
 * @license   GPLv3 http://www.gnu.org/licenses/gpl-3.0.txt
 * @version   1.0.0
 */
class Crawler {

    /**
     * Internal links type.
     *
     * @var string
     */
    const LINKS_INTERNAL = 'internal';

    /**
     * External links type.
     *
     * @var string
     */
    const LINKS_EXTERNAL = 'external';

    /**
     * Seen urls.
     *
     * @var array
     */
    protected $seenUrls = array();

    /**
     * Found links on urls.
     *
     * @var array
     */
    protected $foundLinks = array();

    /**
     * Links to ignore in crawl process.
     *
     * @var array
     */
    protected $ignoreLinks;

    /**
     * Document encoding.
     *
     * @var string
     */
    protected $documentEncoding;

    /**
     * Document version.
     *
     * @var string
     */
    protected $documentVersion;

    /**
     * Check urls exists.
     *
     * @var boolean
     */
    protected $checkUrlExists;

    /**
     * Add only internal links,
     *
     * @var boolean
     */
    protected $onlyInternal;

    /**
     * Add only external links.
     *
     * @var boolean
     */
    protected $onlyExternal;
    
    /**
     * Url to crawl.
     *
     * @var string
     */
    protected $url;

    /**
     * Scheme of url to crawl.
     * 
     * @var string
     */
    protected $scheme;

    /**
     * Host of url to crawl.
     *
     * @var string
     */
    protected $host;

    /**
     * Crawl depth.
     *
     * @var int
     */
    protected $depth;

    /**
     * Garbage collector enabled.
     *
     * @var boolean
     */
    protected $gcCollector;

    /**
     * Debug mode.
     *
     * @var boolean
     */
    protected $debug;

    /**
     * Constructor.
     *
     * @param string $url           URL to crawl
     * @param array  $configuration Crawler configuration
     *
     * @throws InvalidArgumentException
     */
    public function __construct($url, array $configuration = array()) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('URL is not valid.');
        }   

        $this->setUrl($url);
        $this->configure($configuration);
    }

    /**
     * Configure Crawler.
     *
     * @param array $configuration Crawler configuration
     *
     * @throws LogicException
     *
     * @return void
     */
    protected function configure(array $configuration = array()) {
        $defaultConfiguration = array(
            'documentVersion' => '1.0',
            'documentEncoding' => 'UTF-8',
            'checkUrlExists' => true,
            'onlyInternal' => false,
            'onlyExternal' => false,
            'ignoreLinks' => array(),
            'depth' => 5,
            'gcCollector' => true,
            'debug' => false
        );

        $configuration = array_merge($defaultConfiguration, $configuration);
        if ($configuration['onlyInternal'] && $configuration['onlyExternal']) {
            throw new LogicException('Logic: Only internal and only external cannot both activated.');
        }

        $this->documentVersion = $configuration['documentVersion'];
        $this->documentEncoding = $configuration['documentEncoding'];
        $this->checkUrlExists = $configuration['checkUrlExists'];
        $this->onlyInternal = $configuration['onlyInternal'];
        $this->onlyExternal = $configuration['onlyExternal'];
        $this->ignoreLinks = $configuration['ignoreLinks'];
        $this->depth = $configuration['depth'];
        $this->gcCollector = $configuration['gcCollector'];
        $this->debug = $configuration['debug'];
    }

    /**
     * Crawl an url with depth.
     *
     * @param string $url   URL to crawl.
     * @param int    $depth Crawl depth.
     *
     * @return void
     */
    protected function crawlPage($url, $depth) {
        if (isset($this->seenPages[$url]) || $depth === 0 || in_array($url, $this->ignoreLinks)) {
            if($this->debug) {
                printf('Already seen %s' . "\n", $url);
            }
            return;
        }

        $this->setUrlSeen($url);
        if ($this->checkUrlExists && !$this->urlExists($url)) {
            if($this->debug) {
                printf('Not exists %s' . "\n", $url);
            }
            return;
        }

        $dom = new DOMDocument($this->documentVersion, $this->documentEncoding);
        if (@$dom->loadHTMLFile($url)) {
            $anchors = $dom->getElementsByTagName('a');
            foreach ($anchors as $element) {
                $href = $element->getAttribute('href');
                $this->processUrl($href, $depth);

                if ($this->gcCollector && gc_enabled()) {
                    gc_collect_cycles();
                }
            }
        }
    }

    /**
     * Process url.
     *
     * @param string $url URL to process
     * @param int $depth  Crawl depth
     *
     * @return void
     */
    protected function processUrl($url, $depth) {
        if (!$this->startsWith($url, 'http')) {
            $url = $this->scheme . '://' . $this->host .
                (!$this->startsWith($url, '/') ? '/' : '') . $url;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $parsedUrl = parse_url($url);

            $type = self::LINKS_EXTERNAL;
            if (isset($parsedUrl['host']) && $parsedUrl['host'] == $this->host) {
                $this->crawlPage($url, $depth - 1);
                $type = self::LINKS_INTERNAL;
            }

            $this->addLink($url, $type);
        }
    }

    /**
     * Add links to collection with type.
     *
     * @param string $url  Add url to link collection.
     * @param string $type Link type
     *
     * @return void
     */
    protected function addLink($url, $type) {
        if (!array_key_exists($type, $this->foundLinks)) {
            $this->foundLinks[$type] = [];
        }
        if (($this->onlyInternal && $type == self::LINKS_EXTERNAL) ||
            ($this->onlyExternal && $type == self::LINKS_INTERNAL)) {
            return;
        }

        $addLink = true;
        if ($this->checkUrlExists) {
            $addLink = $this->urlExists($url);
        }
        if ($addLink && !$this->isUrlInList($url, $type) && !in_array($url, $this->ignoreLinks)) {
            if($this->debug) {
                printf('Found [%s] %s' . "\n", $type, $url);
            }

            $this->foundLinks[$type][] = $url;
        }
    }

    /**
     * Check url exists (HTTP status code == 200).
     *
     * @param string $url URL to check exist
     *
     * @return boolean
     */
    protected function urlExists($url) {
        $status = false;

        $handler = curl_init($url);    
        curl_setopt_array($handler, array(
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true
        ));
        curl_exec($handler);
        $code = curl_getinfo($handler, CURLINFO_HTTP_CODE);

        if ($code == 200) {
            $status = true;
        }
        
        curl_close($handler);

        return $status;
    }

    /**
     * Check haystack starts with needle.
     *
     * @param string $haystack String to check.
     * @param string $needle   Check if $haystack start with it
     * 
     * @return boolean
     */
    protected function startsWith($haystack, $needle) {
        return $needle === "" || 
            strrpos($haystack, $needle, -strlen($haystack)) !== false;
    }

    /**
     * Sets an url seen.
     *
     * @param string $url URL to set seen.
     *
     * @return void
     */
    protected function setUrlSeen($url) {
        $this->seenUrls[sha1($url)] = true;
    }

    /**
     * Is url seen.
     *
     * @param string $url URL to check if is seen.
     *
     * @return boolean
     */
    protected function isUrlSeen($url) {
        return isset($this->seenUrls[sha1($url)]);
    }

    /**
     * Is url in list.
     *
     * @param string $url  URL to check if is in list
     * @param string $type List type
     *
     * @return boolean
     */
    protected function isUrlInList($url, $type) {
        return in_array($url, $this->foundLinks[$type]);
    }

    /**
     * Start crawler process.
     *
     * @return Crawler
     */
    public function crawl() {
        if ($this->gcCollector && !gc_enabled()) {
            gc_enable();
        }

        $this->crawlPage($this->getUrl(), $this->getDepth());

        if ($this->gcCollector && gc_enabled()) {
            gc_disable();
        }
        return $this;
    }

    /**
     * Get links.
     *
     * @return array
     */
    public function getLinks() {
        if ($this->onlyInternal || $this->onlyExternal) {
            return $this->onlyInternal ? $this->getInternalLinks() : $this->getExternalLinks();
        }

        return array_merge($this->getInternalLinks(), $this->getExternalLinks());
    }

    /**
     * Get internal links.
     *
     * @throws LogicException
     *
     * @return array
     */
    public function getInternalLinks() {
        if ($this->onlyExternal) {
            throw new LogicException('onlyExternal is activated.');
        }

        return array_key_exists(self::LINKS_INTERNAL, $this->foundLinks) ? 
            $this->foundLinks[self::LINKS_INTERNAL] : 
            array();
    }

    /**
     * Get external links.
     *
     * @throws LogicException
     *
     * @return array
     */
    public function getExternalLinks() {
        if ($this->onlyInternal) {
            throw new LogicException('onlyInternal is activated.');
        }
        
        return array_key_exists(self::LINKS_EXTERNAL, $this->foundLinks) ? 
            $this->foundLinks[self::LINKS_EXTERNAL] : 
            array();
    }

    /**
     * Set url.
     *
     * @param string $url Set url to crawl.
     *
     * @return void
     */
    protected function setUrl($url) {
        $parsedUrl = parse_url($url);

        $this->scheme = $parsedUrl['scheme'];
        $this->host = $parsedUrl['host'];
        $this->url = $url;
    }

    /**
     * Get url.
     *
     * @return string
     */
    public function getUrl() {
        return $this->url;
    }

    /**
     * Get depth.
     *
     * @return int
     */
    public function getDepth() {
        return $this->depth;
    }
}