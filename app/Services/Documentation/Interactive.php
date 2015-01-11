<?php namespace App\Services\Documentation;

use App\Services\Contracts\Documentation as DocumentationInterface;
use App\Services\Documentation;
use DOMDocument;
use DOMXPath;
use Github\Client;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use League\CommonMark\CommonMarkConverter;

class Interactive implements DocumentationInterface {

    protected $documentation;

    protected $github;

    protected $cache;

    protected $results;

    private   $cacheTime = 60;

    public function __construct(Documentation $documentation, Client $github, Repository $cache)
    {
        $this->documentation = $documentation;
        $this->github        = $github;
        $this->cache         = $cache;
    }

    public function start()
    {
        $this->results = $this->checkAndHelp();

        return $this->sendToSlack();
    }

    public function sendToSlack()
    {
        return $this->results;
    }

    public function cacheResponse($key, $callback)
    {
        if ($this->cache->has($key) && $this->cache->get($key) != false) {
            return $this->cache->get($key);
        }

        $results = $callback();

        $this->cache->put($key, $results, $this->cacheTime);

        return $results;
    }

    private function checkAndHelp()
    {
        if ($this->documentation->version == null) {
            return $this->getVersions();
        }
        if ($this->documentation->header == null) {
            return $this->getHeaders();
        }
        if ($this->documentation->sub == null) {
            return $this->getSubs();
        }
    }

    public function getVersions()
    {
        $cacheKey = 'docs.versions';

        $versions = $this->getCachedVersions($cacheKey);

        return $this->prettyArray('versions', $versions);
    }

    public function getHeaders()
    {
        $this->documentation->verifyVersion();

        $cacheKey = 'docs.' . $this->documentation->version . '.headers';

        $headers = $this->getCachedHeaders($cacheKey);

        return $this->prettyArray('sections for ' . $this->documentation->version, $headers);
    }

    public function getSubs()
    {
        $this->documentation->verifyHeader();

        $cacheKey = 'docs.' . $this->documentation->version . '.' . $this->documentation->header . '.subs';

        $subs = $this->getCachedSubs($cacheKey);

        return $this->prettyArray('sub sections for ' . $this->documentation->version . '->' . $this->documentation->header, $subs, 6);
    }

    private function prettyArray($type, $array, $chunkSize = 8)
    {
        $array = $array->chunk($chunkSize);

        return '*Available document ' . $type . " are*:\n" . implode("\n", array_map([$this, 'implode'], $array->toArray()));
    }

    private function implode($array)
    {
        return implode("\t", $array);
    }

    /**
     * @param $header
     *
     * @return string
     */
    private function convertMarkdownToHtml($header)
    {
        $markdown = new CommonMarkConverter;

        $html = html_entity_decode(trim($markdown->convertToHtml($header)));

        return $html;
    }

    /**
     * @param $html
     *
     * @return array
     */
    private function convertHtmlToArray($html)
    {
        $subs = new Collection;
        $dom  = new DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML($html);

        $finder = new DomXPath($dom);
        $nodes  = $finder->query("//ul");

        foreach ($nodes->item(0)->childNodes as $childNode) {
            if (count($childNode->childNodes) > 0) {
                $subs->push(\Str::slug(str_replace('/', 'or', $childNode->childNodes->item(1)->childNodes->item(0)->textContent)));
            }
        }

        return $subs;
    }

    /**
     * @param $cacheKey
     *
     * @return mixed
     */
    public function getCachedVersions($cacheKey)
    {
        $github = $this->github;

        $versions = $this->cacheResponse($cacheKey, function () use ($github) {
            $versions = new Collection($github->api('repo')->branches('laravel', 'docs'));
            $versions = (new Collection(array_fetch($versions->toArray(), 'name')))->reverse();

            return $versions;
        });

        return $versions;
    }

    /**
     * @param $cacheKey
     *
     * @return mixed
     */
    public function getCachedHeaders($cacheKey)
    {
        $github  = $this->github;
        $version = $this->documentation->version;

        $headers = $this->cacheResponse($cacheKey, function () use ($github, $version) {
            $headers = new Collection($github->api('repo')->contents()->show('laravel', 'docs', null, $version));
            $headers = $headers->map(function ($header) {
                if (strtolower($header['name']) == 'readme.md') {
                    return false;
                }

                return substr($header['name'], 0, -3);
            })->filter(function ($header) {
                return $header === false ? false : true;
            });

            return $headers;
        });

        return $headers;
    }

    /**
     * @param $cacheKey
     *
     * @return array
     */
    public function getCachedSubs($cacheKey)
    {
        $github  = $this->github;
        $version = $this->documentation->version;
        $header  = $this->documentation->header;

        $headerDoc = $this->cacheResponse($cacheKey, function () use ($github, $version, $header) {
            $headerDoc = new Collection($github->api('repo')->contents()->show('laravel', 'docs', $header . '.md', $version));
            $headerDoc = base64_decode($headerDoc['content']);

            return $headerDoc;
        });

        $html = $this->convertMarkdownToHtml($headerDoc);
        $subs = $this->convertHtmlToArray($html);

        return $subs;
    }
}