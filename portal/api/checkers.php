<?php

include '../config.php';
include 'glycanCommon2.php';
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
$v = 0;  // $v MUST be zero for API to function

$glycoct = isset($_GET['glycoct']) ? $_GET['glycoct'] : '';
$glycan_type = isset($_GET['type']) ? $_GET['type'] : '';
$debug = isset($_GET['debug']) ? $_GET['debug'] : '';
$enz = isset($_GET['enz']) ? $_GET['enz'] : '';
$related = isset($_GET['related']) ? $_GET['related'] : '';
if (strcmp($debug, 'true') == 0) {
	$v = 9;
}

$error = [];

if (is_null($glycoct) || (strlen($glycoct) < 1)) {
	$gctErr['type'] = "input error";
	$gctErr['message'] = "GlycoCT is absent";
	array_push($error, $gctErr);
}

if (is_null($glycan_type) || (strlen($glycan_type) < 1) ) {
	$typeErr['type'] = "input error";
	$typeErr['message'] = "glycan type ($glycan_type) is absent";
	array_push($error, $typeErr);
} else {
	$supportedTypes = "/[NO]/";
	if ((strlen($glycan_type) > 1) || preg_match($supportedTypes, $glycan_type) == 0) {
		$typeErr['type'] = "input error";
		$typeErr['message'] = "glycan type ($glycan_type) is not supported";
		array_push($error, $typeErr);
	}
}

if ($v > 0) {
	echo "<pre>";
	echo "debug is '" . $debug . "'\n";
	echo "show Enzymes is '" . $enz . "'\n";
	echo "show Related is '" . $related . "'\n";
	echo "verbosity is " . $v . "\n";
	echo "code Dir is " . $codeDir . "\n";
	echo "csv code is " . $csvCode . "\n";
	echo "map code is " . $mapCode . "\n";
	echo "sugar file is " . $sugarFile . "\n";
	echo "N node file is " . $nodeFileN . "\n";
	echo "O node file is " . $nodeFileO . "\n";
}

$input = [];
$input['glycoct'] = $glycoct;
$input['glycan_type'] = $glycan_type;

$st = "@@@@@@";
$et = "@@@@@@";
if ($v > 1) echo "\nShowing intermediate data processing steps\n" . 
	"Data processed and returned by java programs are enclosed by the following lines\n" . $st . "\n   data goes here\n" . $et . "\n";

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
	echo "\nGlycoCT encoding (to be processed by java) is \n" . $st . "\n" . $glycoct . "\n" . $et . "\n";
	echo "\nGlycan type is " . $glycan_type . "\n";
}
// use the first 8 characters of the hash for the temporary glycan accession
$tempID = $glycan_type . "TEMP" . substr(hash('ripemd160', $glycoct), 0, 7);
if ($v > 3) echo "\ntemporary glycan id is " . $tempID . "\n";

// to prevent code injection, escapeshellarg($glycoct) is invoked 
//    encloses GlycoCT string in single quotes
//   the quoted string is split into 'words' by the java program
$command = "java -jar " . $csvCode . " " . escapeshellarg($glycoct);
// $command = "java -jar somecrap.jar " . escapeshellarg($glycoct);
$csvEncoding = shell_exec($command) ?? '';
if ($v > 3) echo "\nThe following COMMAND was passed from PHP (via shell_exec) to generate CSV encoding:\n" . $st . "\n" .
	$command . "\n" . $et . "\n";


if ($v > 3) echo "\nThe following result was returned by the java program." .
	"\nIf execution was successful, this should be a CSV encoding.\n\n" . $st . 
	"\n" . $csvEncoding . "\n" . $et . "";

if (strpos($csvEncoding, "glytoucan_ac") === false) {
	$gctDecodeErr['type'] = "decoding error";
	$gctDecodeErr['message'] = "GlycoCT could not be decoded";
	array_push($error, $gctDecodeErr);
	if ($v > 3) {
		echo "\nGlycoCT could not be decoded";
	}
}



$command = "java -jar " . $mapCode . " -t " . escapeshellarg($csvEncoding) . " -s " . $sugarFile . " -c " . $nodeFile . " -n 1 -v 0 -m 3 -e 3"; 
if ($v > 3) {
	echo "\n\nThe following COMMAND was passed from PHP (via shell_exec) to generate the MAPPED CSV encoding:\n" .
		$st . "\n" . $command . "\n" . $et . "\n";
}

$result =  shell_exec($command);

if ($v > 3) {
	echo "\nThe following MAPPED CSV encoding was returned by the java program:\n";
	echo $st . "\n" . $result . "\n" . $et . "\n";
}

