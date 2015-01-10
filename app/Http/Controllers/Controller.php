<?php namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesCommands;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller extends BaseController {

    use DispatchesCommands, ValidatesRequests;

    protected $accessKey;

    public function __construct()
    {
        $this->accessKey   = getenv('API_KEY');

        $this->beforeFilter('@filterRequests');
	}

    public function filterRequests($route, $request)
    {
        $key = $request->get('key');

        if ($key == null || $key != $this->accessKey) {
            return \Redirect::home();
        }
    }

}
