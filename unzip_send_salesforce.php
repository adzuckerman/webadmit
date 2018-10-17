<?php

    //get file name for file to be unzipped
    $zipfile = htmlspecialchars($_GET["zipfile"]);
    
    //get file type application or transcript
    $appOrTranscript = htmlspecialchars($_GET["type"]);
    
    if ( strpos($appOrTranscript, 'transcript') !== false ) {
        echo 'You indicated you were processing transcripts.<br/><br/>';
    }
    else if (strpos($appOrTranscript, 'application') !== false) {
        echo 'You indicated you were processing applications.<br/><br/>';
    }
    else{
        die('<b>Please specify a valid type in the URL i.e. type=transcript or type=application</b>');    
    }
    
    //unzip file
    $zip = new ZipArchive;
    $res = $zip->open($zipfile);
    if ($res === TRUE) {
        $zip->extractTo(dirname(__FILE__).'/myzips/extractpath1/');
        $zip->close();
    }
    else {
        die('Extraction failed. Please specify a valid zipfile.');
    }
    
    $casIds = array();

    //Initialize arrays for Applications
    $casIdtoFile = array();
    $casIdtoEncodedFile = array();
    
    //Initialize arrays for Transcripts
    $casIdDocIdtoFile = array();
    $casIdDocIdtoEncodedFile = array();
    $documentIdToCasId = array();
    
    //Iterate through files in the extract path
    $dir = dirname(__FILE__).'/myzips/extractpath1/*';
    $dirNoStar = str_replace('*','',$dir);
    
    //Get CAS Id and Document ID if applicable from filename
    foreach(glob($dir) as $file) {
        $fileOnly = str_replace($dirNoStar,'',$file);
        $fileParts = explode("_",$fileOnly);
        $casId = $fileParts[0];
        
        if(strpos($appOrTranscript, 'application') !== false) {
            $casIdtoFile[$casId] = $file; 
            $casIdtoEncodedFile[$casId] = base64_encode(file_get_contents($file));
            array_push($casIds,$casId);
        }
        
        if(strpos($appOrTranscript, 'transcript') !== false) {
            $documentId = $fileParts[1];
            $documentIdToCasId[$documentId] = $casId;
            $casIdDocIdtoFile[$casId.'~'.$documentId] = $file; 
            $casIdDocIdtoEncodedFile[$casId.'~'.$documentId] = base64_encode(file_get_contents($file)); 
            array_push($casIds,$casId);
        }    
    }
    
    //Create CAS Id set for query string
    $casIdsCommaSeperated = implode("','",$casIds);
    
    //Create connection to Salesforce.com instance
    define("USERNAME", "azuckermanre@usa.edu.redev");
    define("PASSWORD", "OmnivoFall2018!");
    define("SECURITY_TOKEN", "33u454gypb0g8K0bgm33s45W");
    
    require_once ('soapclient/SforcePartnerClient.php');
    
    $mySforceConnection = new SforcePartnerClient();
    $mySforceConnection->createConnection("soapclient/partner_sandbox.wsdl.xml");
    $mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);
    
    //Execute users query and print it out
    $query = "SELECT Id, Name, CAS_ID__c, CAS_Transcript_Uploaded__c, CAS_Application_Uploaded__c from Opportunity WHERE CAS_ID__c IN ('".$casIdsCommaSeperated."')";
    $response = $mySforceConnection->query($query);
    $sObjects = array();
    $opps = array();
    
    //Create map of CAS Ids to Salesforce Records
    //$casIdToSFId = array();
    $casIdtoRecord = array();
    foreach ($response as $record) {
        //$casIdToSFId[$record->fields->CAS_ID__c] = $record->Id;
        $casIdtoRecord[$record->fields->CAS_ID__c] = $record;
    }

    //Iterate through response and create array of attachment sObjects to be sent to Salesforce.com
    echo '<b>Processing the following files:</b><br/>';
    
    if(strpos($appOrTranscript, 'transcript') !== false) {
        foreach ($documentIdToCasId as $doc => $cas) {
            if($casIdtoRecord[$cas]->fields->CAS_Transcript_Uploaded__c == 'false'){
                $filename = basename($casIdDocIdtoFile[$cas.'~'.$doc]);
                echo $filename . '<br/>';
                $data = $casIdDocIdtoEncodedFile[$cas.'~'.$doc];
                    
                // the target Sobject
                $createFields = array(
                    'Body' => $data,
                    'Name' => $filename,
                    'ParentId' => $casIdtoRecord['$cas']->fields->Id,//$casIdToSFId[$cas],
                    'isPrivate' => 'false'
                );
                $sObject = new stdClass();
                $sObject->fields = $createFields;
                $sObject->type = 'Attachment';
                
                array_push($sObjects,$sObject);    
            }
        }
    }
    
    if(strpos($appOrTranscript, 'application') !== false) {
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
    
    echo '<br/><b>Creating Attachments for Salesforce:</b><br/>';
    foreach ($sObjects as $attachment) {
        $createResponse = $mySforceConnection->create(array($attachment));
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
        
        $updateOppResponse = $mySforceConnection->update($opps);
        //print_r($updateOppResponse);
        echo '<b>Updating Opportunities:</b><br/>';
        $updateOppResponse = $mySforceConnection->update($opps);
        foreach($updateOppResponse as $myOpp) {
            print_r($myOpp);
            echo '<br/>';
        }
        die();
    }
    
    //Update Opportunity records
    echo '<b>Updating Opportunities:</b><br/>';
    $updateOppResponse = $mySforceConnection->update($opps);
    foreach($updateOppResponse as $myOpp) {
        print_r(myOpp);
        echo '<br/>';
    }
?>