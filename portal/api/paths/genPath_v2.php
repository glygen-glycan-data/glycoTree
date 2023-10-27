<?php

// Generate a set of pathways for a sandbox-supported glycan
//  !!! COMMAND LINE MUST BE CALLED WITH MAMP -  $servername = 'localhost:8889' !!!
//  Usage: When called from the command line, use the following syntax
// php genPath_v2.php fmt=json scope=full end=G01354KL head=0 pw=[mysql_password]
//   "fmt" can be 'json' or 'tsv' (tsv not yet supported)
//   "scope" can be either 'full' (retrieve a DAG with multiple pathways)
//     or 'likely' (retrieve a single, likely, linear pathway)
//   "head" can be '0' (no mime header) or '1' (add mime header)
//   "end" is the glytoucan accession of the glycan at the end of the pathway
//   When called as an API on a server, the password is not used (insecure)
//  To get on-line syntax, invoke 	https://glygen.ccrc.uga.edu/sandbox/api/newPath.html
//    Perform a query and then look at the query API line near the top
//  Arguments are passed like this:
// https://glygen.ccrc.uga.edu/sandbox/api/paths/genPath_v2.php?end=G01354KL&fmt=json&scope=full&head=0


include '../../config.php';
include 'class_map.php';

try {
	if (empty($_GET)) {
		// $_GET is not populated when called from command line
		// the following lines are required for command-line invocation
		parse_str(implode('&', array_slice($argv, 1)), $_GET);
	} 
        if (!$servername) {
	    $servername = getenv('MYSQL_SERVER_NAME');
        }
	$password = getenv('MYSQL_PASSWORD');
} catch(Exception $e) {
	exit($e->getMessage());
}

// echo "servername now is $servername\n";
// echo "password now is $password\n";

function getDP($accession, $connection) {
	$sql = "SELECT COUNT(*) AS num FROM compositions WHERE glytoucan_ac=? ";
	$stmt = $connection->prepare($sql);
	$stmt->bind_param("s", $accession);
	$stmt->execute(); 
	$result = $stmt->get_result();
	$row = $result->fetch_assoc(); 
	$dp = $row["num"];
	return $dp;
}

function getRE($accession, $connection) {
	// generate a string representing the reducing end of 
	//   a glycan specified by its $accession
	$reducing_end_structure = "";
	$sql = "SELECT * from compositions WHERE glytoucan_ac=? AND parent_id='0'";
	$stmt = $connection->prepare($sql);
	$stmt->bind_param("s", $accession);
	$stmt->execute(); 
	$result = $stmt->get_result();
	$row_cnt = mysqli_num_rows($result);
	if ($row_cnt > 0) {
		$row = $result->fetch_assoc();	
		$reducing_end_structure = $row["anomer"] . "-" . $row["absolute"] . "-" . $row["form_name"];
	} else {
			$reducing_end_structure = "not found";
	}
	return $reducing_end_structure;
} // end of function getRE()

function getGlycanType($accession, $connection) {
	// generate a string representing the type ("O" or "N") of 
	//   a glycan specified by its $accession
	$type = "";
	$sql = "SELECT residue_id from compositions WHERE glytoucan_ac=? AND parent_id='0'";
	$stmt = $connection->prepare($sql);
	$stmt->bind_param("s", $accession);
	$stmt->execute(); 
	$result = $stmt->get_result();
	$row_cnt = mysqli_num_rows($result);
	if ($row_cnt > 0) {
		$row = $result->fetch_assoc();	
		$type = substr($row["residue_id"], 0, 1);
	} else {
		$type = "not found";
	}
	return $type;
} // end of function getGlycanType()


