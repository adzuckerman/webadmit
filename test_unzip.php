<?php

    //unzip file
    $zip = new ZipArchive;
    $res = $zip->open('testfile1.zip');
    if ($res === TRUE) {
        $zip->extractTo(dirname(__FILE__).'/myzips/extractpath1/');
        $zip->close();
    }
    
    $casIdtoFile  = array();
    $casIdtoEncodedFile = array();
    $blobArray = array();
    
    //Iterate through files in the extract path
    $dir = dirname(__FILE__).'/myzips/extractpath1/*';
    $dirNoStar = str_replace('*','',$dir);
    
    foreach(glob($dir) as $file) {
      $casId = str_replace($dirNoStar,'',$file);
      $casId = substr($casId,0,strpos($casId, '_'));
      $casIdtoFile[$casId] = $file; 
      $casIdtoEncodedFile[$casId] = base64_encode(file_get_contents($file));
    }
    
    //Create CAS Id set for query string
    $casIds = array_keys($casIdtoFile);
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
    $query = "SELECT Id, Name, CAS_ID__c from Opportunity WHERE CAS_ID__c IN ('".$casIdsCommaSeperated."')";
    $response = $mySforceConnection->query($query);
    $sObjects = array();
    
    echo '<b>Processing the following files:</b><br/>';
    foreach ($response as $record) {
        $filename = basename($casIdtoFile[$record->fields->CAS_ID__c]);
        echo $filename . '<br/>';
        $data = $casIdtoEncodedFile[$record->fields->CAS_ID__c];
        
        // the target Sobject
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
    //var_dump($sObjects);
    
    echo '<b>Creating Attachments for Salesforce:</b><br/>';
    foreach ($sObjects as $attachment) {
        $createResponse = $mySforceConnection->create(array($attachment));
        print_r($createResponse);
        echo '<br/>';
    }
    
    
	
?>