<?php

use ApaiIO\Configuration\GenericConfiguration;
use ApaiIO\Operations\Search;
use ApaiIO\Operations\BrowseNodeLookup;
use ApaiIO\Operations\Lookup;
use ApaiIO\ApaiIO;

require('vendor/autoload.php');
require('settings.php');

$conf = new GenericConfiguration;
$conf
    ->setCountry('com')
    ->setAccessKey(AWS_API_KEY)
    ->setSecretKey(AWS_API_SECRET_KEY)
    ->setAssociateTag(AWS_ASSOCIATE_TAG);

$apaiIO = new ApaiIO($conf);


$nodeId = 1; // Gotta start somewhere
$outputFile = fopen('hierarchy_' . str_pad($nodeId, 7, '0', STR_PAD_LEFT) . '.txt', 'w');

traverseNode(0, $nodeId, $apaiIO, $outputFile);

fclose($outputFile);

/**
* Recursive function for traversing down through all the decdendants of a
* specified Amazon BrowseNodeId
*/
function traverseNode($parentNodeId, $nodeId, &$apaiIO, &$outputFile)
{
    // Don't rock the boat, keep it less than one request per second
    // http://docs.aws.amazon.com/AWSECommerceService/latest/DG/TroubleshootingApplications.html
    sleep(1.1);

    $browse = new BrowseNodeLookup();
    $browse
        ->setNodeId($nodeId)
        ->setResponseGroup(['BrowseNodeInfo']);

    try {
        $response = $apaiIO->runOperation($browse);
    } catch (Exception $e) {
        print_r($e->getMessage());
        die();
    }

    $xml = simplexml_load_string($response);

    $row = [
        $parentNodeId,
        $nodeId,
        (string) $xml->BrowseNodes->BrowseNode->Name
    ];

    fputcsv($outputFile, $row);

    $children = $xml->BrowseNodes->BrowseNode->Children->BrowseNode;

    if (count($children) > 0) {
        foreach ($children as $childNode) {
            // Recurse
            traverseNode($nodeId, $childNode->BrowseNodeId, $apaiIO, $outputFile);
        }
    }
}
