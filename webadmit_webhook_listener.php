<?php
//Get callback from WebAdmit
$key = 'f148bd717568fe2b2c8fbeec44c44b91';
$userId = '280465';

header('Content-Type: application/json');
$json = file_get_contents('php://input');
$request = json_decode($json, true);

//Create connection to Salesforce.com instance
define("USERNAME", "azuckermanre@usa.edu.redev");
define("PASSWORD", "OmnivoFall2018!");
define("SECURITY_TOKEN", "33u454gypb0g8K0bgm33s45W");

require_once ('soapclient/SforcePartnerClient.php');

$mySforceConnection = new SforcePartnerClient();
$mySforceConnection->createConnection("soapclient/partner_sandbox.wsdl.xml");
$mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);

$casIdtoFile = array();
$casIdtoEncodedFile = array();

//Loop through download hrefs and get file
$i = 1;
foreach($request["pdf_manager_batch"]["download_hrefs"] as $zip_download){
    
    // Get cURL resource
    $curl = curl_init();
    
    $output_filename = "application" . $i .'.zip';
    $extract_path = "/myzips/extractpath" .$i . '/';
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
    
  	//Get CAS Id from filename
  	foreach(glob($dir) as $file) {
      $casId = str_replace($dirNoStar,'',$file);
      $casId = substr($casId,0,strpos($casId, '_'));
      $casIdtoFile[$casId] = $file; 
      $casIdtoEncodedFile[$casId] = base64_encode(file_get_contents($file));
    }
  	
    //Create CAS Id set for query string
    $casIds = array_keys($casIdtoFile);
    $casIdsCommaSeperated = implode("','",$casIds);
    
    $i++;
}

//Execute Opportunity query to get Salesforce Id and CAS Id
$query = "SELECT Id, Name, CAS_ID__c from Opportunity WHERE CAS_ID__c IN ('".$casIdsCommaSeperated."')";
$response = $mySforceConnection->query($query);
$sObjects = array();

//Iterate through response and create array of attachment sObjects to be sent to Salesforce.com
foreach ($response as $record) {
    $filename = basename($casIdtoFile[$record->fields->CAS_ID__c]);
    var_dump($filename);
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
var_dump($sObjects);
echo 'Creating Attachment';
foreach ($sObjects as $attachment) {
    $createResponse = $mySforceConnection->create(array($attachment));
    print_r($createResponse);    
}
?>