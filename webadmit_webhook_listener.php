<?php
ini_set('memory_limit', '-1');
use Monolog\Logger; //logging to loggly
use Monolog\Handler\StreamHandler;
$log = new Logger('webadmit1');
$log->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));

//Get callback from WebAdmit
$key = 'f148bd717568fe2b2c8fbeec44c44b91';
$userId = '280465';

header('Content-Type: application/json');
$json = file_get_contents('php://input');
$request = json_decode($json, true);

require 'vendor/autoload.php';
define('AMQP_DEBUG', true);
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

$log->info($request);

if($request !== NULL && ($request["pdf_manager_batch"]["pdf_manager_template"]["name"] == "Full_Application" || $request["pdf_manager_batch"]["pdf_manager_template"]["name"] == "Transcripts")){
    $url = parse_url(getenv('CLOUDAMQP_URL'));
    $conn = new AMQPConnection($url['host'], 5672, $url['user'], $url['pass'], substr($url['path'], 1));
    $ch = $conn->channel();

    $exchange = 'amq.direct';
    $queue = 'basic_get_queue';
    $ch->queue_declare($queue, false, true, false, false);
    $ch->exchange_declare($exchange, 'direct', true, true, false);
    $ch->queue_bind($queue, $exchange);

    $msg = new AMQPMessage(json_encode($request), array('content_type' => 'text/plain', 'delivery_mode' => 2));
    $ch->basic_publish($msg, $exchange);
    $log->info("POSTED MESSAGE ". $request);

    $ch->close();
    $conn->close();

    var_dump($request);
    error_log("LISTENER Triggered");
    error_log($request);
    echo "finish";
}else{
    $log->warning("NOTHING to process");
    echo "NOTHING to process";
    exit(1);
}
/*
print_r($request);

//Create connection to Salesforce.com instance
define("USERNAME", "azuckermanre@usa.edu.redev");
define("PASSWORD", "OmnivoFall2018!");
define("SECURITY_TOKEN", "33u454gypb0g8K0bgm33s45W");

require_once ('soapclient/SforcePartnerClient.php');

$mySforceConnection = new SforcePartnerClient();
$mySforceConnection->createConnection("soapclient/partner_sandbox.wsdl.xml");
$mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);

$casIds = array();

//Initialize arrays for Applications
$casIdtoFile = array();
$casIdtoEncodedFile = array();

//Initialize arrays for Transcripts
$casIdDocIdtoFile = array();
$casIdDocIdtoEncodedFile = array();
$documentIdToCasId = array();

$pdfName = $request["pdf_manager_batch"]["pdf_manager_template"]["name"];

//Loop through download hrefs and get file
$i = 1;



foreach($request["pdf_manager_batch"]["download_hrefs"] as $zip_download){

    // Get cURL resource
    $curl = curl_init();

    $dateTimeIndex = date('YmdHis'). '_' . $i;
    $output_filename = "application_" . $dateTimeIndex . '.zip';
    $extract_path = "/myzips/" . $dateTimeIndex . '/';
    $fp = fopen($output_filename, 'w');

    // Set some options
    curl_setopt($curl, CURLOPT_URL, 'https://api.webadmit.org/' . $zip_download);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('x-api-key:' . $key));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_AUTOREFERER, true);

    // Send the request
    $content = curl_exec($curl);

    // Close request to clear up some resources
    curl_close($curl);

    fwrite($fp, $content);
    fclose($fp);

    //unzip file
    $zip = new ZipArchive;
    $res = $zip->open($output_filename);
    if ($res === TRUE) {
      $zip->extractTo(dirname(__FILE__).$extract_path);
      $zip->close();
    }

    //Iterate through extracted files in the extract path
    $dir = dirname(__FILE__).$extract_path.'*';
    $dirNoStar = str_replace('*','',$dir);

  	//Get CAS Id and Document ID if applicable from filename
    foreach(glob($dir) as $file) {
        $fileOnly = str_replace($dirNoStar,'',$file);
        $fileParts = explode("_",$fileOnly);
        $casId = $fileParts[0];

        if(strpos($pdfName, 'Full_Application') !== false) {
            $casIdtoFile[$casId] = $file;
            $casIdtoEncodedFile[$casId] = base64_encode(file_get_contents($file));
            array_push($casIds,$casId);
        }

        if(strpos($pdfName, 'Transcripts') !== false) {
            $documentId = $fileParts[1];
            $documentIdToCasId[$documentId] = $casId;
            $casIdDocIdtoFile[$casId.'~'.$documentId] = $file;
            $casIdDocIdtoEncodedFile[$casId.'~'.$documentId] = base64_encode(file_get_contents($file));
            array_push($casIds,$casId);
        }
    }

    //Create CAS Id set for query string
    $casIdsCommaSeperated = implode("','",$casIds);

    $i++;
}

//Execute Opportunity query to get Salesforce Id and CAS Id
$query = "SELECT Id, Name, CAS_ID__c, CAS_Transcript_Uploaded__c, CAS_Application_Uploaded__c from Opportunity WHERE CAS_ID__c IN ('".$casIdsCommaSeperated."')";
$response = $mySforceConnection->query($query);
$sObjects = array();
$opps = array();

//Create map of CAS Ids to Salesforce records
$casIdToRecord = array();
foreach ($response as $record) {
    $casIdToRecord[$record->fields->CAS_ID__c] = $record;
}

//If no CAS application has been updloaded iterate through response and create
//array of application attachment sObjects to be sent to Salesforce.com
echo '<b>Processing the following files:</b><br/>';
if(strpos($pdfName, 'Full_Application') !== false) {
    foreach ($response as $record) {
        if($record->CAS_Application_Uploaded__c == 'false'){
            $filename = basename($casIdtoFile[$record->fields->CAS_ID__c]);
            echo $filename . '<br/>';
            $data = $casIdtoEncodedFile[$record->fields->CAS_ID__c];

            //The target Attachment Sobject
            $createFields = array(
                'Body' => $data,
                'Name' => $filename,
                'ParentId' => $record->Id,
                'isPrivate' => 'false'
            );
            $sObject = new stdClass();
            $sObject->fields = $createFields;
            $sObject->type = 'Attachment';

            array_push($sObjects,$sObject);
        }
    }
}

//If no CAS transcript has been updloaded iterate through response and create
//array of transcript attachment sObjects to be sent to Salesforce.com
if(strpos($pdfName, 'Transcripts') !== false) {
    foreach ($documentIdToCasId as $doc => $cas) {
        if($casIdtoRecord[$cas]->fields->CAS_Transcript_Uploaded__c == 'false'){
            $filename = basename($casIdDocIdtoFile[$cas.'~'.$doc]);
            echo $filename . '<br/>';
            $data = $casIdDocIdtoEncodedFile[$cas.'~'.$doc];

            // the target Sobject
            $createFields = array(
                'Body' => $data,
                'Name' => $filename,
                'ParentId' => $casIdtoRecord['$cas']->fields->Id,
                'isPrivate' => 'false'
            );
            $sObject = new stdClass();
            $sObject->fields = $createFields;
            $sObject->type = 'Attachment';

            array_push($sObjects,$sObject);
        }
    }
}

//Create attachments and update Opportunities
echo '<b>Creating Attachments for Salesforce:</b><br/>';
foreach ($sObjects as $attachment) {
    $createResponse = $mySforceConnection->create(array($attachment));

    //Get ready to update Opportunity records based on successful response
    if ($createResponse[0]->success && strpos($attachment->fields['Name'], 'Transcript') !== false){
        $fieldsToUpdate = array(
            'CAS_Transcript_Uploaded__c' => 'true'
        );
        $opp = new stdClass();
        $opp->fields = $fieldsToUpdate;
        $opp->type = 'Opportunity';
        $opp->id = $attachment->fields['ParentId'];

        array_push($opps,$opp);
    }
    else if($createResponse[0]->success && strpos($attachment->fields['Name'], 'Application') !== false){
        $fieldsToUpdate = array(
            'CAS_Application_Uploaded__c' => 'true'
        );
        $opp = new stdClass();
        $opp->fields = $fieldsToUpdate;
        $opp->type = 'Opportunity';
        $opp->Id = $attachment->fields['ParentId'];

        array_push($opps,$opp);
    }
    print_r($createResponse);
    echo '<br/><br/>';

    //Update Opportunity records
    echo '<b>Updating Opportunities:</b><br/>';
    $updateOppResponse = $mySforceConnection->update($opps);
    foreach($updateOppResponse as $myOpp) {
        print_r($myOpp);
        echo '<br/>';
    }
}


?>

*/