function getSubclass($accession, $connection, $classMap, $outFile) {
	// returns an array ($sClass) of subclasses for a specified glycan
	// class mappings are imported from file 'class_map.php'

	$sClass = [];
	$comp = [];

	//fwrite($outFile, "  canonical residues in glycan " . $accession . " \n    ");
	$composition_query = "SELECT residue_id FROM compositions WHERE glytoucan_ac=?"; 
	$composition_stmt = $connection->prepare($composition_query);
	$composition_stmt->bind_param("s", $accession);
	$composition_stmt->execute();
	$composition_result = $composition_stmt->get_result();
	while ($compRow = $composition_result->fetch_assoc()) {
		$nodeID = $compRow['residue_id'];
		array_push($comp, $nodeID);
		//fwrite($outFile, " " . $nodeID);
	}
	//fwrite($outFile, "\n");
	
	foreach($classMap as $thisGlycanClass => $classArray) {
		//fwrite($outFile, "\n  checking class " . $thisGlycanClass . "\n");
		// $classArray is a subarray indexed by $thisGlycanClass
		// the next two variables may be set to 0 (mismatch cases)
		$classMatches = 1;
		// no requirement (yet) for 'distributed subset' to be checked
		$subsetMatches = 1;
		
		//fwrite($outFile, "    the distributed subset for class '" . $thisGlycanClass . "' contains these nodes: ");
		foreach($classArray as $key => $value) {
			// this element of $classArray has value of -1; it's in the 'distributed subset' 
			if ($value == -1) {
				//fwrite($outFile, " " . $key);
				$subsetMatches = 0;
			}
			// thus, to assign $thisGlycanClass, at least one element of $comps 
			//   must be in the 'distributed subset' (next loop)
		}
		//fwrite($outFile, "\n");
		
		foreach($classArray as $key => $value) {
			if (in_array($key, $comp)) {
				// $comp contains $key
				//  a $key in the 'distributed subset' is found in $comp
				if ($value == -1) {
					$subsetMatches = 1;
					//fwrite($outFile, "    node " . $key . " is present in the glycan\n");
				}
				//  $key that must be absent IS PRESENT IN $comp - mismatch
				if ($value == 0) {
					$classMatches = 0;
					//fwrite($outFile, "    node " . $key . " must be absent, but is present in the glycan\n");
				}
			} else {
				// $key that must be present IS ABSENT IN $comp - mismatch
				if ($value == 1) {
					$classMatches = 0;
					//fwrite($outFile, "    a required node [" . $key . "] is NOT present in the glycan\n");						
				} 
			}
		}
				
		if ($subsetMatches == 0) {
			$classMatches = 0;
			//fwrite($outFile, "    the glycan does not contain any node in the distributed subset\n");
		}
		
		if ($classMatches == 1) {
			//fwrite($outFile, "    ## no conflicts: assigning class '" . $thisGlycanClass . "' to the glycan ##\n");
			array_push($sClass, $thisGlycanClass);
		}
	}

	//fwrite($outFile, "\n  glycan " . $accession . " assigned the following classes:\n");
	for ($i = 0; $i < sizeOf($sClass); $i++) 
		//fwrite($outFile, "   " . $sClass[$i] . "\n");
		
	return $sClass;
} // end of function getSubclass()


function bail($errCode, $dStr, $format, $data) {
	switch ($errCode) {
		case 0:
			$errMsg = "Connection to data base failed ";
			break;
		case 1:
			$errMsg = "Glycan [" . $dStr . "] could not be found: no pathways were generated";
			break;
		case 2:
			$errMsg = "Glycan type for [" . $dStr . "] could not be determined: no pathways were generated\"}";
			break;
		case 3:
			$errMsg = "Cannot yet generate pathways for O-glycans such as [" . $dStr . "]";
			break;
		case 4:
			$errMsg = "Starting point for pathway to "  . $dStr ." could not be determined; no pathways were generated";
			break;
		case 5:
			$errMsg = "Cannot yet generate pathways for glycans like [" . $dStr . "] not processed by Mgat1";
			break;
		case 9:
			$errMsg = "Missing intermediate; no pathways to [" . $dStr . "] could be generated at this time";
			break;
		default:
			$errMsg = "Unknown error";
			break;
	}
	
	unset($data['nodes']);
	unset($data['links']);
	unset($data['dp_distribution']);		
	unset($data['path_count']);
	
	switch ($format) {
		case "json":
			$data['error'] .= $errMsg;
			echo json_encode($data, JSON_PRETTY_PRINT);
			exit();
			break;
		case "tsv":
			exit("error\n" . $errMsg);
			break;
		default:
			exit($errMsg);
			break;
	}
}

