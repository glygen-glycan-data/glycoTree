<?php
include '../config.php';
include 'glycanCommon2.php';

$servername = getenv('MYSQL_SERVER_NAME');
$password = getenv('MYSQL_PASSWORD');

$focus = $_GET['focus'];

// Create connection
$connection = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($connection->connect_error) {
	die("<br>Connection failed: " . $connection->connect_error);
}

$finalResult = [];
$finalResult['residue_id'] = $focus;

$query = "SELECT * FROM canonical_residues WHERE residue_id=?";
$stmt = $connection->prepare($query);
$stmt->bind_param("s", $focus);
$stmt->execute(); 
$result = $stmt->get_result();
if ( ($result->num_rows) > 0) {
	$row = $result->fetch_assoc();
	$anomer = str_replace(array("a","b"), array("&alpha;","&beta;"), $row['anomer']);
	$finalResult['structure'] = $anomer . "-" . $row['absolute'] . "-" . $row['form_name'];
	$finalResult['snfg_name'] = $row['name'];
	$finalResult['residue_name'] = $row['residue_name'];
}

$finalResult['mapped_enzymes'] = [];
$query = "SELECT enzyme_mappings.*,enzymes.gene_name,enzymes.species FROM enzyme_mappings LEFT JOIN enzymes ON (enzyme_mappings.uniprot = enzymes.uniprot) WHERE enzyme_mappings.residue_id=? ORDER BY enzymes.species, enzymes.gene_name";
$stmt = $connection->prepare($query);
$stmt->bind_param("s", $focus);
$stmt->execute(); 
$result = $stmt->get_result();
if ( ($result->num_rows) > 0) {
	while ($row = $result->fetch_assoc()) {
		array_push($finalResult['mapped_enzymes'],$row);
	}
} else {
	$message = "No enzymes have been mapped to residue " . $focus;
	$finalResult['message'] = $message;
}

$finalResult['enzymes'] = [];
$query = "SELECT * FROM enzymes ORDER BY gene_name";
$stmt = $connection->prepare($query);
$stmt->execute(); 
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
	array_push($finalResult['enzymes'],$row);
}

//echo "<pre>";
echo json_encode($finalResult, JSON_PRETTY_PRINT);
//echo "</pre>";

?>
