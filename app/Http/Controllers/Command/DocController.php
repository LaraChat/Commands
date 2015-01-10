<?php namespace App\Http\Controllers;

use App\Services\Slack;
use DOMDocument;
use DOMXPath;
use Github\Client;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Eloquent\Collection;
use League\CommonMark\CommonMarkConverter;

class DocController extends Controller {

    /**
     * @var CommonMarkConverter
     */
    private $markdown;

    /**
     * @var Client
     */
    private $github;

    /**
     * @var Repository
     */
    private $cache;

    /**
     * @var int
     */
    private $cacheTime = 60;

    /**
     * @var Slack
     */
    private $slack;

    public function __construct(CommonMarkConverter $markdown, Client $github, Repository $cache, Slack $slack)
    {
        $this->markdown = $markdown;
        $this->github   = $github;
        $this->cache    = $cache;
        $this->slack    = $slack;
    }

    public function index($version = 'master', $main = null, $sub = null)
    {
        $url = $this->getDocumentsUrl($version);

        if ($main == null) {
            return $this->getDocumentOptions($url);
        }
        if ($sub == null) {
            return $this->getDocumentAreaOptions($url, $main);
        }

        return $this->documentLink($url, $main, $sub);
        $docs = file_get_contents('https://github.com/laravel/docs/blob/' . $version . '/eloquent.md');
        $html = html_entity_decode(trim($this->markdown->convertToHtml($docs)));

        //dd($html);

        //dd(trim($html));

        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $finder    = new DomXPath($dom);
        $classname = "task-list";
        $nodes     = $finder->query("//ul[contains(@class, '$classname')]");

        echo '<strong>Eloquent</strong><br />';
        foreach ($nodes->item(0)->childNodes as $childNode) {
            if (count($childNode->childNodes) > 0) {
                echo 'http://laravel.com/docs/4.2/eloquent#' . \Str::slug(str_replace('/', 'or', $childNode->childNodes->item(0)->childNodes->item(0)->textContent)) . '<br />';
            }
        }

        //dd($nodes->item(0)->childNodes->item(0)->childNodes->item(0)->childNodes->item(0));
    }

    protected function getDocumentOptions($url)
    {
        $cacheKey = 'laravel.docs.main';

        // @todo - remove this before going live
        $this->cache->forget($cacheKey);

        if ($this->cache->has($cacheKey)) {
            $documents = $this->cache->get($cacheKey);
        } else {
            $documents = new Collection($this->github->api('repo')->contents()->show('laravel', 'docs'));
            $documents = $documents->map(function ($document) {
                return $document['name'];
            });

            $this->cache->put($cacheKey, $documents, $this->cacheTime);
        }

        $response = $this->slack->execute('users.list');

        dd($response->getBody());

        if ($response['ok']) {
            dd('did it');
        } else {
            dd('failed');
        }
    }

    private function getDocumentsUrl($version)
    {
        $baseUrl = 'https://github.com/laravel/docs/blob/';

        return $baseUrl . $version . '/';
    }

    private function getFileNames($file)
    {
        return $file['name'];
    }

}