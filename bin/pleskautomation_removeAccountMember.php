<?php
include_once(__DIR__.'/../../../include/functions.inc.php');
$ppaConnector = get_webhosting_ppa_instance();
// let us form an array with account data
$user_id = 390;
$request = array(
	'user_id' => $user_id,
);
$result = $ppaConnector->removeAccountMember($request);
echo "Result:";
var_dump($result);
echo "\n";
// Parse the response
try {
	PPAConnector::checkResponse($result);
} catch (Exception $e) {
	echo 'Caught exception: '.$e->getMessage()."\n";
}
echo "Success.\n";
