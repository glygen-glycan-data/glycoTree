<?php

function checkRE($value) {
	if ( ($value['anomer'] === "b")  && ($value['absolute'] === "D") &&
	($value['ring'] === "p") ) {
		return true;
	}
	return false;
}

function getFeatures($resList, $accession, $homologs, $fullymapped, $connection) {
	// generate caveat objects and put into array $caveats

	$caveats = [];
	// $groupedRuleData is an array of arrays of ruleData arrays, grouped (1st index) by rule_id
	$groupedRuleData = [];
	// $rawRuleData is an array of ruleData arrays, indexed by 'instance'
	$rawRuleData = [];
	$countQuery = "SELECT COUNT(rule_id) AS rule_count FROM rules";
	$stmt = $connection->prepare($countQuery);	
	$stmt->execute(); 
	$result = $stmt->get_result();
	$row = $result->fetch_assoc();
	$ruleCount = $row['rule_count'];
	// initialize elements of ruleData array
	for ($i = 1; $i <= $ruleCount; $i++) $groupedRuleData[$i] = [];
	
	$resStructureArray = [];
	$otherStructureArray = [];
	$ruleArray = [];
	$queryText = "SELECT rule_data.*,rules.*,canonical_residues.anomer,canonical_residues.absolute,canonical_residues.form_name FROM rule_data LEFT JOIN rules ON (rule_data.rule_id = rules.rule_id) LEFT JOIN canonical_residues ON (canonical_residues.residue_id = rule_data.other_residue) WHERE focus=?";
	foreach ($resList as $value)  {
		// reinitialize eCaveats for each residue;
		$resID = $value['residue_id'];
		$findStr = array("a", "b");
		$replaceStr = array("&alpha;", "&beta;");
		$anomer = str_replace($findStr,$replaceStr,$value['anomer']);
		$resStructure =  $anomer . "-" . $value['absolute'] . "-" . $value['form_name'];
		$resStructureArray[$resID] = $resStructure;
                

		$result = doQuery($queryText, $connection, "s", $resID);
		while ($row = $result->fetch_assoc()) {
			if ( ($row['status'] == "active") || ($row['status'] == "disputed") ) {
				array_push($ruleArray, $row);
				$theRule = [];
				$ruleID = $row['rule_id'];
				$theRule['rule_id'] = $ruleID;
				$instance = $row['instance'];
				$theRule['instance'] = $instance;
				$focus = $row['focus'];
				$theRule['focus'] = $focus;
				$theRule['enzyme'] = $row['enzyme'];
				$otherResidue = $row['other_residue'];
				$theRule['other_residue'] = $otherResidue;
				// retrieve structure of otherResidue, when appropriate
				if (!is_null($otherResidue) ) {
					$anomer = str_replace($findStr,$replaceStr,$row['anomer']);
					$resStructure =  $anomer . "-" . $row['absolute'] . "-" . $row['form_name'];
					$otherStructureArray[$otherResidue] = $resStructure; // BAD!!!
				}
				$theRule['polymer'] = $row['polymer'];
				$theRule['taxonomy'] = $row['taxonomy'];
				// $theRule['proposer_id'] = $row['proposer_id']; // not required
				$theRule['logic'] = $row['logic'];
				$theRule['refs'] = $row['refs']; 
				$theRule['comment'] = $row['comment']; 
				$theRule['status'] = $row['status'];
				$theRule['class'] = $row['class']; 
				$theRule['description'] = $row['description']; 
				$ruleFind = array("[focus]", "[enzyme]", "[taxonomy]", "[other_residue]", "[polymer]");
				// include both residue_id and residue_name in 'assertion'
				$ruleReplace = array($focus . " (" . $resStructureArray[$focus] . ")",
						$theRule['enzyme'], $theRule['taxonomy'],
						$otherResidue. " (" . $otherStructureArray[$otherResidue] . ")",
						$theRule['polymer']);
				$theRule['assertion'] = str_replace($ruleFind, $ruleReplace, $theRule['logic']);
				$groupedRuleData[$ruleID][$instance] = $theRule;
			}
		}
		
	}

	$reqViolation = [];
	$blockViolation = [];
	$structureViolation = [];
	$limitViolation = [];
	for ($i = 1; $i <= $ruleCount; $i++) {
		// each of the different canonical rules
		if (sizeof($groupedRuleData[$i]) > 0) {
			// each instance of rule $i
			foreach ($groupedRuleData[$i] as $key => $value) {
				// $value is an associative array desribing an instance of rule $i
				$focus = $value['focus'];
				$instance = $value['instance'];
				if (strpos($value['logic'], "requires residue [other_residue]")) {
					$reqRes = $value['other_residue'];
					$resinStruct = array_key_exists($reqRes, $resStructureArray);
					if (!$resinStruct) {
						// the glycan does NOT contain reqRes (it is not in $resStructureArray)
						if (!$fullymapped) {
						    array_push($reqViolation, "enzyme " . $value['enzyme'] .
							  " transfers residue " . $focus . 
							  " (" . $resStructureArray[$focus] . ") in " .
							  $value['taxonomy'] . " - missing residue " . $reqRes .
							  " (" . $otherStructureArray[$reqRes] . ")");
                                                } else {
						    array_push($rawRuleData, $value);
                                                }
					}
				}
				if (strpos($value['logic'], "blocked by residue [other_residue]")) {
					$blockRes = $value['other_residue'];
					$resinStruct = array_key_exists($blockRes, $resStructureArray);
					if ($resinStruct || !$fullymapped) {
						// the glycan contains blockRes or there are unmapped residues
						if (!$resinStruct) {
						     array_push($blockViolation, "enzyme: " . $value['enzyme'] .
							  "; &nbsp; transferred residue: " . $value['focus'] . 
							  " (" . $resStructureArray[$focus] . ")" .
							  "; &nbsp; blocking: " . $blockRes .
							  " (" . $otherStructureArray[$blockRes] . ")" .
							  "; &nbsp; species: " . $value['taxonomy']);
                                                } else {
						     array_push($rawRuleData, $value);
                                                }
					}
				}
				if (strpos($value['logic'], "abiotic")) {
					array_push($structureViolation, $value['assertion']); 
					array_push($rawRuleData, $value);
				}
				if (strpos($value['logic'], "limited to")) {
					array_push($limitViolation, $value['assertion'] );
					array_push($rawRuleData, $value);
				}
			}
		}
	}
	
	if (sizeof($blockViolation) > 0) {
		$newCaveat = [];
		$blockMsg = "One or more enzymes involved in the synthesis  of " .
			$accession . " is blocked by a residue in this glycan. Thus, " .
			$accession . " is either abiotic (e.g., chemically synthesized) or synthesized " .
			"by a pathway that does not involve any of the enzymes listed below: ";
		$any = false;
		$sep = "";
		for ($i = 0; $i < sizeof($blockViolation); $i++) {
                        $keepviolation = true;
                        for ($j = 0; $j< sizeof($rawRuleData); $j++) {
                            $focus = $rawRuleData[$j]["focus"];
                            $enzyme = $rawRuleData[$j]["enzyme"];
			    $bvstr = "enzyme: " . $enzyme . "; &nbsp; transferred residue: " . $focus . " ";
                            if (strstr($blockViolation[$i],$bvstr) !== false) {
                                $keepviolation = false;
                                break;
                            }
                        }
                        if (!$keepviolation) {
                            continue;
                        }
			$blockMsg .= $sep . $blockViolation[$i];
			$sep = "# ";
                        $any = true;
		}
                if ($any) {
		    $newCaveat['msg'] = $blockMsg;
		    array_push($caveats, $newCaveat);
                }
	}
	
	if (sizeof($reqViolation) > 0) {
		$newCaveat = [];
		$reqMsg = "One or more enzymes involved in the synthesis of " .
			$accession . " requires a residue that is missing. " . "Thus, " .
			$accession . " is either abiotic (e.g., chemically synthesized), synthesized " .
			"by a pathway that does not involve any of the enzymes listed below, or generated " . 
			"from another glycan by hydrolysis of a required residue: ";
		$sep = "";
		for ($i = 0; $i < sizeof($reqViolation); $i++) {
			$reqMsg .= $sep . $reqViolation[$i];
			$sep = "# ";
		}
		$newCaveat['msg'] = $reqMsg;
		array_push($caveats, $newCaveat);
	}
	
	if (sizeof($structureViolation) > 0 ) {
		$newCaveat = [];
		$structureMsg = "One or more residues in " .
			$accession . " is abiotic. " . "Thus, " . $accession . 
			" is itself abiotic (e.g., chemically synthesized or mis-characterized):";
		$sep = "";
		for ($i = 0; $i < sizeof($structureViolation); $i++) {
			$structureMsg .= $sep . $structureViolation[$i];
			$sep = "# ";
		}
		$newCaveat['msg'] = $structureMsg;
		array_push($caveats, $newCaveat);
	}
	
	if (sizeof($limitViolation) > 0 ) {
		$newCaveat = [];
		$limitMsg = "One or more residues in " .
			$accession . " is limited to certain taxonomic species. " .
			"Therefore, " . $accession . " is itself taxonomically limited:";
		$sep = "";
		for ($i = 0; $i < sizeof($limitViolation); $i++) {
			$limitMsg .= $sep . $limitViolation[$i];
			$sep = "# ";
		}
		$limitMsg .= "</li></ul>";
		$newCaveat['msg'] = $limitMsg;
		array_push($caveats, $newCaveat);
	}
	
	$features['rules'] = $ruleArray;
	$features['caveats'] = $caveats;
	$features['rule_violations'] = $rawRuleData;
	return $features;
}  // end of function getFeatures()


