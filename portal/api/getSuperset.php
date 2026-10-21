<?php

// Usage for command line:
//   php getSuperset.php acc=[glytoucan accession] pw=[mysql_password]

// Usage on line:
// https://glygen.ccrc.uga.edu/sandbox/api/getSuperset.php?acc=[glytoucan accession]


include '../config.php';
include 'bitSet.php';
include 'glycanCommon2.php';

$servername = "";
$password = "";



try {
	if (empty($_GET)) {
		// $_GET is not populated when called from command line
		// the following lines are required for command-line invocation
		parse_str(implode('&', array_slice($argv, 1)), $_GET);
		$servername = 'localhost:8889';
		// !!! password must be supplied on the command line !!!
		$password = $_GET['pw'];
	} else {
		$servername = getenv('MYSQL_SERVER_NAME');
		$password = getenv('MYSQL_PASSWORD');
	}
} catch(Exception $e) {
	exit($e->getMessage());
}

try {

	$acc = isset($_GET['acc']) ? $_GET['acc'] : '';
	//echo "\n### processing glycan: " . $acc . " ###";
	$head = isset($_GET['head']) ? $_GET['head'] : '';
	
	// Create connection
	$connection = new mysqli($servername, $username, $password, $dbname);

	// Check connection
	if ($connection->connect_error) {
		exit("connection failure");
	}
	
	$output = [];
	$bitData = [];
	$probe_b64 = "";
	fetchBitSetData($connection, $bitData, $probe_b64, $acc);
	
	// strlen($probe_b64) is zero if probe is NOT in DB - this may be OK in other php scripts
	if (strlen($probe_b64) == 0) {
		$output['glytoucan_ac'] = $acc;
		$output['error'] = "Accession '" . $acc . "' was not found in the DB";
		if ($head == "1") header('Content-Type: application/json; charset=utf-8');
		echo json_encode($output, JSON_PRETTY_PRINT);
		exit("");
	}
	
	$probe_bs = new BitSet($probe_b64, "base64");

	$extended = [];
	$identical = [];
	$pruned = [];
	$extended_fuzzy = [];
	
	compareBitSets($bitData, $probe_bs, $identical, $extended, $pruned, $extended_fuzzy);
	
	$output['message'] = "some valid results may not be in the DB (e.g., G15407YE [Man-GlcNAc-GlcNAc] or G57321FI [GalNAc])";
	$output['glytoucan_ac'] = $acc;
	$output['glycans_evaluated'] = sizeof($bitData);
	$output['identical'] = $identical;
	$output['extended'] = $extended;
	$output['extended_fuzzy'] = $extended_fuzzy;
	$output['pruned'] = $pruned;

	header("Cache-Control: no-cache, must-revalidate");
	if ($head == "1") header('Content-Type: application/json; charset=utf-8');
	echo json_encode($output, JSON_PRETTY_PRINT);

} catch (mysqli_sql_exception $e) { 
	echo "MySQLi Error Code: " . $e->getCode() . "<br />";
	echo "Exception Msg: " . $e->getMessage();
	exit();
}

$connection->close();

?>
