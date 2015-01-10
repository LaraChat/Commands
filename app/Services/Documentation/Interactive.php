<?php namespace App\Services\Documentation;

use App\Services\Contracts\Documentation as DocumentationInterface;
use App\Services\Documentation;
use Github\Client;
use Illuminate\Support\Collection;

class Interactive implements DocumentationInterface {

    protected $documentation;

    protected $github;

    protected $results;

    public function start(Documentation $documentation, Client $github)
    {
        $this->documentation = $documentation;
        $this->github        = $github;

        $this->results = $this->checkAndHelp();

        dd($this->results);

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
        $versions = new Collection(array_fetch($versions->toArray(), 'name'));

        return $this->prettyArray('versions', $versions);
    }

    private function getHeaders()
    {
        $headers = new Collection($this->github->api('repo')->contents()->show('laravel', 'docs', null, $this->documentation->version));
        $headers = $headers->map(function ($header) {
            return substr($header['name'], 0, -3);
        });

        return $this->prettyArray('sections', $headers);
    }

    private function prettyArray($type, $array)
    {
        $array = $array->chunk(4);

        return 'Available documents '. $type ." are:\n" . implode("\n", array_map([$this, 'implode'], $array->toArray()));
    }

    private function implode($array)
    {
        return implode("\t", $array);
    }
}