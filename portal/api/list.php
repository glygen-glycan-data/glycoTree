<?php
include '../config.php';
$servername = getenv('MYSQL_SERVER_NAME');
$password = getenv('MYSQL_PASSWORD');

try {
	$par = $_GET['par'];
	$mode = $_GET['mode'];
	$par2 = $_GET['par2'];
	
	// Create connection
	$connection = new mysqli($servername, $username, $password, $dbname);

	// Check connection
	if ($connection->connect_error) {
		die("<br>Connection failed: " . $connection->connect_error);
	}
	
	$list = [];
	$numPars = 0;
	switch ($mode) {
		case "with": 
			// glycans containing $par1 AND $par3
			$numPars = 2;
			$query = "SELECT DISTINCT glytoucan_ac FROM compositions c WHERE residue_id=? AND glytoucan_ac IN (SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_id=?)";
			break;
		case "without":
			// glycans containing $par1 BUT NOT $par3
			$numPars = 2;
			$query = "SELECT DISTINCT glytoucan_ac FROM compositions c WHERE residue_id=? AND glytoucan_ac NOT IN (SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_id=?)";
			break;
		case "all":
		  $query = "SELECT DISTINCT glytoucan_ac FROM compositions";
		  break;
		case "mapped":
		  $query = "SELECT DISTINCT glytoucan_ac FROM compositions WHERE glytoucan_ac NOT IN (SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_name='unassigned')";
		  break;
		case "clean":
		  $query = "SELECT DISTINCT glytoucan_ac FROM compositions WHERE glytoucan_ac NOT IN (SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_name='unassigned' OR notes not in ('validated by Qrator','manually validated','Manually validated'))";
		  break;
		case "all_N":
		  $query = "SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_id LIKE 'N%'";
		  break;
		case "mapped_N":
		  $query = "SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_id LIKE 'N%' AND glytoucan_ac NOT IN (SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_name='unassigned')";
		  break;
		case "clean_mapped_N":
		  $query = "SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_id LIKE 'N%' AND glytoucan_ac NOT IN (SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_name='unassigned' OR notes not in ('validated by Qrator','manually validated','Manually validated'))";
		  break;
		case "all_O":
		  $query = "SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_id LIKE 'O%'";
		  break;
		case "mapped_O":
		  $query = "SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_id LIKE 'O%' AND glytoucan_ac NOT IN (SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_name='unassigned')";
		  break;	
		case "clean_mapped_O":
		  $query = "SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_id LIKE 'O%' AND glytoucan_ac NOT IN (SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_name='unassigned' OR notes not in ('validated by Qrator','manually validated','Manually validated'))";
		  break;
		case "res":
		  $numPars = 1;
		  $query = "SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_id=?";
		  break;	
		case "enz":
		  $numPars = 2;
		  # $query = "SELECT DISTINCT glytoucan_ac FROM compositions JOIN enzyme_mappings ON compositions.residue_name=enzyme_mappings.residue_name JOIN enzyme ON enzyme_mappings.uniprot=enyzmes.uniprot WHERE enzymes.gene_name=?;";
		  $query = "SELECT DISTINCT glytoucan_ac FROM compositions, enzyme_mappings, enzymes WHERE ( enzymes.gene_name=? OR enzymes.uniprot=? ) AND compositions.residue_name=enzyme_mappings.residue_name AND enzyme_mappings.uniprot=enzymes.uniprot;";
                  $par2 = $par;
		  break;	
		case "sug":
		  $par2 = "%" . $par . "%";
		  $numPars = 2;
		  if ((strpos($par, "NAc") !== false) || (strpos($par, "NGc") !== false)) {
			  $query = "SELECT DISTINCT glytoucan_ac FROM compositions WHERE name=? OR residue_name LIKE ?";
		  } else {
			  // the query does not contain 'NAc' or 'NGc' so filter these out
			  $query = "SELECT DISTINCT glytoucan_ac FROM compositions WHERE (name=? OR residue_name LIKE ?) AND residue_name NOT IN (SELECT residue_name FROM compositions WHERE residue_name LIKE '%NAc%' OR residue_name LIKE '%NGc%')";	
		  }
		  break;
		case "mapped_example":
		  // the following query retrieves glytoucan_ac and DP (ordered by DP)
		  //   for each fully mapped glycan containing a specific residue (specified by $par)
		  $query = "SELECT glytoucan_ac,count(DISTINCT residue_id) AS DP FROM compositions AS C1 WHERE EXISTS (SELECT glytoucan_ac FROM compositions AS C2 WHERE C1.glytoucan_ac = C2.glytoucan_ac AND residue_id=?) AND glytoucan_ac NOT IN (SELECT DISTINCT glytoucan_ac FROM compositions WHERE residue_name='unassigned') GROUP BY glytoucan_ac ORDER BY DP";
		  
		  $numPars = 1;
		  break;
		default:
		  $query = "SELECT DISTINCT glytoucan_ac FROM compositions";
	}
	
	/* to get DPs of all glycans containing a specific residue
	SELECT glytoucan_ac,count(DISTINCT residue_id) AS DP FROM compositions AS C1 WHERE EXISTS (SELECT glytoucan_ac FROM compositions AS C2 WHERE C1.glytoucan_ac = C2.glytoucan_ac AND residue_id='N53') GROUP BY glytoucan_ac;
	*/
	$stmt = $connection->prepare($query);	
	# echo json_encode($connection->error_list, JSON_PRETTY_PRINT);
	if ($numPars === 1) {
		$stmt->bind_param("s", $par);
	}
	if ($numPars === 2) {
		$stmt->bind_param("ss", $par, $par2);
	}
	$stmt->execute(); 
	$result = $stmt->get_result();
	
	while ($list_row = $result->fetch_assoc()) {
			array_push($list, $list_row);
	}
	echo json_encode($list, JSON_PRETTY_PRINT);
	
	} catch (mysqli_sql_exception $e) { 
		echo "MySQLi Error Code: " . $e->get Code() . "<br />";
		echo "Exception Msg: " . $e->getMessage();
		exit();
	}

$connection->close();

?>