function sortCustom($arr, $column, $cmp) {
// sort associative array by column value using comparator '$cmp'
		$column_array  = array_column($arr, $column);
		// no ascending or descending - order depends entirely on $cmp
		uasort($column_array, $cmp);
		$i = 0;
        foreach ($column_array as $key => $value)  {
			// echo "$key $value; "; 
			$sorted_array[$i] = $arr[$key];
			$i++;
		}
	return $sorted_array;
}


function gtree_comparator($a, $b) {
//  custom comparator for sorting glycoTree residue_id values
//	  - first both non-numeric (e.g., 'NA' - 'NC')  order lexically
//	  - next both partially numeric (e.g., 'N31' - 'N324') order numerically
//	  - last both numeric (e.g., '17' - '12') order numerically
//	  - for mixed numeric and partially numeric (e.g., 'N5' - '35') put numeric last
	preg_match('/[A;-Z]/', $a, $matches, PREG_OFFSET_CAPTURE);
	$ap = $matches[0][1]; // position of [A-Z] in $a, NULL if not found
	$ac = (!is_null($ap)); // boolean, true if contains [A-Z]
	
	preg_match('/[A-Z]/', $b, $matches, PREG_OFFSET_CAPTURE);
	$bp = $matches[0][1]; // position of [A-Z] in $a, NULL if not found
	$bc = (!is_null($bp)); // boolean, true if contains [A-Z]

	//get the numeric parts $an and $bn
	$an = $a;
	$bn = $b;

	if ($ac) { // $a is canonical
		// reset $an only if $a contains a numeric substring
		preg_match('/[0-9]/', $a, $matches, PREG_OFFSET_CAPTURE);
		if (!is_null($matches)) $an = (int) substr($a, $ap+1);
	}
	if ($bc) { // $b is canonical
		// reset $bn only if $b contains a numeric substring
		preg_match('/[0-9]/', $b, $matches, PREG_OFFSET_CAPTURE);
		if (!is_null($matches)) $bn = (int) substr($b, $bp+1);
		// TODO: fix the above line, which causes php to log a 'Notice'
		//    Undefined offset   or 
		//    Trying to access array offset on value of type null
	}
	
	// exactly one of the two is numeric
	if ( (!$ac || !$bc ) && ( $ac || $bc ) ) { 
			return -1 * strcmp($a, $b);
	}

	// both contain [A-Z] or both are numeric
	if ($an == $bn) {
		return 0;
	}
	return ($an < $bn) ? -1 : 1;
	
}

