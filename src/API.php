<?php namespace Le;
// php-api by Leon, MIT License

final class API {
	private function __construct() {}

	public static $resources = [], $session = null;

	const HTTP_OK = 200;
	const HTTP_NO_CONTENT = 204;
	const HTTP_MULTI = 207;
	const HTTP_NOT_MODIFIED = 304;
	const HTTP_BAD_REQUEST = 400;
	const HTTP_FORBIDDEN = 403;
	const HTTP_NOT_FOUND = 404;
	const HTTP_SERVER_ERROR = 500;
	const HTTP_NOT_IMPLEMENTED = 501;

	public static function init($resources, $session = null){
		self::$session = $session;
		self::$resources = $resources;
	}

	public static function handle($method, $path, $query = [], $data = []){
		try {
			if(!isset($path[0])) throw new APIException(API::HTTP_NOT_FOUND, 'resource not found');
			if(!isset($path[1])) throw new APIException(API::HTTP_NOT_IMPLEMENTED, 'unknown action');

			if(!isset(self::$resources[$path[0]]))
				throw new APIException(API::HTTP_NOT_FOUND, 'resource not found');

			$class = self::$resources[$path[0]];
			$function = strtolower($method) . '_' . $path[1];

			if(!method_exists($class, $function))
				throw new APIException(API::HTTP_NOT_IMPLEMENTED, 'unknown action');

			array_shift($path); array_shift($path);

			$response = call_user_func([ $class, $function ], [
				'path' => $path,
				'query' => $query,
				'data' => $data
			]);

			if(!($response instanceof APIResponse))
				throw new APIException(API::HTTP_SERVER_ERROR, "no response from action");

			return $response;
		} catch(APIException $e){
			return $e->getResponse();
		}
	}

	public static function validate($params, $settings){
		if(isset($settings['path']) && is_array($settings['path'])){
			if(count($params['path']) > count($settings['path']))
				throw new APIException(API::HTTP_NOT_FOUND, "request validation failed: path not found");

			foreach($settings['path'] as $key => $uu)
					self::filter($params['path'][$key] ?? '', $settings['path'][$key], "path_" . $key);
		}

		if(isset($settings['query']) && is_array($settings['query']))
			foreach($settings['query'] as $key => $uu)
					self::filter($params['query'][$key] ?? '', $settings['query'][$key], "query_" . $key);

		if(isset($settings['data']) && is_array($settings['data']))
			foreach($settings['data'] as $key => $uu)
					self::filter($params['data'][$key] ?? '', $settings['data'][$key], "data_" . $key);
	}

	public static function filter($var, $filter, $ref = ''){
		$msg = 'request validation failed: ' . ($ref?$ref.' ':'');

		if((!isset($filter['empty']) || !$filter['empty']) && $var === '')
			throw new APIException(API::HTTP_BAD_REQUEST, $msg . "mustn't be empty", $ref);
		else if(isset($filter['empty']) && $filter['empty'] && $var === '') return;

		if(isset($filter['allowhtml']) && $filter['allowhtml'] === false)
			if(strip_tags($var) != $var) throw new APIException(API::HTTP_BAD_REQUEST, $msg . "doesn't allow html tags", $ref);

		if(isset($filter['type'])){
			if($filter['type'] === 'alnum' && !ctype_alnum($var))
				throw new APIException(API::HTTP_BAD_REQUEST, $msg . "has to be alphanumeric", $ref);
			else if($filter['type'] === 'alpha' && !ctype_alpha($var))
				throw new APIException(API::HTTP_BAD_REQUEST, $msg . "has to be alphabetic", $ref);
			else if($filter['type'] === 'digit' && !ctype_digit($var))
				throw new APIException(API::HTTP_BAD_REQUEST, $msg . "has to consist only of digits", $ref);
		}

		if(isset($filter['filter']) && !filter_var($var, $filter['filter']))
			throw new APIException(API::HTTP_BAD_REQUEST, $msg . "didn't pass filter", $ref);
		if(isset($filter['regex']) && !preg_match($filter['regex'], $var))
			throw new APIException(API::HTTP_BAD_REQUEST, $msg . "didn't pass regex", $ref);

		if(isset($filter['length_min']) && strlen($var)<$filter['length_min'])
			throw new APIException(API::HTTP_BAD_REQUEST, $msg . "must be min " . $filter['length_min'] . " chars long", $ref);
		if(isset($filter['length_max']) && strlen($var)>$filter['length_max'])
			throw new APIException(API::HTTP_BAD_REQUEST, $msg . "must be max " . $filter['length_max'] . " chars long", $ref);

		if(isset($filter['min']) && $var<$filter['min'])
			throw new APIException(API::HTTP_BAD_REQUEST, $msg . "must be min " . $filter['min'], $ref);
		if(isset($filter['max']) && $var>$filter['max'])
			throw new APIException(API::HTTP_BAD_REQUEST, $msg . "must be max " . $filter['max'], $ref);
	}
}

class APIResponse {
	private $status, $body, $cache, $id;
	public function __construct($status, $body = null, $cache = null, $id = null){
		$this->status = $status;
		$this->body = $body;
		$this->cache = $cache;
		$this->id = $id;
	}

	public function array(){
		$response = [
			'status' => $this->status,
			'body' => $this->body
		];
		if(!is_null($this->id)) $response = ['id' => $this->id] + $response; // prepend the id
		if(!is_null($this->cache)) $response['cache'] = $this->cache;

		return $response;
	}

	public function json(){
		http_response_code($this->status);
		header('Content-Type: application/json');
		if(!is_null($this->cache)) header('X-Cache: ' . $this->cache);
		if(!is_null($this->body)) echo json_encode($this->body);
		exit;
	}

	public function text(){
		http_response_code($this->status);
		header('Content-Type: text/plain');
		echo $this->body;
		exit;
	}

	public function custom($type){
		http_response_code($this->status);
		header('Content-Type: ' . $type);
		echo $this->body;
		exit;
	}
}

class APIException extends \Exception {
	private $response;

	public function __construct($status, $message = '', $api_code = 0, $api_entity_id = null, $previous = null){
		$body = [
			'message' => $message,
			'code' => $api_code
		];
		$this->response = new APIResponse($status, $body, null, $api_entity_id);

		parent::__construct($message, $status, $previous);
	}

	public function getResponse(){
		return $this->response;
	}
}
