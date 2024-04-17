<?php
include '../config.php';
include 'glycanCommon2.php';

$servername = getenv('MYSQL_SERVER_NAME');
$password = getenv('MYSQL_PASSWORD');

$focus = $_GET['focus'];
$allenz = $_GET['allenz'];

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
        $finalResult['anomer'] = $row['anomer'];
        $finalResult['site'] = $row['site'];
        $finalResult['absolute'] = $row['absolute'];
        $finalResult['ring'] = $row['ring'];
        $finalResult['name'] = $row['name'];
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
$query = "SELECT DISTINCT enzymes.*, NOT ISNULL(canonical_residues.residue_id) as expected FROM enzymes LEFT JOIN enzyme_mappings ON  enzymes.uniprot = enzyme_mappings.uniprot LEFT JOIN canonical_residues ON enzyme_mappings.residue_id = canonical_residues.residue_id and canonical_residues.name = ? and canonical_residues.anomer = ? and canonical_residues.absolute = ? and canonical_residues.ring = ? and canonical_residues.site = ? ORDER BY enzymes.gene_name";
$stmt = $connection->prepare($query);
$stmt->bind_param("sssss", $finalResult['name'], $finalResult['anomer'], $finalResult['absolute'], $finalResult['ring'], $finalResult['site']);
$stmt->execute(); 
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
	array_push($finalResult['enzymes'],$row);
}

//echo "<pre>";
header("Content-Type: application/json");
echo json_encode($finalResult, JSON_PRETTY_PRINT);
//echo "</pre>";

?>
