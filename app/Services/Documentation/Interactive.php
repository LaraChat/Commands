<?php namespace App\Services\Documentation;

use App\Services\Contracts\Documentation as DocumentationInterface;
use App\Services\Documentation;
use DOMDocument;
use DOMXPath;
use Github\Client;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Collection;
use League\CommonMark\CommonMarkConverter;

class Interactive implements DocumentationInterface {

    /**
     * @var Documentation
     */
    protected $documentation;

    /**
     * @var Client
     */
    protected $github;

    /**
     * @var Repository
     */
    protected $cache;

    /**
     * @var string
     */
    protected $results;

    /**
     * @var int
     */
    private $cacheTime = 60;

    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * @param Documentation    $documentation
     * @param Client           $github
     * @param Repository       $cache
     * @param ConfigRepository $config
     */
    public function __construct(Documentation $documentation, Client $github, Repository $cache, ConfigRepository $config)
    {
        $this->documentation = $documentation;
        $this->github        = $github;
        $this->cache         = $cache;
        $this->config        = $config;
    }

    /**
     * Find the step we are at.  Send the needed result to slack.
     *
     * @return string
     */
    public function start()
    {
        $this->results = $this->checkAndHelp();

        return $this->sendToSlack();
    }

    /**
     * For helper text, you respond to slack with a string.
     * This string is only visible to the user who called the command.
     *
     * @return string
     */
    public function sendToSlack()
    {
        return $this->results;
    }

    /**
     * Handle caching and fetching the response.
     *
     * @param $key
     * @param $callback
     *
     * @return mixed
     */
    public function cacheResponse($key, $callback)
    {
        // If cache has the key, return it.
        if ($this->cache->has($key) && $this->cache->get($key) != false) {
            return $this->cache->get($key);
        }

        // Get the data and set it in cache.
        $results = $callback();

        $this->cache->put($key, $results, $this->cacheTime);

        return $results;
    }

    /**
     * Check for the existence of an option, print the help.
     *
     * @return string
     */
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

    /**
     * Get the available versions that can be called.
     *
     * @return string
     */
    public function getVersions()
    {
        $cacheKey = $this->config->get('larabot.cacheKeys.docs.version');

        $versions = $this->getCachedVersions($cacheKey);

        return $this->prettyArray('versions', $versions);
    }

    /**
     * Get the available headers that can be called.
     *
     * @return string
     */
    public function getHeaders()
    {
        $this->documentation->verifyVersion();

        $cacheKey = sprintf($this->config->get('larabot.cacheKeys.docs.header'), $this->documentation->version);

        $headers = $this->getCachedHeaders($cacheKey);

        return $this->prettyArray('sections for ' . $this->documentation->version, $headers);
    }

    /**
     * Get the available sub headers that can be called.
     *
     * @return string
     */
    public function getSubs()
    {
        $this->documentation->verifyHeader();

        $cacheKey = sprintf($this->config->get('larabot.cacheKeys.docs.sub'), $this->documentation->version, $this->documentation->header);

        $subs = $this->getCachedSubs($cacheKey);

        return $this->prettyArray('sub sections for ' . $this->documentation->version . '->' . $this->documentation->header, $subs, 6);
    }

    /**
     * Take the array, break it up into chunked arrays.
     * Implode to form tabbed strings with each chunk on it's
     * own line.  Makes it easier to see in slack.
     *
     * @param     $type
     * @param     $array
     * @param int $chunkSize
     *
     * @return string
     */
    private function prettyArray($type, $array, $chunkSize = 8)
    {
        $array = $array->chunk($chunkSize);

        return '*Available document ' . $type . " are*:\n" . implode("\n", array_map([$this, 'implode'], $array->toArray()));
    }

    /**
     * Very simply used for the prettyArray map call.
     *
     * @param $array
     *
     * @return string
     */
    private function implode($array)
    {
        return implode("\t", $array);
    }

    /**
     * Convert markdown syntax into html.
     *
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
     * Grab the sub sections from valid HTML.
     *
     * @param $html
     *
     * @return array
     */
    private function getSubHeadersFromHtml($html)
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
     * Used to get or set the cache for available versions.
     *
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
     * Used to get or set the cache for available headers.
     *
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
     * Used to get or set the cache for available sub headers.
     *
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
        $subs = $this->getSubHeadersFromHtml($html);

        return $subs;
    }
}