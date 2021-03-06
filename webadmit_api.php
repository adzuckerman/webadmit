<?php

ini_set('memory_limit', '-1');
use Monolog\Logger; //logging to loggly
use Monolog\Handler\StreamHandler;
$log = new Logger('webadmit1');
$log->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));

//$userIds = array('280465','280464');
$userIds = array();
for ($i = 1; $i <= intval(getenv('NO_CAS_USERS')); $i++) {
    array_push($userIds, getenv('USER_ID_' . $i));
}

function showTemplates($userId){
    $key = getenv('WEBADMIT_APIKEY');
    //$userId = '280465';

    // Get cURL resource
    $curl = curl_init();
    // Set some options - we are passing in a useragent too here
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => 'https://api.webadmit.org/api/v1/user_identities/'.$userId.'/pdf_manager_templates',
        CURLOPT_HTTPHEADER => array('x-api-key:' . $key),
    ));
    // Send the request & save response to $resp
    $resp = curl_exec($curl);
    // Close request to clear up some resources
    curl_close($curl);

    $result = json_decode($resp, $assoc = true);

    //var_dump($result["pdf_manager_templates"]);

    foreach($result["pdf_manager_templates"] as $template){
       initRun($template["id"],$userId);
    }
    return $result;
}

function initRun($id,$userId){
    $key = 'f148bd717568fe2b2c8fbeec44c44b91';
    //$userId = '280465';
    // $callback = 'https://webhook.site/dbc2fdef-0ccf-41ce-b993-60b0ac3ffda3';
    $callback = getenv('CALLBACK_URL');

    $data = array("pdf_manager_template_id" => $id, "callback" => $callback);
    $data_string = json_encode($data);

    // Get cURL resource
    $curl = curl_init();
    // Set some options - we are passing in a useragent too here
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => 'https://api.webadmit.org/api/v1/user_identities/'.$userId.'/pdf_manager_batches',
        CURLOPT_HTTPHEADER => array(
            'x-api-key:' . $key,
            'Content-Type:application/json'),
        CURLOPT_POSTFIELDS => $data_string,
    ));
    // Send the request & save response to $resp
    $resp = curl_exec($curl);
    $log->info("Executed CURL User id ". $userId . "  CURLOPT_POSTFIELDS : " . $data_string . "  CURLOPT_HTTPHEADER : " . $key);

    // Close request to clear up some resources
    curl_close($curl);

    $result = json_decode($resp, $assoc = true);
    $log->info($result);

    var_dump($result);

    return $result;
}

foreach($userIds as $userId){
    showTemplates($userId);
}

echo "DONE";
?>
