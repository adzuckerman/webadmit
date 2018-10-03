<?php
echo "<html><head><title>Print Contacts</title>";

//Create connection to Salesforce.com instance
define("USERNAME", "azuckermanre@usa.edu.redev");
define("PASSWORD", "Ek.Zuck0883!");
define("SECURITY_TOKEN", "FxVjMyqbPFcYhsEywRelZ4zE");


require_once ('soapclient/SforcePartnerClient.php');

$mySforceConnection = new SforcePartnerClient();
$mySforceConnection->createConnection("soapclient/partner_sandbox.wsdl.xml");
$mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);
echo "</head><body>";

//Execute users query and print it out
$query = "SELECT Id, Name, CAS_ID__c from Opportunity WHERE CAS_ID__c IN ('1234567890')";
$response = $mySforceConnection->query($query);

echo "Results of query '$query'<br/><br/>\n";
foreach ($response as $record) {
    //print_r($record);
    // Id is on the $record, but other fields are accessed via the fields object
    $object = new Sobject($record);
    //$object = $record;
    //print_r($object);
    echo $object->Id . ": " . $object->fields->Name . " "
        . $object->fields->CAS_ID__c . " " . "<br/>\n";
    
    $data = file_get_contents('1234567890.pdf');
    
    // the target Sobject
    $createFields = array(
        'Body' => base64_encode($data),
        'Name' => '1234567890.pdf',
        'ParentId' => $object->Id,
        'isPrivate' => 'false'
    );
    $sObject = new stdClass();
    $sObject->fields = $createFields;
    $sObject->type = 'Attachment';
    echo 'Creating Attachment';
    
    $createResponse = $mySforceConnection->create(array($sObject));
    print_r($createResponse);
}

echo "</body></html>";

?>