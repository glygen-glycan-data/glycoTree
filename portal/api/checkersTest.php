<?php

include '../config.php';
include 'bitSet.php';

function generateEmptyBitSet($connection) {
	// generates a BitSet with the proper size for current DB contents
	$bsQuery = "SELECT base64_composition from bitSet LIMIT 1";
	$stmt = $connection->prepare($bsQuery);	
	$stmt->execute(); 
	$result = $stmt->get_result();
	$row = $result->fetch_assoc();
	$b64 = $row ? $row['base64_composition'] : '';
	$bs = new BitSet($b64, "base64");
	$bs->clear(-1);
	return ($bs);
}
	
	
function bitIDfromResID($resID, $bitSetSize, $hotChars, $specialIDs) {
	if (array_key_exists($resID, $specialIDs)) {
		return($specialIDs[$resID]);
	}
	if (strlen(strpbrk($resID, $hotChars) ) > 0 ) {
		// $resID contains a 'hot character' (e.g., 'N' or 'O')
		$bitID = substr($resID, 1);
		return($bitID);
	} else {
		//  id is not mapped: bit[0] should be set whenever this occurs
		return(0);
	}
}
	
$servername = getenv('MYSQL_SERVER_NAME');
$password = getenv('MYSQL_PASSWORD');


$codeDir = "./java/";
$csvCode = $codeDir . "TextToCSV.jar";
$mapCode = $codeDir . "TreeBuilder4.jar";
$sugarFile = $codeDir . "sugars.csv";
$nodeFileN = $codeDir . "N_canonical_residues.csv";
$nodeFileO = $codeDir . "O_canonical_residues.csv";
$v = 9;  // $v MUST be zero for API to function; $v > 0 for testing

if ($v > 0) {
	echo "<pre>";
	echo "verbosity is " . $v . "\n";
	echo "code Dir is " . $codeDir . "\n";
	echo "csv code is " . $csvCode . "\n";
	echo "map code is " . $mapCode . "\n";
	echo "sugar file is " . $sugarFile . "\n";
	echo "N node file is " . $nodeFileN . "\n";
	echo "O node file is " . $nodeFileO . "\n";
}

if ($v > 1) echo "Development in progress: showing intermediate data processing steps\n\n";

$glycoct = isset($_GET['glycoct']) ? $_GET['glycoct'] : '';
$glycan_type = isset($_GET['type']) ? $_GET['type'] : '';

switch ($glycan_type) {
	case "N":
		$nodeFile = $nodeFileN;
		break;
	case "O":
		$nodeFile = $nodeFileO;
		break;
	default:
		break;
}

if ($v > 3) {
	echo "GlycoCT encoding is \n" . $glycoct . "\n";
	echo "Glycan type is " . $glycan_type . "\n\n";
}
// to prevent code injection, escapeshellarg($glycoct) is invoked 
//    encloses GlycoCT string in single quotes
//   the quoted string is split into 'words' by the java program
$command = "java -jar " . $csvCode . " " . escapeshellarg($glycoct);
if ($v > 3) echo "\ncsv command:\n" . $command . "\n";
$csvEncoding = shell_exec($command);

if ($v > 3) echo "\ncsv encoding\n" . $csvEncoding;

$linefeeds   = array("\r\n", "\n", "\r");
$space = ' ';

//$oneLineCSV = str_replace($linefeeds, $space, $csvEncoding);
//if ($v > 3) echo "\n\none-line csv encoding\n" . $oneLineCSV;

// use the first 8 characters of the hash for the temporary glycan accession
$tempID = $glycan_type . substr(hash('ripemd160', $glycoct), 0, 7);
if ($v > 3) echo "\n\ntemporary glycan id is " . $tempID . "\n";
// $csvEncoding = str_replace('undetermined', $tempID, $csvEncoding);
if ($v > 3) echo "\nannotated csv encoding\n" . $csvEncoding;

// the directory ./temp/mapped/ must already exist
$command = "java -jar " . $mapCode . " -t " . escapeshellarg($csvEncoding) . " -s " . $sugarFile . " -c " . $nodeFile . " -n 1 -v 0 -m 3 -e 3"; 
if ($v > 3) {
	echo "\n\nmapping residues to tree with command:\n" . $command . "\n\n";
}

$result =  shell_exec($command);
// $result = str_replace('glytoucan_ac', 'temp_ac', $result);

if ($v > 3) {
	echo "\n\nmapped structure as csv:\n";
	echo "\n" . $result . "\n";
}