function getHomologs($accession, $connection) {
	// NOT TESTED
	$homologs = [];

	// get residue info from compositions
	$comp_query = "SELECT residue_id,name,anomer,absolute,ring,parent_id,site,form_name,glycoct_index FROM compositions WHERE glytoucan_ac=?";
	$comp_stmt = $connection->prepare($comp_query);	
	$comp_stmt->bind_param("s", $accession);
	$comp_stmt->execute(); 
	$comp_result = $comp_stmt->get_result();

	// get biosynthetically related glycans from correlation
	$match_query = "SELECT homolog,relative_dp,shared FROM correlation WHERE glytoucan_ac=?";
	$match_stmt = $connection->prepare($match_query);
	$match_stmt->bind_param("s", $accession);
	
	
	//while ($row = $comp_result->fetch_assoc()) {
		// query correlations using the glytoucan_ac
		$match_stmt->execute(); 
		$match_result = $match_stmt->get_result();
		while ($match_row = $match_result->fetch_assoc()) {
			array_push($homologs, $match_row);
		}
	//}
	
	return ($homologs);
} // end of function getHomologs()


function getAlternate($accession, $connection, $properRE) {
	// NOT TESTED
	$homologs = getHomologs($accession, $connection);

	foreach($homologs as $thisH) {
		if (($thisH['relative_dp'] === 0) && ($thisH['homolog'] !== $accession) ) {
			$hRE = getRE($thisH['homolog'], $connection);
			if ($hRE === $properRE) return ($thisH['homolog']);
		}
	}
	
	return ("not found");
} // end of function getAlternate()


function getPrecursors($accession, $connection) {
	$precursors = [];
	$sql = "SELECT homolog FROM correlation WHERE glytoucan_ac=? AND relative_dp=-1";
	$stmt = $connection->prepare($sql);
	$stmt->bind_param("s", $accession);
	$stmt->execute(); 
	$result = $stmt->get_result();
	$count = 0;
	while ($row = $result->fetch_assoc()) {
		$precursors[$count] = $row['homolog'];
		$count++;
	}
	return $precursors;
} // end of function getPrecursors()



// use this on $nodes array with column 'dp'
function sortColumn($arr, $column) {
	$sorted_array = [];
	// sort associative array by column value
	$column_array  = array_column($arr, $column);
	// no ascending or descending - order depends entirely on $cmp
	asort($column_array);
	$i = 0;
	  foreach ($column_array as $key => $value)  {
		// echo "$key $value; "; 
		$sorted_array[$i] = $arr[$key];
		$i++;
	}
	return $sorted_array;
}




function nodeExists($data, $the_node) {
	// returns the 'id' of the_node, or '0' if the node does not exist
	$nds = $data['nodes'];
	$nIndex = 0;
	foreach($nds as $value) {
		if ($value["id"] == $the_node) $nIndex = $value["id"];
	}
	return $nIndex;
}



function addNode(&$data, $the_node, $connection) {
	// echo "   ...addNode... $the_node\n";
	// copy $data['nodes']
	$nds = $data['nodes'];
	// $count = sizeof($nds) + 1;
	$newNode = [];
	$newNode["id"] = $the_node;
	$newNode["dp"] = getDP($the_node, $connection);
	$nds[] = $newNode;
	// changes to $nds are local, so must refresh $data['nodes']
	$data['nodes'] = $nds;
	// echo "\nNODES\n";
	// echo json_encode($data['nodes'], JSON_PRETTY_PRINT);
	return $newNode["id"];
} // end of function addNode()