// the functions doQuery() and queryComposition() allow composition Object to be retrieved from the DB
// when the composition Object is generated by another method, queryComposition is not called
function doQuery($q, $con, $types, ...$params) {
	//  execute a mysql query with a variable number of arguments
	$stmt = $con->prepare($q);	
	$stmt->bind_param($types, ...$params);
	$stmt->execute(); 
	$result = $stmt->get_result();
	return ($result);
	//$numargs = func_num_args();
} // end of function doQuery()


function queryComposition($acc, $con) {
	// get canonical residue info from compositions
	$queryText = "SELECT residue_id,name,anomer,absolute,ring,parent_id,site,form_name,glycoct_index,canonical_residue_index FROM compositions WHERE glytoucan_ac=?";
	$result = doQuery($queryText, $con, "s", $acc);
	return ($result);
} // end of function queryComposition()

function queryStructure($acc, $con) {
	$queryText = "SELECT tree,abiotic,mapped,validated,inglygen FROM structure WHERE glytoucan_ac=?";
	$result = doQuery($queryText, $con, "s", $acc);
	return ($result);
} // end of function queryStrucure()

/****************************
* The integrateData() function takes an array of associative arrays as its second argument
*   This array ($compArray) can be generated from a mysql query or from explicit data,	
*    facilitating php scripts that use integrateData() to describe glycans 
*    that are not in the DB
*  example - one element of $compArray:
*  array(9) {
*   ["residue_id"]=>
*   string(2) "N1"
*   ["name"]=>
*   string(3) "Man"
*   ["anomer"]=>
*   string(1) "a"
*   ["absolute"]=>
*   string(1) "D"
*   ["ring"]=>
*   string(1) "p"
*   ["parent_id"]=>
*   string(2) "NC"
*   ["site"]=>
*   string(1) "3"
*   ["form_name"]=>
*   string(4) "Manp"
*   ["glycoct_index"]=>
*   int(6)
*  }
***************************/
function integrateData($connection, $compArray, $accession, $structure) {
	// integrates the data associated with the accession

	// $glycan is an associative array that holds the integrated, hierarchical data
	//    for the glycan with a specific glytoucan_ac value
	$glycan = [];
	$resid = "";
	$glycan["glytoucan_ac"] = $accession;
	// get cannonical residue annotations from canonical_residues
	$canon_query = "SELECT residue_name,notes FROM canonical_residues WHERE residue_id=?";
	$canon_stmt = $connection->prepare($canon_query);
	$resid = "";
	$canon_stmt->bind_param("s", $resid);

	// get enzyme information for residues from enzyme_mappings
	$map_query = "SELECT enzyme_mappings.type,enzyme_mappings.uniprot,enzyme_mappings.notes,enzymes.protein_refseq,enzymes.dna_refseq,enzymes.gene_name,enzymes.gene_id,enzymes.species,enzymes.branch_site_specificity,enzymes.orthology_group FROM enzyme_mappings,enzymes WHERE enzyme_mappings.uniprot=enzymes.uniprot AND residue_id=?";
	$map_stmt = $connection->prepare($map_query);
	$map_stmt->bind_param("s", $resid);

	// get biosynthetically related glycans from correlation
	$match_query = "SELECT homolog,relative_dp,shared FROM correlation WHERE glytoucan_ac=?";
	$match_stmt = $connection->prepare($match_query);
	$match_stmt->bind_param("s", $accession);

	$residues = [];
	$fullymapped = true;
	foreach($compArray as $i => $resdata) {
		$enzymes = [];
		$homologs = [];
		$resid = $resdata["residue_id"];
		$fullrow = $resdata;
		// query canonical_residues using the current residue_id
		$canon_stmt->execute(); 
		$canon_result = $canon_stmt->get_result();
		if ( ($canon_result->num_rows) > 0) {
			$canon_row = $canon_result->fetch_assoc();
			$fullrow = array_merge($resdata, $canon_row);
		}

		if (is_null($fullrow['residue_name'])) {
			$fullrow['glycotree'] = "none";
                        $fullymapped = false;
		} else {
			$fullrow['glycotree'] = explode("_", $fullrow['residue_name'])[0];
		}
                $fullrow['canonical_residue_index'] = explode(";", $fullrow['canonical_residue_index'])[0];
		// query enzyme_mappings using the current residue_id
		$map_stmt->execute(); 
		$map_result = $map_stmt->get_result();
		while ($map_row = $map_result->fetch_assoc()) {
			array_push($enzymes, $map_row);
		}
		$fullrow["enzymes"] = $enzymes;
		array_push($residues, $fullrow);

		// query correlations using the glytoucan_ac
		$match_stmt->execute(); 
		$match_result = $match_stmt->get_result();
		while ($match_row = $match_result->fetch_assoc()) {
			array_push($homologs, $match_row);
		}
	}

        $glycan["structure"] = $structure;
        $glycan["fullymapped"] = $fullymapped;
	$features = getFeatures($residues, $accession, $homologs, $fullymapped, $connection);

	// array sort by column value using custom comparator
	// $sorted = $residues;
	$sorted = sortCustom($residues, 'residue_id', 'gtree_comparator');
	$glycan["residues"] = $sorted;
	$glycan["related_glycans"] = $homologs;
	$glycan["rules"] = $features['rules'];
	$glycan["caveats"] = $features['caveats'];
	$glycan["rule_violations"] = $features['rule_violations'];
        foreach ($glycan["residues"] as $rind => $res) {
            $newenzymes = [];
            $resid = $res["residue_id"];
            foreach ($res["enzymes"] as $eind => $enz) {
                $good = true;
                foreach ($glycan["rule_violations"] as $rvind => $rv) {
                    if (($rv["focus"] == $resid) && ($rv["enzyme"] == $enz["uniprot"])) {
                        # $glycan["residues"][$rind]["enzymes"][$eind]["rule_violation"] = $rv["instance"];
                        $good = false;
                        break;
                    }
                }
                if ($good) {
                    array_push($newenzymes,$enz);
                }
            }
            $glycan["residues"][$rind]["enzymes"] = $newenzymes;
        }
	return($glycan);

} // end function integrateData()