if (strlen($result) == 0) {
	// TreeBuilder failed, so use the raw $csvEncoding
	$result = $csvEncoding;
	$mappingErr['type'] = "mapping error";
	$mappingErr['message'] = "No residues in $tempID could be mapped to the $glycan_type-glycotree";
	array_push($error, $mappingErr);
	if ($v > 3) {
		echo "\nNo residues in the structure could be mapped";
	}
}
// mappedStringArray is an array of strings, one for each residue
$mappedStringArray = explode("\n", $result);
if ($v > 4) {
	echo "\n\nMAPPED encoding as array of strings\n";
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
	if ($v > 4) echo "\n\nCSV string:\n    " . $resStr;

	$glycoctIndexPresent = false;
	if ($i == 0) { // zeoth line holds names of parameters
		foreach($resStringArray as $j => $parName) {
				$paramKeys[$j] = $parName;
				if ($parName == "glycoct_index") $glycoctIndexPresent = true;
		}
		// add glycoct_index if not already added by TreeBuilder
		if (!$glycoctIndexPresent) {
			$paramKeys[sizeof($resStringArray)] = "glycoct_index";
			if ($v > 4) echo "\nAdded glycoct_index to parameters";
		}
		if ($v > 4) {
			echo "\n\nfirst csv string - parameter keys \n    ";
			print_r($paramKeys);
		}
	} else if (sizeof($resStringArray) > 1)  { // process data lines but not blank lines
		$glycoctIndexPresent = false;
		foreach($resStringArray as $j => $parVal) {
			if ($j > 1)  { 
				$residueAssociativeArray[$paramKeys[$j]] = $parVal;
				if ($paramKeys[$j] == "glycoct_index") $glycoctIndexPresent = true;
			}
		}
		// add glycoct_index value if not already added by TreeBuilder
		if (!$glycoctIndexPresent) { 
			if ($v > 4) echo "\nAdded glycoct_index value (glycoctIndexPresent is $glycoctIndexPresent)";
			$residueAssociativeArray['glycoct_index'] = $residueAssociativeArray['residue_id'];
		}
		if ($v > 4) {
			echo "\nassociative array from CSV string\n";
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

// in each caveat, replace the string "undetermined" with $tempID
$modCaveats = [];  // modified array of caveats
foreach($integratedData['caveats'] as $caveat) {
	$newCaveat = []; // a single, new, modified caveat
	foreach($caveat as $key => $value) {
		$newCaveat[$key] = str_replace("undetermined", $tempID, $value);
	}
	array_push($modCaveats, $newCaveat);
}

$finalData['input'] = $input;
$finalData['glytoucan_ac'] = $integratedData['glytoucan_ac'];
$finalData['temp_id'] = $tempID;
if (sizeof($error) > 0) $finalData['error'] = $error;
if (sizeof($integratedData['rule_violations']) > 0) $finalData['rule_violations'] = $integratedData['rule_violations'];
if (sizeof($modCaveats) > 0) $finalData['caveats'] = $modCaveats;
if (isset($integratedData['residues']))
	if (sizeof($integratedData['residues']) > 0)
		$finalData['residues'] = $integratedData['residues'];

if (strcmp($enz, "true") == 0) {
	if ($v > 5) echo("\nEnzymes are included in scope of the query");
} else {
	if (isset($integratedData['residues'])) {
		if ($v > 5) {
			echo("\n Enzymes are not included in scope of the query");
			echo("\n### Rebuilding default 'residues' object, removing 'enzymes' property ###\n");
		}
		$tempResidues = [];
		foreach($integratedData['residues'] as $x => $res_value) {
			$thisResidue = [];
			if ($v > 5) {
				echo "\nBefore rebuilding, residue [" . $res_value['residue_id'] . "] has enzymes:\n";
				print_r($res_value['enzymes']);
			}
			foreach($res_value as $y => $res_parameter) {
				if (strcmp($y, "enzymes") != 0) $thisResidue[$y] = $res_parameter;
			}
			$thisResidue['enzymes'] = null;
			array_push($tempResidues, $thisResidue);
		}
		$finalData['residues'] = $tempResidues;
		if ($v > 5) {
			echo "\n### all enzymes removed ###\n";
			print_r($finalData['residues']);	
		}
	}
}

	
if (strcmp($related, "true") == 0) {
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

	$relatedGlycans = [];
	$relatedGlycans['message'] = "some related glycans may not be in the DB (e.g., G15407YE [Man-GlcNAc-GlcNAc] or G57321FI [GalNAc])";
	$relatedGlycans['glycans_evaluated'] = sizeof($bitData);
	$relatedGlycans['identical'] = $identical;
	$relatedGlycans['extended'] = $extended;
	$relatedGlycans['extended_fuzzy'] = $extended_fuzzy;
	$relatedGlycans['pruned'] = $pruned;

	$finalData['related_glycans'] = $relatedGlycans;
}



if ($v > 4) echo "\n\nJSON result:\n";
if ($v == 0) {
	header_remove(); 
	header("Cache-Control: no-cache, must-revalidate");
	header("Content-Disposition: attachment; filename=$tempID.json");
	header("Content-type: application/json");
	header("Access-Control-Allow-Origin: *");
	header("Access-Control-Allow-Methods: POST, GET");
}
echo json_encode($finalData, JSON_PRETTY_PRINT);

$connection->close();
if ($v > 0) echo "</pre>";

// NEXT: extend $integratedData with related glycans not mappable to $tempID
?>