function addEdge(&$data, $child_node, $parent_node, $connection) {
	$clean = true;
	// add nodes to $data if they have not yet been added
	// nodeExists returns nodeIndex, or zero if non-existent
	$cIndex = nodeExists($data, $child_node);
	$pIndex = nodeExists($data, $parent_node);

	$nds = $data['nodes'];
	
	// get the residue_id (i.e., $diff) of the residue that is 
	//     present in the $child_node but not in the $parent_node
	$child_residues = [];
	$sql = "select residue_id,name from compositions where glytoucan_ac=?";
	$stmt = $connection->prepare($sql);
	$acc = $child_node;
	$stmt->bind_param("s", $acc);
	$stmt->execute(); 
	$result = $stmt->get_result();
	while ($row = $result->fetch_assoc()) {
		if (strpos($row["name"], '-') !== false) {
			//echo $child_node . " - FOUND SUBSTITUENT: " . $row["name"] . " in " . $row["residue_id"] . "<br>";
			$clean = false;
		}
		$child_residues[] = $row["residue_id"];
	}
	// echo "\nchild is " . $child_node . "\n  num residues is " . sizeof($child_residues) . "\n";
	
	$parent_residues = [];
	$acc = $parent_node;
	$stmt->execute(); 
	$result = $stmt->get_result();
	while ($row = $result->fetch_assoc()) {
		if (strpos($row["name"], '-') !== false) {
			// echo $parent_node . " - FOUND SUBSTITUENT: " . $row["name"] . " in " . $row["residue_id"] . "<br>";
			$clean = false;
		}		
		$parent_residues[] = $row["residue_id"];
	}
	// echo "\nparent is " . $parent_node . "\n  num residues is " . sizeof($parent_residues) . "\n";
	
	if ($clean === true) {
		// echo "Now adding link: " . $child_node . " - " . $parent_node . "<br>";
		if ($cIndex === 0) {
			// $child_node does not yet exist
			// addNode returns new (non-zero) nodeIndex
			$cIndex = addNode($data, $child_node, $connection);
		}

		if ($pIndex === 0) {
			// node $parent_node does not yet exist
			$pIndex = addNode($data, $parent_node, $connection);
		}
				
		$residue_affected = [];
		$effect = "none";
		$ra = "";
		$diff = array_diff($child_residues, $parent_residues);
		foreach($diff as $value) $ra = $value;
		$enzymes = [];	
		
		$sizeDiff = sizeof($child_residues) - sizeof($parent_residues);

		if ($sizeDiff == 0 ) {
			$effect = "Release or transfer entire glycan";
			//$residue_affected["residue_id"] = "";
		} else {
			$fullName = "";
			// new way to generate text for abiotic residues - "not_found_in" is deprecated 
			$sql = "SELECT name,anomer,absolute,form_name,rules.logic FROM canonical_residues LEFT JOIN rule_data ON (rule_data.focus = canonical_residues.residue_id) LEFT JOIN rules ON (rules.rule_id = rule_data.rule_id) WHERE residue_id=?";
			// $sql = "select name,anomer,absolute,form_name,not_found_in from canonical_residues where residue_id=?";
			$stmt = $connection->prepare($sql);
			$stmt->bind_param("s", $ra);
			$stmt->execute(); 
			$result = $stmt->get_result();
			while ($row = $result->fetch_assoc()) {
				$residue_affected["residue_id"] = $ra;
				$residue_affected["pubchem_name"] = $row["name"];
				$residue_affected["anomer"] = $row["anomer"];
				$residue_affected["absolute"] = $row["absolute"];
				$residue_affected["form_name"] = $row["form_name"];
				$greek = array('a' => "&alpha;", 'b' => "&beta;");
				// may need to extend $greek
				$fullName = $greek[$row["anomer"]] . "-" . $row["absolute"] . "-" . $row["form_name"];
				$residue_affected["full_name"] = $fullName;
				$logic = $row["logic"];
			}
			
			if ($sizeDiff > 0 ) {
				$effect = "Add " . $fullName;
			}
			if ($sizeDiff < 0 ) {
				$effect = "Remove " . $fullName;
			}
			
			if (strpos($logic, "abiotic")) { 
				// echo ($residue_affected["residue_id"] . " is abiotic<br>");
				$row["gene_name"] = "abiotic";
				$row["uniprot"] = "abiotic";
				$row["type"] = "";
				$row["species"] = "no known organism";
				$enzymes[] = $row;
			} else {
				$sql = "SELECT enzymes.gene_name,enzymes.uniprot,enzymes.type,enzymes.species FROM enzyme_mappings,enzymes WHERE enzymes.uniprot=enzyme_mappings.uniprot AND enzyme_mappings.residue_id=? ORDER BY species"; // @@!!
				$stmt = $connection->prepare($sql);
				$stmt->bind_param("s", $ra);
				$stmt->execute(); 
				$result = $stmt->get_result();
				while ($row = $result->fetch_assoc()) {
					$enzymes[] = $row;
				}
			}
		}
		

		
		$edge = array(
			"target" => $cIndex,
			"source" => $pIndex,
			"residue_affected" => $residue_affected,
			"effect" => $effect,
			"enzymes" => $enzymes
		);
		// get copy ($lnks) of $data['links']
		$lnks = $data['links'];
		// add a new link to $lnks
		$lnks[] = $edge;
		// replace $data['links'] with updated $lnks
		$data['links'] = $lnks;
		return true;
	}
	return false;
} // end of function addEdge()



