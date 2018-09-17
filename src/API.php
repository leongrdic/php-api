<?php
// php-api by Leon, MIT License

namespace Le;

class API {
	public static $resources, $session, $options = [ 'list_limit' => 100 ];
	
	const ACCESS_DENY = 0;
	const ACCESS_GRANT = 100;
	const ACCESS_PROTECTED = 200;
	const ACCESS_PRIVATE = 300;
	
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
	
	public static function handle($method, $resource, $id, $args, $data){
		
		if(!isset(self::$resources[$resource])) return self::error(self::HTTP_NOT_FOUND, 'resource not found');
		$meta = self::$resources[$resource];
		
		if(ctype_alpha($id) || strpos($id, '_') !== false){ // calling an action instead of id, works for all methods
			$action = 'api_' . strtolower($method) . '_' . $id;
			
			if(!method_exists($meta['object'], $action)) return self::error(HTTP_NOT_IMPLEMENTED, 'unknown action');
			
			$params = [
				'session' => self::$session,
				'args' => $args,
				'data' => $data
			];
			
			return call_user_func([$meta['object'], $action], $params);
		}else if(empty($id) && $method === 'GET'){ // calling without id, works only for GET method - list
		
			if(!method_exists($meta['object'], $action)) return self::error(HTTP_NOT_IMPLEMENTED, 'listing unavailable at the moment');
			
		}else if(!empty($id)){ // calling for one or more ids, works for GET and POST
			if($method === 'GET'){
				
				if(strpos($id, ',') !== false){ // getting multiple objects
					
					$ids = explode(',', $id);
					
					$responses = [];
					foreach($ids as $val){
						array_push($responses, self::handle($method, $resource, $val, $args, $data));
					}
					
					return self::response(self::HTTP_MULTI, $responses);
					
				}else{ // getting a single object
					if(strpos($id, ':') !== false){
						$hash = explode(':', $id);
						$id = $hash[0];
						$hash = isset($hash[1]) ? $hash[1] : null;
					}
					
					$access = $meta['object']::api_access($id, self::$session);
					if($access <= self::ACCESS_DENY) return self::error(self::HTTP_FORBIDDEN, 'access denied to this object', null, $id);
					
					if(!isset($meta['cache'])) $meta['cache'] = 0;
					
					if(isset($hash)){
						$object_hash = $meta['object']::hash($id);
						if($hash === $object_hash) return self::response(self::HTTP_NOT_MODIFIED, null, $meta['cache'], $id);
					}
					
					try{ $res = new $meta['object']($id); }
					catch(Exception $e){
						if($e->getModule() === 30 && ($e->getCode() === 15 || $e->getCode() === 17)) return self::error(self::HTTP_NOT_FOUND, 'object not found', null, $id);
						throw $e;
					}
					$data = $res->get();
					
					foreach($data as $column => $value){
						$column_meta = isset($meta['props'][$column]) ? $meta['props'][$column] : [];
						$column_access = isset($column_meta['read']) ? $column_meta['read'] : self::ACCESS_GRANT;
						if($access < $column_access) unset($data[$column]);
					}
					
					return self::response(self::HTTP_OK, [$data], $meta['cache'], $id);
					
				}
				
			}else if($method === 'POST'){
				$access = $meta['object']::api_access($id, self::$session);
				if($access <= self::ACCESS_DENY) return self::error(self::HTTP_FORBIDDEN, 'access denied to this object');
				
				try{ $res = new $meta['object']($id); }
				catch(Exception $e){
					if($e->getModule() === 30 && ($e->getCode() === 15 || $e->getCode() === 17)) return self::error(self::HTTP_NOT_FOUND, 'object not found');
					throw $e;
				}
				
				foreach($data as $column => $value){
					$column_meta = isset($meta['props'][$column]) ? $meta['props'][$column] : [];
					$column_access = isset($column_meta['write']) ? $column_meta['write'] : self::ACCESS_PRIVATE;
					if($access < $column_access) return self::error(self::HTTP_FORBIDDEN, 'access denied for writing the field \'' . $column . '\'');
					
					// TODO check format
				}
				
				$res->set($data);
				
				return self::response(self::HTTP_NO_CONTENT);
			}
		}
		
		return self::error(self::HTTP_NOT_IMPLEMENTED, 'unsupported method');
	}
	
	public static function response($status, $body = null, $cache = null, $id = null){
		$response = [
			'status' => $status,
			'body' => $body
		];
		if(!is_null($id)) $response = ['id' => $id] + $response; // prepend the id
		if(!is_null($cache)) $response['cache'] = $cache;
		
		return $response;
	}
	
	public static function error($status, $message = '', $code = null, $id = null){
		$body = [
			'message' => $message,
			'code' => $code
		];
		
		return self::response($status, $body, null, $id);
	}
	
	public static function send_json($response){
		http_response_code($response['status']);
		header('Content-Type: application/json');
		if(isset($response['cache'])) header('X-Cache: ' . $response['cache']);
		if(!empty($response['body'])) echo json_encode($response['body']);
		exit;
	}
	
	public static function send_text($response){
		http_response_code($response['status']);
		header('Content-Type: text/plain');
		if(!empty($response['body'])) echo $response['body'];
		exit;
	}
}