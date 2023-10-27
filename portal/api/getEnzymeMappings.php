<?php
include '../config.php';

$servername = getenv('MYSQL_SERVER_NAME');
$password = getenv('MYSQL_PASSWORD');

$limiter = null;
if(isset($_GET['limiter'])) $limiter = $_GET['limiter'];
$limiterVal = $_GET['val'];

// Create connection
$connection = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($connection->connect_error) {
	die("<br>Connection failed: " . $connection->connect_error);
}

$dataWrap = [];
$dataWrap['limiter'] = $limiter;
$dataWrap['limiter_value'] = $limiterVal;
// echo "limiter is " . $limiter . " - limiterVal is " . $limiterVal . "<br>";


$enzymeMappingData = [];

$query = "none";

// limit query to specific, acceptable cases
// verbosity REQUIRED for security - cannot simply replace variables, but use prepared statements
$nPars = 0;
$whereClause = "";
$orderClause = "ORDER BY SUBSTR(canonical_residues.residue_id, 1, 1), cast(SUBSTR(canonical_residues.residue_id, 2, 4) as UNSIGNED)";

if(!empty($limiter)) switch ($limiter) { 
	case "residue_id":
	  $whereClause = "WHERE canonical_residues.residue_id=?";
	  $nPars = 1;
	  break;	
	case "parent_id":
	  $whereClause = "WHERE canonical_residues.parent_id=?";
          $orderClause = "ORDER BY canonical_residues.anomer, canonical_residues.absolute, canonical_residues.form_name, canonical_residues.site";
	  $nPars = 1;
	  break;	
	case "proposer":
	  $whereClause = "WHERE enzyme_mappings.proposer_id=?";
	  $nPars = 1;
	  break;
	case "disputer":
	  $whereClause = "WHERE enzyme_mappings.disputer_id=?";
	  $nPars = 1;
	  break;
	case "species":
	  $whereClause = "WHERE enzymes.species=?";
	  $nPars = 1;
	  break;
	case "status":
	  $nPars = 1;
	  $whereClause = "WHERE enzyme_mappings.status=?";
	  break;
	case "enzyme":
	  $whereClause = "WHERE enzyme_mappings.uniprot=?";
	  $nPars = 1;
	  break;
	case "gene":
	  $whereClause = "WHERE enzymes.gene_name=?";
	  $nPars = 1;
	  break;
	case "notes_substr":
	  $limiterVal = "%$limiterVal%"; // required for "WHERE LIKE"
	  $whereClause = "WHERE enzyme_mappings.notes LIKE ?";
	  $nPars = 1;
	  break;

	case "no_filter":
		break;
	default:
	  // if this code is reached, the specified $limiter is not supported
	  $msg = "The specified limiter ($limiter) is not supported -";
	  $msg .= "  Try one of the following:";
	  $msg .= "  residue_id, enzyme, notes_substr";
	  $dataWrap['msg'] = $msg;
	  $nPars = 0;
	  $whereClause = "WHERE enzyme_mappings.residue_id=0";
}

$query = "SELECT canonical_residues.residue_id,canonical_residues.anomer,canonical_residues.absolute,canonical_residues.form_name,canonical_residues.site,canonical_residues.parent_id,enzyme_mappings.instance,enzyme_mappings.uniprot,enzyme_mappings.notes,enzyme_mappings.type,enzyme_mappings.status,enzyme_mappings.proposer_id,enzyme_mappings.disputer_id,enzymes.species,enzymes.gene_name FROM canonical_residues LEFT JOIN enzyme_mappings ON (enzyme_mappings.residue_id = canonical_residues.residue_id) LEFT JOIN enzymes ON (enzymes.uniprot = enzyme_mappings.uniprot) $whereClause $orderClause";
//echo "$query<br><br>";
//echo "limiterVal is $limiterVal";
$stmt = $connection->prepare($query);

switch ($nPars) { 
	case 1 :
		$stmt->bind_param("s", $limiterVal);
		break;
	case 5 :
		$stmt->bind_param("sssss", $limiterVal, $limiterVal, $limiterVal, $limiterVal, $limiterVal);
		break;
	default:
		break;
}


$stmt->execute(); 
$result = $stmt->get_result();
if ( ($result->num_rows) > 0) {
	while ($row = $result->fetch_assoc() ) {
		array_push($enzymeMappingData,$row);
	}
} else {
	$msg = "The query generated no results.  " . $dataWrap['msg'];
	$dataWrap['msg'] = $msg;
}

$dataWrap['data'] = $enzymeMappingData;

header("Cache-Control: no-cache, must-revalidate");
header('Content-Type: application/json; charset=utf-8');
echo json_encode($dataWrap, JSON_PRETTY_PRINT);

?>
