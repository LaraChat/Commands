<?php namespace App\Services;

use Frlnc\Slack\Core\Commander;
use Frlnc\Slack\Http\CurlInteractor;
use Frlnc\Slack\Http\SlackResponseFactory;
use Illuminate\Config\Repository;

/**
 * @method execute($command, array $parameters = [])
 * @method setToken($token)
 * @method static format($string)
 */
class Slack {

    public function __construct(Repository $config)
    {
        $interactor = new CurlInteractor;
        $interactor->setResponseFactory(new SlackResponseFactory);

        $this->commander = new Commander($config->get('services.slack.token'), $interactor);
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->commander, $name], $arguments);
    }

}