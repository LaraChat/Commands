<?php namespace App\Services;

use Frlnc\Slack\Core\Commander;
use Frlnc\Slack\Http\CurlInteractor;
use Frlnc\Slack\Http\SlackResponseFactory;
use Illuminate\Config\Repository;

class Slack {

    public function __construct(Repository $config)
    {
        $interactor = new CurlInteractor;
        $interactor->setResponseFactory(new SlackResponseFactory);

        $this->commander = new Commander($config->get('slack.token'), $interactor);
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->commander, $name], $arguments);
    }

}