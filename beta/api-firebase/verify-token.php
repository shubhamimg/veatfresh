<?php
include_once('../includes/crud.php');
include_once('../library/jwt.php');
function generate_token(){
	$jwt = new JWT();
	$payload = [
		'iat' => time(), /* issued at time */
		'iss' => 'VeatFresh',
		'exp' => time() + (30*60), /* expires after 1 minute */
		'sub' => 'VeatFresh Authentication'
	];
	$token = $jwt::encode($payload,JWT_SECRET_KEY);
	print_r(json_encode($token));
}
// generate_token();
// $token = generate_token();
// print_r($token);

function verify_token(){
	$jwt = new JWT();
	try{
	   //echo "Token : ".$token = $jwt->getBearerToken();
	   $token = $jwt->getBearerToken();
	}catch(Exception $e){
	    $response['error'] = true;
		$response['message'] = $e->getMessage();
		print_r(json_encode($response));
		return false;
	}
	if(!empty($token)){
		try{
			// JWT::$leeway = 60;
			$payload = $jwt->decode($token, JWT_SECRET_KEY, ['HS256']);
			if(!isset($payload->iss) || $payload->iss != 'eKart'){
	            $response['error']=true;
	            $response['message'] = 'Invalid Hash';
	            print_r(json_encode($response));
			    return false;
			}else{
				return true;
			}
		}catch (Exception $e){
			$response['error'] = true;
			$response['message'] = $e->getMessage();
			print_r(json_encode($response));
			return false;
		}
	}else{
		$response['error'] = true;
		$response['message'] = "Unauthorized access not allowed";
		print_r(json_encode($response));
		return false;
	}
	}
?>