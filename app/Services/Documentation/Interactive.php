<?php namespace App\Services\Documentation;

use App\Services\Contracts\Documentation as DocumentationInterface;
use App\Services\Documentation;

class Interactive implements DocumentationInterface {

    protected $documentation;

    public function start(Documentation $documentation)
    {
        $this->documentation = $documentation;

        $this->checkAndHelp();
    }

    public function sendToSlack()
    {
        // TODO: Implement sendToSlack() method.
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
}