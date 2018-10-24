<?php
ini_set('memory_limit', '-1');
$key = getenv('WEBADMIT_APIKEY'); 
//Functions in this file can run for a long time!
//This was tested for up to 5 minutes of runtime
function process_request($request){
    echo "Startin to process";

    echo $request;
    //Create connection to Salesforce.com instance
    define("USERNAME", getenv('SF_USERNAME'));
    define("PASSWORD", getenv('SF_PASSWORD'));
    define("SECURITY_TOKEN", getenv('SF_SECURITY_TOKEN'));
    require_once ('soapclient/SforcePartnerClient.php');
    $mySforceConnection = new SforcePartnerClient();
    $mySforceConnection->createConnection("soapclient/partner_sandbox.wsdl.xml");
    $mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);
    $casIds = array();
    $docIds = array();
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
    echo "PDF -> " . $pdfName;
    foreach($request["pdf_manager_batch"]["download_hrefs"] as $zip_download){
        echo "HERE 32";
        var_dump($zip_download);
        // Get cURL resource
        $dateTimeIndex = date('YmdHis'). '_' . $i;
        $output_filename = $pdfName . $dateTimeIndex . '.zip';
        $extract_path = "/myzips/" . $pdfName . $dateTimeIndex . '/';
        $fp = fopen($output_filename, 'w');

        // Set some options
        $key = getenv('WEBADMIT_APIKEY');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://api.webadmit.org'.$zip_download);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('x-api-key:' . $key));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        // Send the request
        $content = curl_exec($curl);

        if($content == " "){
            echo "No content";
            return 0;
        }
        // Close request to clear up some resources
        curl_close($curl);
        fwrite($fp, $content);
        fclose($fp);

        //unzip file
        $zip = new ZipArchive;
        $res = $zip->open($output_filename);

        if ($res === TRUE) {
            echo "just unzipped";
          $zip->extractTo(dirname(__FILE__).$extract_path);
          $zip->close();
        }else {
            echo "issue with unzipping";
            return 0;
        }
        //Iterate through extracted files in the extract path
        $dir = dirname(__FILE__).$extract_path.'*';
        $dirNoStar = str_replace('*','',$dir);
      	//Get CAS Id and Document ID if applicable from filename
        foreach(glob($dir) as $file) {
            echo " $ file";
            $fileOnly = str_replace($dirNoStar,'',$file);
            $fileParts = explode("_",$fileOnly);
            $casId = $fileParts[0];
            if(strpos($pdfName, 'Full_Application') !== false) {
                echo "FILE IS Full_Application ";
                $casIdtoFile[$casId] = $file;
                $casIdtoEncodedFile[$casId] = base64_encode(file_get_contents($file));
                array_push($casIds,$casId);
            }
            else if (strpos($pdfName, 'Transcripts') !== false) {
                echo "FILE IS Transcripts";
                $documentId = $fileParts[1];
                $documentIdToCasId[$documentId] = $casId;
                $casIdDocIdtoFile[$casId.'~'.$documentId] = $file;
                $casIdDocIdtoEncodedFile[$casId.'~'.$documentId] = base64_encode(file_get_contents($file));
                array_push($casIds,$casId);
                array_push($docIds,$documentId);
            }
            else {
                return 0;
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
    $casIdDocIdToRecord = array();
    foreach ($response as $record) {
        foreach ($docIds as $docId){
            $casIdDocIdToRecord[$record->fields->CAS_ID__c.'~'. $docId] = $record;
        }
    }
    //If no CAS application has been updloaded iterate through response and create
    //array of application attachment sObjects to be sent to Salesforce.com
    echo "LINE 107 LINE";
    if(strpos($pdfName, 'Full_Application') !== false) {
        foreach ($response as $record) {
            if($record->fields->CAS_Application_Uploaded__c == 'false'){
                $filename = basename($casIdtoFile[$record->fields->CAS_ID__c]);
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

                //HERE
                $createResponse = $mySforceConnection->create(array($sObject));

                //Get ready to update Opportunity records based on successful response
                if ($createResponse[0]->success && strpos($sObject->fields['Name'], 'Transcript') !== false){
                    $fieldsToUpdate = array(
                        'CAS_Transcript_Uploaded__c' => 'true'
                    );
                    $opp = new stdClass();
                    $opp->fields = $fieldsToUpdate;
                    $opp->type = 'Opportunity';
                    $opp->Id = $sObject->fields['ParentId'];

                    array_push($opps,$opp);
                }
                else if($createResponse[0]->success && strpos($sObject->fields['Name'], 'Application') !== false){
                    $fieldsToUpdate = array(
                        'CAS_Application_Uploaded__c' => 'true'
                    );
                    $opp = new stdClass();
                    $opp->fields = $fieldsToUpdate;
                    $opp->type = 'Opportunity';
                    $opp->Id = $attachment->fields['ParentId'];
                    array_push($opps,$opp);
                }

                $updateOppResponse = $mySforceConnection->update($opps);

            }
        }
    }
    echo "LINE 155 LINE";
    //If no CAS transcript has been updloaded iterate through response and create
    //array of transcript attachment sObjects to be sent to Salesforce.com
    if(strpos($pdfName, 'Transcripts') !== false) {
        foreach ($documentIdToCasId as $doc => $cas) {
            echo "In foreach 155";
            var_dump($casIdDocIdToRecord[$cas.'~'.$doc]->fields->CAS_Transcript_Uploaded__c);
            if($casIdDocIdToRecord[$cas.'~'.$doc]->fields->CAS_Transcript_Uploaded__c == 'false'){
                $filename = basename($casIdDocIdtoFile[$cas.'~'.$doc]);
                echo $filename . '<br/>';
                $data = $casIdDocIdtoEncodedFile[$cas.'~'.$doc];
                // the target Sobject
                $createFields = array(
                    'Body' => $data,
                    'Name' => $filename,
                    'ParentId' => $casIdDocIdToRecord[$cas.'~'.$doc]->Id,
                    'isPrivate' => 'false'
                );
                $sObject = new stdClass();
                $sObject->fields = $createFields;
                $sObject->type = 'Attachment';

                //HERE

                $createResponse = $mySforceConnection->create(array($sObject));

                //Get ready to update Opportunity records based on successful response
                if ($createResponse[0]->success && strpos($sObject->fields['Name'], 'Transcript') !== false){
                    $fieldsToUpdate = array(
                        'CAS_Transcript_Uploaded__c' => 'true'
                    );
                    $opp = new stdClass();
                    $opp->fields = $fieldsToUpdate;
                    $opp->type = 'Opportunity';
                    $opp->Id = $sObject->fields['ParentId'];
                    array_push($opps,$opp);
                }
                else if($createResponse[0]->success && strpos($sObject->fields['Name'], 'Application') !== false){
                    $fieldsToUpdate = array(
                        'CAS_Application_Uploaded__c' => 'true'
                    );
                    $opp = new stdClass();
                    $opp->fields = $fieldsToUpdate;
                    $opp->type = 'Opportunity';
                    $opp->Id = $sObject->fields['ParentId'];
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
        }
    }
    echo '<b>Created Attachments for Salesforce:</b><br/>';
    return true;
}
echo "HERE";
require 'vendor/autoload.php';
define('AMQP_DEBUG', true);
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
$url = parse_url(getenv('CLOUDAMQP_URL'));
while(true){
    try{
        $conn = new AMQPConnection($url['host'], 5672, $url['user'], $url['pass'], substr($url['path'], 1));
        $ch = $conn->channel();
        $exchange = 'amq.direct';
        $queue = 'basic_get_queue';
        $ch->queue_declare($queue, false, true, false, false);
        $ch->exchange_declare($exchange, 'direct', true, true, false);
        $ch->queue_bind($queue, $exchange);
        $retrived_msg = $ch->basic_get($queue);
        echo "received ". $retrived_msg->body . " </br>";
        if($retrived_msg->body){
            if(process_request(json_decode($retrived_msg->body, true))){
                //request processesed
                $ch->basic_ack($retrived_msg->delivery_info['delivery_tag']);
                echo "GOOD";
            }else{
                echo "there was an error processing the request";
                error_log($retrived_msg->body);
                echo $retrived_msg->delivery_info['delivery_tag'];
                $ch->basic_ack($retrived_msg->delivery_info['delivery_tag']); // remove for queue after adding to backup

                $backup_ch = $conn->channel();

                $backup_exchange = 'amq.direct';
                $backup_queue = 'backup_queue';
                $backup_ch->queue_declare($backup_queue, false, true, false, false);
                $backup_ch->exchange_declare($backup_exchange, 'direct', true, true, false);
                $backup_ch->queue_bind($backup_queue, $backup_exchange);

                $msg = new AMQPMessage($retrived_msg->body, array('content_type' => 'text/plain', 'delivery_mode' => 2));
                $backup_ch->basic_publish($msg, $exchange);

                $backup_ch->close();
            }
        }else{
            echo "ERROR";
            error_log($retrived_msg);
            print_r($retrived_msg);
        }
        while (count($ch->callbacks)) {
            $channel->wait();
        }
        $ch->close();
        $conn->close();
    } catch(AMQPIOException $e) {
        // cleanup_connection($conn);
        echo "AMQPIOException";
        usleep(60000000);
    } catch(\RuntimeException $e) {
        // cleanup_connection($conn);
        echo "RuntimeException";
        usleep(60000000);
    } catch(\ErrorException $e) {
        // cleanup_connection($conn);
        echo "ErrorException";
        var_dump($e);
        usleep(60000000);
    }
    sleep(5);
}