// mappedStringArray is an array of strings, one for each residue
$mappedStringArray = explode("\n", $result);
if ($v > 4) {
	echo "\n\nmapped structure as array of strings\n";
	print_r($mappedStringArray);
}

// $compositionArray is an array of associative arrays describing the composition of the structure
$compositionArray = [];
$paramKeys = [];

foreach($mappedStringArray as $i => $resStr) {
	// $resStringArray is an array of strings, each describing a parameter for the residue
	$resStringArray = explode(",", $resStr);
	// $residueAssociativeArray is an array of parameters describing the residue
	$residueAssociativeArray = [];
	if ($v > 4) echo "\n\ncsv string:\n    " . $resStr;
	
	if ($i == 0) { // zeoth line holds names of parameters
		foreach($resStringArray as $j => $parName) {
			if ($j < 11) { // only use the first 11 parameters
				$paramKeys[$j] = $parName;
			}
		}
		if ($v > 4) {
			echo "\n\nfirst csv string - setting parameter keys \n    ";
			print_r($paramKeys);
		}
	} else if (sizeof($resStringArray) > 1)  { // do not process blank lines
		foreach($resStringArray as $j => $parVal) {
			if ( ($j < 11) && ($j > 1) ) { // use only selected parameters
				$residueAssociativeArray[$paramKeys[$j]] = $parVal;
			}
		}
		
		if ($v > 4) {
			echo "\nassociative array for residue " . ($i - 1) . "\n";
			print_r($residueAssociativeArray);
		}
		// add the associative array for the residue to $compositionArray
		array_push($compositionArray, $residueAssociativeArray);
	}
}

if ($v > 4) {
	echo "\n\nfull associative array for temporary id " . $tempID . "\n";
	print_r($compositionArray);
}

// Create connection
$connection = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($connection->connect_error) {
	die("<br>Connection failed: " . $connection->connect_error);
}

$integratedData = integrateData($connection, $compositionArray, 'undetermined');

// generate the structure's bit set with appropriate size for current DB contents
$probeBS = generateEmptyBitSet($connection);  
if ($v > 4) echo "\n### new bit set: size " . $probeBS->size() . ";  length " . $probeBS->length();
$bitSetSize = $probeBS->size();

$hotChars = "NO";
$specialIDs = array("NA" => $bitSetSize - 3, "NB" => $bitSetSize - 2, 
						 "NC" => $bitSetSize - 1, "OC" => $bitSetSize - 4);

for ($i = 0; $i < sizeof($compositionArray); $i++) {
	$resID = $compositionArray[$i]["residue_id"];
	$bitID = bitIDfromResID($resID, $bitSetSize, $hotChars, $specialIDs);
	if ($v > 5) echo "\n residue " . $resID . " has bitID " . $bitID;
	$probeBS->set($bitID);
}
if ($v > 2) echo "\n\n### bit set for probe [" . $tempID . "]: " .
	$probeBS->toString();

$bitData = [];
$probe_b64 = "";
fetchBitSetData($connection, $bitData, $probe_b64, $tempID);
if ($v > 4) echo "\n\n";

$extended = [];
$identical = [];
$pruned = [];
$extended_fuzzy = [];

compareBitSets($bitData, $probeBS, $identical, $extended, $pruned, $extended_fuzzy);

$finalData['glytoucan_ac'] = $integratedData['glytoucan_ac'];
$finalData['temp_id'] = $tempID;
$finalData['caveats'] = $integratedData['caveats'];
$finalData['residues'] = $integratedData['residues'];
	
$relatedGlycans = [];
$relatedGlycans['message'] = "some related glycans may not be in the DB (e.g., G15407YE [Man-GlcNAc-GlcNAc] or G57321FI [GalNAc])";
$relatedGlycans['glycans_evaluated'] = sizeof($bitData);
$relatedGlycans['identical'] = $identical;
$relatedGlycans['extended'] = $extended;
$relatedGlycans['extended_fuzzy'] = $extended_fuzzy;
$relatedGlycans['pruned'] = $pruned;

$finalData['related_glycans'] = $relatedGlycans;

if ($v > 4) echo "\n\n";
header_remove(); 
header("Cache-Control: no-cache, must-revalidate");
header("Content-Disposition: attachment; filename=$tempID.json");
echo json_encode($finalData, JSON_PRETTY_PRINT);

$connection->close();
if ($v > 0) echo "</pre>";

// NEXT: extend $integratedData with related glycans not mappable to $tempID
?>