function mostLikely($thisAcc, $links, $nodes, &$likelyNodes, &$likelyLinks) {
	$maxPaths = 0;
	$maxNode = []; 
	$maxLink = [];
	foreach($links as $k1 => $testLink) {
		if ($testLink['target'] === $thisAcc) {
			$testParent = $testLink['source'];
			foreach($nodes as $k2 => $testNode) {
				if ($testNode['id'] === $testParent) {
					$tempMax = max($maxPaths, $testNode['path_count']);
					if ($tempMax > $maxPaths) {
						$maxPaths = $tempMax;
						$maxNode = $testNode;
						$maxLink = $testLink;
					}
				}
			}
		}
	}
	
	if ($maxPaths > 0) {
		// populate $likelyNodes and $likelyLinks
		// for most likely path,there is only one path; 'path_count' is meaningless 
		$conciseMaxNode = [];
		$conciseMaxNode['id'] = $maxNode['id'];
		$conciseMaxNode['dp'] = $maxNode['dp'];
		array_push($likelyNodes, $conciseMaxNode);
		array_push($likelyLinks, $maxLink);
		// recursion toward $start
		mostLikely($maxNode['id'], $links, $nodes, $likelyNodes, $likelyLinks);
	}
} // end of function mostLikely()




// recursive traversal ($end to $start) of multiple pathways
//   traversal direction is opposite that of pathway direction
// return values propagate back to original $end node
// $end is the id of the traversal initiator (end of pathway)
// $start is the id of the traversal terminus (start of pathway)
// $globalRE is a string describing the reducing end structure common to all nodes
// $startDP is the number of residues in the traversal terminus (start of pathway)
// $rid is the canonical residue_id of the reducing end of each node
//   $rid = "NA" for N-glycans
// $data and $pc (path-count) are passed by reference so they can be appended 
//     at each recursion level
function pathDAG($end, $start, $globalRE, $startDP, $rid, &$data, $connection, &$pc) {
	// echo "\n#### ---pathDAG--- recursion for node $end ####\n";
	// echo "    globalRE is " . $globalRE . "\n";
	// if $start has been reached; success, but do not continue traversal
	if ($end == $start) {
		// echo "  ---pathDAG--- !FOUND START! TERMINATING traversal, outcome is 1\n\n";
		return 1;
	}
	
	$re = getRE($end, $connection);
	// echo "reducing end is $re\n";
	// do not use accessions with non-matching reducing ends
	if ($globalRE != $re) {
		// echo "  ---pathDAG--- bailing - reducing end mismatch, outcome is 0\n\n";
		return 0; 
	}
	

	// $startDP has been reached, but ($end != $start); dead end
	if ($startDP == getDP($end, $connection)) {
	// 	echo "  ---pathDAG--- dp ($startDP) of $end matches start, but no match: outcome is 0\n\n";
		return 0;
	}
	
	// $paths is the number of paths to $start from this node - initially 0
	$paths = 0; 
   
	// fetch all potential precursors of $end
	$precursors = getPrecursors($end, $connection);
	foreach ($precursors as $key => $value) {
		$new_end = $precursors[$key];
		// $outcome is the number of paths returned by a recursion of pathDAG
		if (!array_key_exists($new_end, $pc) ) {
			// if !$new_end exists (it's not a key in $pc), execute recursion
			// echo "---pathDAG--- calling recursion on " . $new_end . "\n";
			$outcome = pathDAG($new_end, $start, $globalRE, $startDP, $rid, $data, $connection, $pc);
		} else {
			// do not execute recursion, but
			// get the outcome that would result if recursion occurred
			$outcome = $pc[$new_end];
		}
		if ($outcome > 0) {
			// $new_end has traversed to $start, so process the results 
			//    $outcome is the number of paths taken
			// echo "@@@ pathDAG @@@ COMPLETED TRAVERSAL FROM $new_edge TO $start: NUMBER OF PATHS TAKEN IS $outcome\n";
			// add $new_end to $pc (path-count) - if it's not there already
			 $pc[$new_end] = $outcome;
			// add $end to $new_end edge to $data
			//  addEdge also adds nodes themselves, it they're not there already
			// if addEdge fails, do NOT increase value of $paths
			if (addEdge($data, $end, $new_end, $connection)) $paths += $outcome;
		}
	}

	return $paths;
}  // end of function pathDAG()