function fetchBitSetData($connection, &$bitData, &$probe_b64, $acc) {
	$bit_query = "SELECT * FROM bitSet"; 
	$bit_stmt = $connection->prepare($bit_query);
	$bit_stmt->execute();
	$bit_result = $bit_stmt->get_result();
	while ($row = $bit_result->fetch_assoc()) {
		$bitData[$row['glytoucan_ac']] = $row['base64_composition'];
		if ($row['glytoucan_ac'] == $acc) {
			$probe_b64 = $row['base64_composition'];
		}
	}	
} // end of function fetchBitSetData()


function checkFuzzy($bitset) {
	$fuzzy = $bitset->get(0) ? true : false;
	return($fuzzy);
} // end of function checkFuzzy()


function compareBitSets($bitData, $probe_bs, &$identical, &$extended, &$pruned, &$extended_fuzzy) {
	$probeFuzzy = checkFuzzy($probe_bs);
	// echo "\nprobeFuzzy is " . $probeFuzzy;
	// fuzziness is specified: clear(0) to facilitate logic (only on local copy)
	$probe_bs->clear(0);

	$probeMapped = $probe_bs->cardinality();
	if ($probeMapped == 0) return; // glycans related to the 'null glycan' are meaningless
	foreach($bitData as $a => $data) {
		$target_bs = new BitSet($data, "base64");
		$targetFuzzy = checkFuzzy($target_bs);
		// fuzziness is specified: clear(0) to facilitate logic (only on local copy)
		$target_bs->clear(0); 
		$mapped = $target_bs->cardinality();
		$target_bs->and($probe_bs);
		$matched = $target_bs->cardinality(); // i.e., the number of common bits
		// cp, ct, and cAnd hold information for classifying targets

		if (!$probeFuzzy) { // process probes that are NOT fuzzy
			if (!$targetFuzzy) {
				// neither probe nor target are fuzzy: and logic is easy
				if ($matched == $probeMapped) { // all bits in probe are in target
					if ($matched == $mapped) { // all bits in target are in probe
						// target is identical to probe		
						array_push($identical,
									  array("id"=>$a, "mapped"=>$mapped, "matched"=>$matched));			
					} else { // all bits in probe are in target but not vice verse
						// target extends probe
						array_push($extended, 
									  array("id"=>$a, "mapped"=>$mapped, "matched"=>$matched));
					}
				} else { // NOT all bits in probe are in target
					if ($matched == $mapped) { // all bits in target are in probe
						// target is pruned version of probe
						array_push($pruned, array("id"=>$a, "mapped"=>$mapped, "matched"=>$matched));
					}
				}
			} else {
				// only target is fuzzy
				if ($matched == $probeMapped) { // all bits in probe are in target
					// target is a fuzzy extension of probe	
					array_push($extended_fuzzy, 
								  array("id"=>$a, "mapped"=>$mapped, "matched"=>$matched));
				}
			}
		} else { // process fuzzy probes
			// can reliably find only non-fuzzy pruned versions of fuzzy probes
			if (!$targetFuzzy) {
				// only probe is fuzzy
				if ($matched == $mapped) { // all bits in target are in probe
					// target is a (non-fuzzy) pruned version of fuzzy probe	
					array_push($pruned, 
								  array("id"=>$a, "mapped"=>$mapped, "matched"=>$matched));
				}
			}
		}
	}	
	usort($extended, function($a, $b) {
	  return $a['mapped'] - $b['mapped'];
	});
	usort($extended_fuzzy, function($a, $b) {
	  return $a['mapped'] - $b['mapped'];
	});
	usort($pruned, function($a, $b) {
	  return $b['mapped'] - $a['mapped'];
	});
} // end of function compareBitSets()


?>
