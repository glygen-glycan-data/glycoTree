<?php

	// each glycanClass is mapped to a collection of residue_id's
	//   to identify its  class
	//    residues with values of 0 cannot be in the glycan
	//    residues with values of 1 must be in the glycan
	//    at least one residue in the 'distributed subset' (value = -1)
	//       must also be in the glycan
	$glycanClassMap['hybrid'] = array('NC'=>'1', 'N2'=>'1', 'N19'=>'1', 'N20'=>'1');
	$glycanClassMap['complex'] = array('NC'=>'1', 'N2'=>'1', 'N5'=>'1');
	$glycanClassMap['himannose'] = array('NC'=>'1', 'N21'=>'1', 'N19'=>'1', 'N20'=>'1');
	# $glycanClassMap['no_mgat1'] = array('NC'=>'1', 'N2'=>'0');
	# $glycanClassMap['core_fucosylated'] = array('NC'=>'1', 'N15'=>'-1', 'N40'=>'-1');

	$startAccession['hybrid'] = 'G22768VO';
	$startAccession['complex'] = 'G22768VO';
	$startAccession['himannose'] = 'G22768VO';

        $glycanClassMap['ogalnac'] = array('OC'=>'1');
        $glycanClassMap['ofuc'] = array('OC1'=>'1');
        $glycanClassMap['oglcnac'] = array('OC2'=>'1');
        $glycanClassMap['oglc'] = array('OC3'=>'1');
        $glycanClassMap['oman'] = array('OC4'=>'1');

        $startAccession['ogalnac'] = 'G57321FI';
        $startAccession['ofuc'] = 'G96881BQ';
        $startAccession['oglcnac'] = 'G49108TO';
        $startAccession['oglc'] = 'G71142DF';
        $startAccession['oman'] = 'G61491DK';
	
?>