function printResult($format, $data, $head) {	
	switch ($format) {
		case "json":
			if ($head == 1) header('Content-Type: application/json; charset=utf-8');
			echo json_encode($data, JSON_PRETTY_PRINT);
			break;
		case "tsv":
			echo "tsv format not yet supported\n";
			break;
		default:
			break;
	}
}


// main method follows
try {
	//$logFile = fopen("php.log", "w") or die("Unable to open log file!");
	$noteCount = 0;
	$end = $_GET['end'];
	$head = $_GET['head'];
	$start = 'not determined';
	$queryEnd = $end;
	//fwrite($logFile, "\n\n ### " . date("Y/m/d H:i:s") . " GMT ###\n" );
	//fwrite($logFile, "Generating pathways for glycan " . $end . "\n");
	$format = $_GET['fmt'];
	$scope = $_GET['scope'];


	$data = [];
	$data['glytoucan_ac'] = $queryEnd;	
	$data['error'] = "";
	$data['notes'] = [];

	// Create connection

// echo "servername now is $servername\n";
// echo "username now is $username\n";
// echo "password now is $password\n";
// echo "dbname now is $dbname\n";
	$connection = new mysqli($servername, $username, $password, $dbname);

	// Check connection
	if ($connection->connect_error) {
		bail(0, $end, $format, $data);
	}

	$reEnd = getRE($end, $connection);
	//fwrite($logFile, " glycan reducing end structure is " . $reEnd . "\n");
	
	if ($reEnd == 'not found') {
		bail(1, $end, $format, $data);
	} 
	
	$glycanType = getGlycanType($end, $connection);
	//fwrite($logFile, " glycan type is " . $glycanType . "\n\n");
	if ($glycanType == 'not found') {
		bail(2, $end, $format, $data);
	} 

	
	
	switch ($glycanType) {
		case "O":
			$rid = "OC";
			$subclass = getSubclass($end, $connection, $glycanClassMap, $logFile);
			foreach($subclass as $value) {
				// use this to set $start
				if ($value == "ogalnac") {
					$properRed = 'a-D-GalpNAc';
					if ($reEnd != $properRed) {
						$data['notes'][$noteCount++] = "reducing end mismatch [" . $reEnd . "]";
						$end = getAlternate($end, $connection, $properRed);
						// getAlternate ensures that $reEnd == $properRed
						$data['notes'][$noteCount++] = "homologous pathway end-point [" . $end . "]";
						//fwrite($logFile, "\n  reducing end mismatch [" . $reEnd . "]; homologous pathway end-point [" . $end ."]\n");
						$reEnd = $properRed;
					}
					$start = $startAccession['ogalnac'];
				} 
                        }
			$data['notes'][$noteCount++] = "pathway start-point [" . $start . "]";			
			//fwrite($logFile, "\n  pathway start-point is " . $start . "\n");
			//fwrite($logFile, "  pathway end-point is " . $end . "\n");
			break;
		case "N":
			// get $subclass to identify $start for this glycan
			$rid = "NA";
			$subclass = getSubclass($end, $connection, $glycanClassMap, $logFile);
			foreach($subclass as $value) {
				// use this to set $start
				if ($value == "hybrid") {
					$properRed = 'b-D-GlcpNAc';
					if ($reEnd != $properRed) {
						$data['notes'][$noteCount++] = "reducing end mismatch [" . $reEnd . "]";
						$end = getAlternate($end, $connection, $properRed);
						// getAlternate ensures that $reEnd == $properRed
						$data['notes'][$noteCount++] = "homologous pathway end-point [" . $end . "]";
						//fwrite($logFile, "\n  reducing end mismatch [" . $reEnd . "]; homologous pathway end-point [" . $end ."]\n");
						$reEnd = $properRed;
					}
					$start = $startAccession['hybrid'];
				} 
				
				if ($value == "complex") {
					$properRed = 'b-D-GlcpNAc';
					if ($reEnd != $properRed) {
						$data['notes'][$noteCount++] = "reducing end mismatch [" . $reEnd . "]";
						$end = getAlternate($end, $connection, $properRed);
						// getAlternate ensures that $reEnd == $properRed
						$data['notes'][$noteCount++] = "homologous pathway end-point [" . $end . "]";
						//fwrite($logFile, "\n  reducing end mismatch [" . $reEnd . "]; homologous pathway end-point [" . $end ."]\n");
						$reEnd = $properRed;
					} 
					$start = $startAccession['complex'];				
				}  
				
				// N-glycans lacking residue N2 apparently have not been processed by Mgat1
				if ($value == "no_mgat1 ") bail(5, $end, $format, $data); 
				// no_mgat1 pathways not ready yet (either a-D-GlcpNAc or b-D-GlcpNAc)

				if ($value == "core_fucosylated") {
					$data['notes'][$noteCount++] = "glycan is core-fucosylated";
				}
			}
			$data['notes'][$noteCount++] = "pathway start-point [" . $start . "]";			
			//fwrite($logFile, "\n  pathway start-point is " . $start . "\n");
			//fwrite($logFile, "  pathway end-point is " . $end . "\n");
			break;
		default:
			break;
	}
		
			
	// set up node and link variables
	$pc = [];  // the path count for each node
	$data['nodes'] = []; 
	$data['links'] = [];
	
	$endDP = getDP($end, $connection);
	$startDP = getDP($start, $connection);
	if ($start == "not determined") {
		bail(4, $end, $format, $data);
	}
	// explicit pathway generation code follows
	
	// traverse glycotree to find all paths
	$totalPaths = pathDAG($end, $start, $reEnd, $startDP, $rid, $data, $connection, $pc);

	if ($end !== $queryEnd) {
		//fwrite($logFile, "  adding pseudo-end " . $queryEnd . " to account for reducing end mismatch\n");
		addEdge($data, $queryEnd, $end, $connection);
	}
		
	$dpDistribution = [];
	for ($i = $startDP; $i <= $endDP; $i++) {
		$dpDistribution[$i] = 0;
	}

	$nodeArray = $data['nodes'];
	foreach ($nodeArray as $key => $value) {
		$pcKey = $value['id'];
		if (($pcKey === $end) || ($pcKey === $queryEnd) ) {
			$nodeArray[$key]['path_count'] = $totalPaths;
		} else {
			$nodeArray[$key]['path_count'] = $pc[$pcKey];
		}
		$dpDistribution[$value['dp']]++;
	}
	
	$sortedNodes = sortColumn($nodeArray, 'dp');
	$data['nodes'] = $sortedNodes;
	$data['dp_distribution'] = $dpDistribution;
	$data['path_count'] = $totalPaths;

	// explicit pathway generation code is above
	
	// pathways to glycans with proper reducing end must have at least 2 elements
	$threshold = 2;
        if ($glycanType == 'O') $threshold = 1;
	// pathways to glycans with improper reducing end must have at least 3 elements
	if ($queryEnd !== $end) $threshold += 1;
	
	if (count($data['nodes']) < $threshold) {
		bail(9, ($queryEnd), $format, $data);
	}
	if ($scope === "full") {
		printResult($format, $data, $head);
	}
	if ($scope === "likely") {
		// the following filter (likely) should be an independent function; msny arguments
		//  $data, $end, &$likelyNodes, &$likelyLinks
		$likelyNodes = [];
		$likelyLinks = [];
		mostLikely($queryEnd, $data['links'], $data['nodes'], $likelyNodes, $likelyLinks);
		foreach($data['nodes'] as $key => $testNode) {
			if ( ($queryEnd !== $end) && ($testNode['id'] === $queryEnd) ) {
				// for most likely path,there is only one path; 'path_count' is meaningless 
				$conciseNode = [];
				$conciseNode['id'] = $testNode['id'];
				$conciseNode['dp'] = $testNode['dp'];
				array_push($likelyNodes, $conciseNode);			
			}
		}
		$sortedNodes = sortColumn($likelyNodes, 'dp');
		$likelyData = [];
		$likelyData['glytoucan_ac'] = $queryEnd;	
		$likelyData['error'] = $data['error'];
		$likelyData['notes'] = $data['notes'];
		$likelyData['nodes'] = $sortedNodes;
		$likelyData['links'] = $likelyLinks;
		printResult($format, $likelyData, $head);
	}
	// fclose($logFile);

} catch (mysqli_sql_exception $e) { 
	echo "MySQLi Error Code: " . $e->getCode() . "<br />";
	echo "Exception Msg: " . $e->getMessage();
	exit();
}
$connection->close();

?>

