<?php namespace App\Http\Controllers\Command;

use App\Http\Controllers\Controller;
use App\Services\Documentation;
use App\Services\Slack;
use DOMDocument;
use DOMXPath;
use Github\Client;
use Illuminate\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        parent::__construct();

        $this->markdown = $markdown;
        $this->github   = $github;
        $this->cache    = $cache;
        $this->slack    = $slack;
    }

    public function index(Request $request)
    {
        $documentation = new Documentation($request->get('text'));

        return $documentation->handle();

        dd($details);
        $version = $details[0];

        $url = $this->getDocumentsUrl($version);

        dd(array_search('!', $details));

        if (! isset($details[1])) {
            return $this->getDocumentOptions($url, $version);
        }
        if (! isset($details[2])) {
            return $this->getDocumentAreaOptions($url, $details[1]);
        }

        return $this->documentLink($url, $details[1], $details[2]);
    }

    protected function getDocumentOptions($url, $version)
    {
        $cacheKey = 'laravel.docs.main';

        // @todo - remove this before going live
        $this->cache->forget($cacheKey);

        if ($this->cache->has($cacheKey)) {
            $documents = $this->cache->get($cacheKey);
        } else {
            $documents = new Collection($this->github->api('repo')->contents()->show('laravel', 'docs', null, $version));
            $documents = $documents->map(function ($document) {
                return ucwords(substr($document['name'], 0, -3));
            });

            $this->cache->put($cacheKey, $documents, $this->cacheTime);
        }

        $documents = $documents->chunk(4);

        //dd($documents);

        return 'The following options are available for version ' . $version . "<br />" . implode("<br />", array_map('implode', $documents->toArray()));

        $response = $this->slack->execute('chat.postMessage', [
            'channel' => 'U03AGP8V4',
            'text'    => implode(', ', $documents->toArray())
        ]);

        dump($response);
        dump($documents);
        die;

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

    private function markdownTest($version)
    {
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

}