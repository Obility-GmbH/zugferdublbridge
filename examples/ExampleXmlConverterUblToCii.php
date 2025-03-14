<?php

use horstoeko\stringmanagement\PathUtils;
use horstoeko\zugferdublbridge\XmlConverterUblToCii;

require __DIR__ . "/../vendor/autoload.php";

$xmlFilenames = glob(__DIR__ . "/*ubl*.xml");

if ($xmlFilenames === false) {
    die();
}

foreach ($xmlFilenames as $xmlFilename) {
    $xmlFilePathInfo = pathinfo($xmlFilename);

    $newXmlPath = PathUtils::combineAllPaths($xmlFilePathInfo['dirname'], "cii");
    $newXmlFilename = PathUtils::combinePathWithFile($newXmlPath, str_replace('ubl', 'uncefact', $xmlFilePathInfo['basename']));

    echo "Converting..." . PHP_EOL;
    echo ' - Source ... ' . $xmlFilename . PHP_EOL;
    echo ' - Dest ..... ' . $newXmlFilename . PHP_EOL;

    XmlConverterUblToCii::fromFile($xmlFilename)->convert()->saveXmlFile($newXmlFilename);
}
