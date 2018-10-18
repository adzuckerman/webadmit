<?php
ini_set('memory_limit', '-1');
$key = 'f148bd717568fe2b2c8fbeec44c44b91';
$userId = '280465';

//Functions in this file can run for a long time!
//This was tested for up to 5 minutes of runtime

function process_request($request){

    var_dump($request);

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


    var_dump($request["pdf_manager_batch"]["download_hrefs"]);
    foreach($request["pdf_manager_batch"]["download_hrefs"] as $zip_download){
        echo "Line 43";
        // Get cURL resource
        $curl = curl_init();

        $dateTimeIndex = date('YmdHis'). '_' . $i;
        $output_filename = "application_" . $dateTimeIndex . '.zip';
        $extract_path = "/myzips/" . $dateTimeIndex . '/';
        echo "output_filename -> ". $output_filename;
        $fp = fopen($output_filename, 'w');

        echo "zip to download";
        var_dump($zip_download);
        // Set some options
        curl_setopt($curl, CURLOPT_URL, 'https://api.webadmit.org/api/v1/user_identities/280464/pdf_manager_zip_files/287986/download');
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('x-api-key:' . $key));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);

        // Send the request
        $content = curl_exec($curl);
        echo " CONTENT  ";
        var_dump($content);
        echo " zip_download  ";
        var_dump($zip_download);
        // Close request to clear up some resources
        curl_close($curl);
        echo "FP";
        var_dump($fp);
        echo "FWRITE :";
        echo fwrite($fp, $content);
        echo "FWRITE !";
        fclose($fp);

        echo "File exists ? ";
        var_dump(file_exists($output_filename));
        echo " File size? ";
        var_dump(filesize($output_filename));

        //unzip file
        $zip = new ZipArchive;
        $res = $zip->open($output_filename);
        echo "output_filename";
        echo $output_filename;
        echo "RESPONSE ZIP OPEN";
        var_dump($res);
        echo "RESPONSE ZIP ABOVE";
        if ($res === TRUE) {
          echo "response is true";
          $zip->extractTo(dirname(__FILE__).$extract_path);
          $zip->close();
        }else{

            echo "NO RESPONSE";
            echo "test test test test test testtest test testtest test testtest test testtest test testtest test test";
            $key = 'f148bd717568fe2b2c8fbeec44c44b91';
            $curl = curl_init();

            // Set some options
            curl_setopt($curl, CURLOPT_URL, 'https://api.webadmit.org/api/v1/user_identities/280464/pdf_manager_zip_files/287988/download');
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('x-api-key:' . $key));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_AUTOREFERER, true);

            // Send the request
            $content = curl_exec($curl);
            echo " CONTENT  ";
            var_dump(($content));

          echo "NO RESPONSE";
          return false;
        }

        //Iterate through extracted files in the extract path
        $dir = dirname(__FILE__).$extract_path.'*';
        $dirNoStar = str_replace('*','',$dir);

      	//Get CAS Id and Document ID if applicable from filename
        foreach(glob($dir) as $file) {
            echo "line 82";
            $fileOnly = str_replace($dirNoStar,'',$file);
            $fileParts = explode("_",$fileOnly);
            $casId = $fileParts[0];

            if(strpos($pdfName, 'Full_Application') !== false) {
                $casIdtoFile[$casId] = $file;
                $casIdtoEncodedFile[$casId] = base64_encode(file_get_contents($file));
                array_push($casIds,$casId);
            }

            if(strpos($pdfName, 'Transcripts') !== false) {
                echo "PDF NAME IS Transcripts";
                $documentId = $fileParts[1];
                $documentIdToCasId[$documentId] = $casId;
                $casIdDocIdtoFile[$casId.'~'.$documentId] = $file;
                $casIdDocIdtoEncodedFile[$casId.'~'.$documentId] = base64_encode(file_get_contents($file));
                array_push($casIds,$casId);
            }
            echo $pdfName;
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
    var_dump($response);
    echo "RESPONSE ABOVE";
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

    echo "148";
    //If no CAS transcript has been updloaded iterate through response and create
    //array of transcript attachment sObjects to be sent to Salesforce.com
    if(strpos($pdfName, 'Transcripts') !== false) {
        echo "Transcripts 154";
        foreach ($documentIdToCasId as $doc => $cas) {
            echo "In foreach 155";
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
    var_dump($pdfName);
    echo "PDF NAME";

    //Create attachments and update Opportunities
    echo '<b>Creating Attachments for Salesforce:</b><br/>';
    print_r($sObjects);
    foreach ($sObjects as $attachment) {
        echo "178";
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
            var_dump("MESSAGE");
            echo "</br></br>";
            if(process_request(json_decode($retrived_msg->body, true))){
                $ch->basic_ack($retrived_msg->delivery_info['delivery_tag']);
                echo "GOOD";
            }else{
                echo "ERROR";
                error_log($retrived_msg->body);
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
