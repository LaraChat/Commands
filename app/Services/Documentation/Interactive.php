<?php namespace App\Services\Documentation;

use App\Services\Contracts\Documentation as DocumentationInterface;
use App\Services\Documentation;
use DOMDocument;
use DOMXPath;
use Github\Client;
use Illuminate\Support\Collection;
use League\CommonMark\CommonMarkConverter;

class Interactive implements DocumentationInterface {

    protected $documentation;

    protected $github;

    protected $results;

    public function start(Documentation $documentation, Client $github)
    {
        $this->documentation = $documentation;
        $this->github        = $github;

        $this->results = $this->checkAndHelp();

        return $this->sendToSlack();
    }

    public function sendToSlack()
    {
        return $this->results;
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

    private function getVersions()
    {
        $versions = new Collection($this->github->api('repo')->branches('laravel', 'docs'));
        $versions = (new Collection(array_fetch($versions->toArray(), 'name')))->reverse();

        return $this->prettyArray('versions', $versions);
    }

    private function getHeaders()
    {
        $headers = new Collection($this->github->api('repo')->contents()->show('laravel', 'docs', null, $this->documentation->version));
        $headers = $headers->map(function ($header) {
            if (strtolower($header['name']) == 'readme.md') {
                return false;
            }

            return substr($header['name'], 0, -3);
        })->filter(function ($header) {
            return $header === false ? false : true;
        });

        return $this->prettyArray('sections', $headers);
    }

    private function getSubs()
    {
        $header = new Collection($this->github->api('repo')->contents()->show('laravel', 'docs', $this->documentation->header . '.md', $this->documentation->version));
        $header = base64_decode($header['content']);

        $html = $this->convertMarkdownToHtml($header);

        $subs = $this->convertHtmlToArray($html);

        return $this->prettyArray('sub sections', $subs, 6);
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

        $finder    = new DomXPath($dom);
        $classname = "task-list";
        $nodes     = $finder->query("//ul");

        foreach ($nodes->item(0)->childNodes as $childNode) {
            if (count($childNode->childNodes) > 0) {
                $subs->push(\Str::slug(str_replace('/', 'or', $childNode->childNodes->item(1)->childNodes->item(0)->textContent)));
            }
        }

        return $subs;
    }
}