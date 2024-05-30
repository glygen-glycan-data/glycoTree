/*  READ THIS BEFORE MODIFYING:
- in SVG, sometimes it is necessary to invoke jquery functions, other times
   it is necessary to invoke vanilla javascript functions
- parentsUntil() returns an ORDERED array, NOT inclusive of the "Until" element
   the order is from closest ancestor to most distant ancestor
- many jquery functions return arrays, not single elements
- most mouse events are triggered from <g> elements with IDs - do NOT navigate
    starting with the actual clicked element, but rather from the <g> element
	containing it
- added annotations are apended to SVG, and as such are direct children of the <svg> element
*/

// constants
var v = 5; // verbosity of console.log
var nodeType = {'R':'residue', 'L':'link', 'LI':'text', 'C':'canvas', 'A':'annotation'};
var greek = {'a': '&alpha;', 'b': '&beta;', 'x': '?','o': 'acyclic', 'n': ""};
// data variables
var acc = []; // an array of GlyTouCan accessions
var hRes = ""; // residue_id of residue to hightlight on load
var svgEncoding = [];
var data = [];
var selectedData = [];
var svgCanvasColor = "rgb(255,255,255)";
var annotated = false;
var overNode = false;
var relatedDataExists = false;
var jsonCount = 0;
var svgCount = 0;
var dataAvailable = true;
var unavailable = [];
var allDataRequested = false;
var glycanSelector = "all";
var probeEnd = "";
var probeSubCount = "0";
var pathStart = "none";
var alternate = null;
var trees = new Array();

document.onkeydown = keySet;

function highlight(node) {
	if (v > 3) console.log("  highlighting node " +
							$(node).attr("id"));
	resetSVG();
	var parts = parseID($(node).attr('id'));
	var localAcc = parts['accession'];
	var type = parts['type'];
	// set thisCanvas to canvas of the clicked tree
	var thisCanvas = null;
	if (type == "C") {
		// a canvas was clicked
		thisCanvas = $(node);
	} else {
		// a node, link, or link text was clicked
		var sd = $('#' + sDiv);
		thisCanvas = sd.find("[id^=C-" + localAcc + "]");
		// grey out all nodes and edges in the clicked image 
		greyOut(localAcc); 
		$(node).css('opacity', 1.0);
	}
	// highlight thisCanvas
	thisCanvas.css('fill', highlightColor);
	
	if (v > 5) console.log('highlighted canvas has id: ' +
								thisCanvas.attr('id'));
} // end of function highlight()


function resetSVG() {
	// set all svg objects to their default values
	if (v > 5) console.log("Setting Defaults");
	resetAllBackground(defaultCanvasColor);
	getClickableSet().each(function( index ) {
		$(this).css('opacity', 1.0);
	});
} // end of function resetSVG()


function greyOut(acc) {
	if (v > 5) console.log("  greying out elements of " + acc);
	var sd = $('#' + sDiv);
	var nodeSet = sd.find('.' + acc + '_node').children();
	nodeSet.css('opacity', 0.3);
	var edgeSet = sd.find('.' + acc + '_edge').children();
	edgeSet.css('opacity', 0.3);
}

function getClickableSet(acc) {
	//  initially, globally select all clickable elements 
	//     selected by class of enclosing <g>
	//		   - i.e., the class names end with '_node' or '_edge'
	//    children of <g> elements with class ending in '_node'
	//     that are in the body of ifr
	var sd = $('#' + sDiv);
	var  clickableSet = sd.find('[class$=_node]').children();
	//    children of <g> elements with class ending in '_edge'
	var  edgeSet = sd.find('[class$=_edge]').children();
	//    canvases
	var canvasSet = sd.find("[id^=C-]");

	// if accession was passed, select subsets using semantic id
	if (typeof acc !== 'undefined') {
		clickableSet = sd.find('.' + acc + '_node').children();
		edgeSet = sd.find('.' + acc + '_edge').children();
		//var idStr = "C-" +  acc;
		canvasSet = sd.find("[id^=C-" + acc + "]");
		if (v > 8) console.log(" acc is defined! " + acc +
				"\ncanvasSet: " + canvasSet);
	} else {
		if (v > 8) console.log(" no accession passed to getClickableSet()");
	}
	if (v > 8) console.log(" detected " +
					canvasSet.length + " canvases for structure " + acc);
	$.merge(clickableSet, edgeSet);
	$.merge(clickableSet, canvasSet);
	if (v > 8) console.log("clickableSet.length: " +
			clickableSet.length);
	return(clickableSet);
} // end of getClickableSet()

function resetAllBackground(backgroundColor) {
	// globally select elements in ifr with class ending with "_mask" 
	var sd = $('#' + sDiv);

	nodeSet = sd.find("[class$=_mask]").children();
	nodeSet.css('stroke', backgroundColor);
	nodeSet.css('fill', backgroundColor);
} // end of function resetAllBackground()


function keySet(e) {
	// implements response to keystrokes
	var c = e.keyCode;
	var lc = String.fromCharCode(c).toLowerCase();
	if (/[0-9]/.test(lc) == true) { 
		v = 1 * lc;
		console.log("verbosity changed to " + v);
	}
}

function showPathway() {
	var pathEnd = acc[0];
	var url = "vertical-path.html?" + pathEnd;
	window.open(url,'_blank');
}


function populateInput(p) {
	if (v > 3) console.log("## Adding " + p + " to accession array ##");
	acc.push(p);	
}

	
function gNodeLog(gNode) {
	var gKids = gNode.children;
	for (var j = 0; j < gKids.length; j++) {
		var nn = gKids[j].nodeName;
		txt = "Entered <" + nn + ">:";	
		
		switch(nn) {
			case "rect":
				txt += " x " + $(gKids[j]).attr("x") + "; y " + 
				$(gKids[j]).attr("y") + "; height " + 
				$(gKids[j]).attr("height") + "; width " + 
				$(gKids[j]).attr("width");						
				break;
			case "circle":
				txt += " cx " + $(gKids[j]).attr("cx") + "; cy " +
				$(gKids[j]).attr("cy") + "; r " + 
				$(gKids[j]).attr("r");
				break;
			case "polygon":
				txt += " points " + $(gKids[j]).attr("points");
				break;
			case "text":
				txt += " text " + $(gKids[j]).text();
				break;
		}
	}
	console.log(txt)
}  // end of function gNodeLog()

	
function enterNode() {
	if (this.style) this.style.cursor = "pointer";
	var id = $(this).attr("id");
	if (id.match("^svg")) {
		if (v > 8) console.log("entered svg");
	} else {
		var parts = parseID(id);
		var t = ', ' + nodeType[parts['type']] + ' ';
		var rid = parts['resID'];
		var txt = "<br>&nbsp; Click now to explore glycan ";
		if (rid == 0) { // mouse over svg canvas
			rid = '';
			t = '';
		}
		txt +=  parts['accession'] + t + rid;
		$('#'+hDiv).html(txt); 
		if (v > 6) gNodeLog(this);
	}
} // end of function enterNode() 
	
	
function exitNode() { 
	$('#'+hDiv).html("<br>Click a structure or residue");
	var id = $(this).attr("id");
	if (id.match("^svg")) {
		if (v > 8) console.log("exited svg");
	} else {
		var parts = parseID(id);
		if (v > 5) console.log("exited node, overNode is " + overNode);
	}
} // end of function exitNode()
	
function dblclickNode() {
	// open a new sandbox explorer in the same window
	var id = $(this).attr("id");
	var parts = parseID(id);
	var a = parts["accession"];
	if ( (parts["type"] === "C") && (a !== acc[0]) ) {
		// double-clicked node must be a canvas, but not the canvas of the reference glycan
		var url = "explore.html?" + a;
		window.open(url,'_self',false);
	}
}


function clickNode() {
	// this function does not take any arguments
	//   'this' is the source of an event
	clickResponse(this);
}

function clickResponse(node) {
	// this function takes a node as its argument - it has no 'this' reference
	//   so it can be called without a click event
	var id = $(node).attr("id");
	var parts = parseID(id);
	var type = parts["type"];
	var localAcc = parts["accession"];
	var rd = data[localAcc].residues;
	var rv = data[localAcc].rule_violations;
        var ingg = (data[localAcc].structure.inglygen == 1);
        var treetypen = 0;
        var treetypeo = 0;
        var nodecnt = 0;
	for (var r in rd) {
          // console.log("blah, blah, blah:",r);
          if (/^#N/.test(r)) {
            treetypen += 1;
          } 
          if (/^#O/.test(r)) {
            treetypeo += 1;
          } 
          if (/^#/.test(r)) {
            nodecnt += 1;
          }
        }
        var treetype = '-'
        if (nodecnt == treetypen) {
            treetype = 'N';
        } else if (nodecnt == treetypeo) {
            treetype = 'O';
        }
        // console.log("blah, blah, blah:",treetype,nodecnt,treetypen,treetypeo);
	if (v > 5) console.log("clicked " + type + " in image of " + localAcc +
			" (" + id + ") " + treetype + ".");
	//$("#caveatDiv").css("visibility", "hidden");
	highlight(node);
	var resID = parts["resID"];
	var txt = getInfoText(localAcc, resID, treetype, ingg);
	if (v > 5) console.log("##### Info Box content #####\n" + txt + "\n#####");
	$("#"+iDiv).html(txt);
	
	var caveats = data[localAcc].caveats;
	if (caveats.length > 0) {
		showCaveats(localAcc);
		minimizeCaveats(localAcc);
	}
	if ((resID != '0') && (resID.match(/S/) == null) ) { 
		// the canvas itself was not clicked
		//  and the residue is mappable to a glycoTree object
		// ed is enzyme data for residues[resID]
		var ed = rd['#' + resID].enzymes;
                ed = filterEnzymesByRuleViolations(rd['#'+resID],ed,rv);
		setupEnzymeTable('enzymeTable', ed);
	} else {
		// the canvas itself was clicked
		setupTabs();
		// set up residue table and related glycan table
		//var rg = data[accession]["related_glycans"];
		setupResidueTable("residueTable", rd);
		// add event listener for the select element
		$('#glycanSelect').val(glycanSelector);
		$('#glycanSelect').change(function() {
			glycanSelector = $(this).val();
			// redraw and enable the panel containing glycan structures
			processFiles();
			var xxx = $("#"+iDiv).find(".gtd");
			xxx.click()
		});
		// set up ALL enzymes table
		var allEnzymes = getAllEnzymes(rd,rv);
		if (v > 4) console.log("  total number of enzymes is " + allEnzymes.length);
		setupEnzymeTable('enzymeTable', allEnzymes);
		setupRelatedGlycanTable("relatedTable", selectedData);
	}
} // end of function clickNode()

function filterEnzymesByRuleViolations(residue,enzymes,rule_violations) {
    // console.log(residue);
    // console.log(enzymes);
    // console.log(rule_violations);
    var resid = residue.residue_id;
    var newenzymes = [];
    for (enz of enzymes) {
         var good = true;
         // for (rv of rule_violations) {
         // console.log(rv.id,rv.focus,resid,rv.enzyme,enz.uniprot);
         //  if (((rv.rule_id == 1) || (rv.rule_id == 2)) && (rv.focus == resid) && (rv.enzyme == enz.uniprot)) {
         //       good = false;
         //       break;
         //   }
         // }
         if (enz["rule_violation"] == null) {
             newenzymes.push(enz);
         }
    }
    // console.log(newenzymes);
    return newenzymes;
}


function getAllEnzymes(residueArray,rule_violations) {
	// generate an array of enzyme objects associated with residueArray
	var allEnzymes = [];
	for (key in residueArray) {
		if (!key.includes("#")) {
			var ed = residueArray[key].enzymes;
                        ed = filterEnzymesByRuleViolations(residueArray[key],ed,rule_violations)
			for (key2 in ed) {
				var ok2add = true;
				for (i in allEnzymes) {
					if (ed[key2].gene_name === allEnzymes[i].gene_name) {
						ok2add = false;
					}
				}
				if (ok2add) allEnzymes.push(ed[key2]);				
			}
		}
	}
	return(allEnzymes);
} // end of function getAllEnzymes()


function setupResidueTable(tableName, tableData) {
	var table = $('#'+tableName).DataTable( {

		data: tableData,
		paging: false,
		"columnDefs": [
			{"className": "dt-center", "targets": "_all"}
		],
		columns: [
			{ 
				"title": "<a href='https://www.ncbi.nlm.nih.gov/glycans/snfg.html' target='_blank'>SNFG Symbol</a>",
				"data": "name",
				render: function(data, type, row, meta) {
					var svgName = data.split("-")[0];
                                        if (!conf.svg2pubchem) {
                                            var svg2pubchem = {};
                                            for (sug of conf.sugars) {
                                                if (sug.pubchem) {
                                                    svg2pubchem[sug.name] = sug.pubchem;
                                                } else {
                                                    svg2pubchem[sug.name] = sug.name;
                                                }
                                            }
                                            conf.svg2pubchem = svg2pubchem;
                                        }
                                        var pubchem = conf.svg2pubchem[svgName];
                                        if (!pubchem) {
                                            pubchem = svgName;
                                        }
					return "<a href='https://pubchem.ncbi.nlm.nih.gov/compound/" + pubchem +
						"' target='pubchem'><img src='snfg_images/" + svgName + ".svg'></a>"
				}
			},
			{ 
				"title": "Monosaccharide",
				"data": "html_name"
			},
			{ 
				"title": "Residue ID",
				"data": "residue_id",
				render: function(data, type, row, meta) {
					return "<a href=\"javascript:highlightResidue('" + data + "','" + acc[0] + "');\">" + data + "</a>"
				}
			},
			{ 
				"title": "Linked to Residue",
				"data": "parent_id",
				render: function(data, type, row, meta) {
					if (data === "0") {
						return data;
					} else {
						return "<a href=\"javascript:highlightResidue('" + data + "','" + acc[0] + "');\">" + data + "</a>"
					}
				}
			},
			{ 
				"title": "Linkage Site",
				"data": "site"
			},
			{ 
				"title": "Notes",
				"data": "notes",
				"defaultContent": "---"
			}
		]
	} );
	
} // end of function setupResidueTable()


function setupRelatedGlycanTable(tableName, tableData) {
	var nRes = data[acc[0]].residues.length;

	var table = $('#'+tableName).DataTable( {

		data: tableData,
		order: [[ 3, "asc" ]],
		paging: false,
		"columnDefs": [
			{"className": "dt-center", "targets": "_all"}
		],
		columns: [
			{ 
				"title": "Related Glycan",
				"data": "homolog",
				"render": function(data, type, row, meta){
					return data;
				}
			},
			
			{ 
				"title": "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Links&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;",
				"data": "homolog",
				"render": function(data, type, row, meta){
					if(type === 'display'){
						data = "<div class='ttip'><a href='explore.html?" +
							data + "' target='_blank'>" +    
							"<img src='svg/sandLogo.svg' height=35 width=35></a>" +
							"<span class='ttiptext'>Explore " + data + 
							"<br>in New Sandbox</span></div>" +
							"<div class='ttip'><a href='" + URLs["glygen_glycan"] +
							data + "' target='glygen'>" +
							"<img src='svg/glygenLogo.svg' height=35 width=35></a>" +
							"<span class='ttiptext'>Explore " + data + 
							"<br>in GlyGen</span></div>&nbsp;&nbsp;" +
							"<div class='ttip'><a href='" + URLs["gnome"] +
							data + "' target='gnome'>" +
							"<img src='svg/subsumLogo.svg' height=35 width=35></a>" +
							"<span class='ttiptext'>Explore " + data + 
							"<br>in Structure Browser</span></div>";
					}
					return data;
				}
			},	
			
			{ 
				"title": "DP",
				"data": "relative_dp",
				"render": function(data, type, row, meta){
					if(type === 'display'){
						data = nRes + (1.0 * data);
					}
					return data;
				}
			},
			
			{ 
				"title": "Match",
				"data": "shared"
			},
			
			{ 
				"title": "Substituents",
				"data": "sub_count"
			},
			
			{ 
				"title": "Reducing End",
				"data": "reducing_end"			
			}
		]
	} );

} // end of function setupRelatedGlycanTable


function setupEnzymeTable(tableName, tableData) {
	var table = $('#'+tableName).DataTable( {
		data: tableData,
		paging: false,
                "order": [[ 3, 'asc' ], [ 0, 'asc' ]],
		"columnDefs": [
			{"className": "dt-center", "targets": "_all"}
		],
		columns: [
			{ 
				"title": "Gene",
				"data": "gene_name",
				"render": function(data, type, row, meta){
					if(type === 'display'){
						data = '<a href="' + URLs["gene"] + data + 
							'" target="genecards">' + data + '</a>';
					}
					return data;
				}
			},
			{ 
				"title": "GlyGen",
				"data": "uniprot",
				"render": function(data, type, row, meta){
					if(type === 'display'){
						data = '<a href="' + URLs["glygen_protein"] + data + 
							'" target="glygen">' + data + '</a>';
					}
					return data;
				}
			},
			{ 
				"title": "UniProt",
				"data": "uniprot",
				"render": function(data, type, row, meta){
					if(type === 'display'){
						data = '<a href="' + URLs["uniprot"] + data + 
							'" target="uniprot">' + data + '</a>';
					}
					return data;
				}
			},
			{ 
				"title": "Species",
				"data": "species",
				"render": function(data, type, row, meta){
					if(type === 'display'){
						data = '<a href="' + URLs["taxonomy"] + data + 
							'" target="species">' + data + '</a>';
					}
					return data;
				}
			},
			{ 
				"title": "Type",
				"data": "type"
			},
			{ 
				"title": "Gene ID",
				"data": "gene_id",
				"render": function(data, type, row, meta){
					if(type === 'display'){
						data = '<a href="' + URLs["gene_id"] + data + 
							'" target="dna_refseq">' + data + '</a>';
					}
					return data;
				}
			},
		]
	} );

	// Toggle column visibility
	$('a.toggle-vis').on( 'click', function (e) {
		e.preventDefault();
		var column = table.column( $(this).attr('data-column') );
		column.visible( ! column.visible() );
	} );	
}

function customStrings(accession, resID, treetype, inglygen) {
	// customize mStr values for this glycan and residue
	mStr["infoHead"] = templates["infoHead"];
        if (inglygen == true) {
	    mStr["infoHead"] = mStr["infoHead"].replace("@GLYGENLINK", templates["glygenLink"]);
	    mStr["infoHead"] = mStr["infoHead"].replace("@GLYGEN", URLs["glygen_glycan"]);
        } else {
	    mStr["infoHead"] = mStr["infoHead"].replace("@GLYGENLINK", "");
        }
	mStr["infoHead"] = mStr["infoHead"].replace(/@ACCESSION/g, accession);	
        if (treetype == "N") {
	  mStr["gnomeLink"] = templates["gnomeLink"].replace("@GNOME", URLs["gnome_glycotree_nlinked"]);
        } else if (treetype == "O") {
	  mStr["gnomeLink"] = templates["gnomeLink"].replace("@GNOME", URLs["gnome_glycotree_olinked"]);
        } else {
	  mStr["gnomeLink"] = templates["gnomeLink"].replace("@GNOME", URLs["gnome"]);
        }
	mStr["gnomeLink"] = mStr["gnomeLink"].replace(/@ACCESSION/g, accession);
	mStr["sandLink"] = templates["sandLink"].replace(/@ACCESSION/g, accession);
	mStr["enzHead"] = templates["enzHead"].replace(/@ACCESSION/g, accession);
	mStr["enzHead"] = mStr["enzHead"].replace(/@RESID/g, resID);
	mStr["enzAll"] = templates["enzAll"].replace(/@ACCESSION/g, accession);
	
} // end of function customStrings()


function setupTabs() {
	$('#tabs li a:not(:first)').addClass('inactive');
	$('.tableHolder').hide();
	$('.tableHolder:first').show();
	$('#tabs li a').click(function(){
		var t = $(this).attr('href');
		$('#tabs li a').addClass('inactive');        
		$(this).removeClass('inactive');
		$('.tableHolder').hide();
		$(t).fadeIn('slow');
		return false;
	})

	if($(this).hasClass('inactive')){ 
		$('#tabs li a').addClass('inactive');         
		$(this).removeClass('inactive');
		$('.tableHolder').hide();
		$(t).fadeIn('slow');    
	}
} // end of function setupTabs()


function countResidues(residueData) {
	var counts = {resCount:0, subCount:0};
	for (var i = 0; i < residueData.length; i++) 
		if (residueData[i].anomer === "n") {
			counts.subCount++;
		} else {
			counts.resCount++;
		}
	return(counts);
} // end of function countResidues()


// this is not a general download function - specific for glycotree glycans
function downloadGlycan(acc, selector) {
	var filename = "";
	var dType = selector.value;
	var content = "";
	
	if (dType == "svg") {
		content = encodeURIComponent(svgEncoding[acc]);
		filename = acc + ".svg";
	}
	if (dType == "json") {
		content = encodeURIComponent(getData(acc));
		filename = acc + ".json";
	}
	
	var da = document.createElement('a');
	// for svg, error occurs if there is a space between the comma and the content
	da.setAttribute('href','data:text/' + dType + ',' + content);
	da.setAttribute('download', filename);
	document.body.appendChild(da);
	da.click();
	selector.value = "none";
	document.body.removeChild(da);
} // end of function downloadGlycan()


function minimizeCaveats(acc) {
	var cavPanel = $("#caveatDiv");
	var caveatTxt = "<center><img src='./svg/warn.svg' style='vertical-align: -5px' width='25' height='25'> " +
		"&nbsp; <b>Caveats for Glycan " + acc +
		"</b>&nbsp; <img src='./svg/warn.svg' style='vertical-align: -5px' width='25' height='25'> " +
		"&nbsp; <a href=\"javascript:showCaveats('" + acc + "');\">more ...</a></b></center>";
	cavPanel.html(caveatTxt);
	cavPanel.css("visibility","visible");
		// setup the caveatDiv
	repositionInfo(acc);
}

function showCaveats(acc) {
	var caveats = data[acc].caveats;
	var cavPanel = $("#caveatDiv");
	if (caveats.length == 0) {
		cavPanel.css("visibility","hidden");
		return;
	}
	var caveatTxt = "<center><h2>Caveats for Glycan " + acc + "</h2></center><ul>";
	for (var i in caveats) {
		var msg = caveats[i]['msg'];
		
		// enclose each residue_id in an anchor referring to method 'highlightResidue()'
		var resStr = msg.match(/[NO][0-9]+/g);
		var uniqueResStr = [...new Set(resStr)];
		$.each(uniqueResStr, function(index, value) { 
			var replacement = "<a href=\"javascript:highlightResidue('" + value + "','" + acc + "');\">" + value + "</a>";
			var regex = new RegExp(value, "g");
			msg = msg.replace(regex, replacement);
		});
		
		// put individual rule violations in a list
		var htmlMsg = msg.replace(":", "<ul><li>");
		htmlMsg = htmlMsg.replace(/\#/g, "</li><li>") + "</li></ul>";
		if (v > 2) console.log("Caveat message:\n" + htmlMsg);
		caveatTxt += "<li>" + htmlMsg + "</li>";
	}
	caveatTxt += "</ul> &nbsp; <a href=\"javascript:minimizeCaveats('" + acc + "');\">less ...</a>";
	cavPanel.html(caveatTxt);
	cavPanel.css("visibility","visible");
	repositionInfo(acc);
}


function findNodeByResidueID(rid, ac) {
	// finds node in the glycan where accession=ac and residue_id=rid
	if (v > 3) console.log("finding residue " + rid + " in glycan " + acc[0]);
	var sd = $('#' + sDiv);
	var regex = new RegExp(":" + rid + "$"); // regex must begin with ":"
	var foundNode = sd.find('[class$=_node]').children().filter(function () {
		return this.id.indexOf(ac) > -1;
	}).filter(function () {
		return this.id.search(regex) > -1;
	});
	if (v > 3) console.log("found residue " + foundNode[foundNode.length-1].id);
	return foundNode;
} // end of method findNodeByResidueID()

function showGlycanWithResidue(res1, missing) {
	// retrieves and displays an example of a fully mapped glycan containing res1
	//alert("res1 is " + res1);
	var theURL = "api/list.php?mode=mapped_example&par=" + res1;
	//var glycanList = null;
	$.get(theURL, function(result) {
		if (v > 3) {
			console.log("result" + result);
		}
		if (theURL.includes(".json")) {
			// jquery automatically parses files with extension '.json'
			glycanList = result
		} else {
			// JSON.parse is not automatically invoked
			glycanList = JSON.parse(result);
		}
		var glycanWithResidue = glycanList[0]["glytoucan_ac"];
		//alert("found glycan: " + glycanWithResidue);
		var missingMsg = "";
		if (missing) {
			missingMsg = "Glycan " + acc[0] + " does NOT contain residue " + res1;
		}
		if (confirm(missingMsg +
			"\nClick OK to open a new tab showing residue " + res1 +
				" in " + glycanWithResidue +
				", the smallest fully-mapped glycan in the Sandbox DB that contains " + res1)) {
					// executed upon clicking 'OK'
    				window.open('explore.html?focus=' + glycanWithResidue +
									'&residue=' + res1,'_blank');		
			}
	})
	.fail(function() {
		alert("File " + theURL + " failed");
	});
}

function highlightResidue(resID, ac) {
	// highlights the residue within ac whose id ends with resName

	var nodeToHighlight = findNodeByResidueID(resID, ac);
	if (nodeToHighlight.length > 0) {
		highlight(nodeToHighlight);
	} else {
		// nodeToHighlight is NOT in the glycan where accession = ac
		showGlycanWithResidue(resID, true);
	}
}

function repositionInfo(acc) {
	var caveats = data[acc].caveats;
	var cavPanel = $("#caveatDiv");
	var tabTop = cavPanel.height() + 122;
	$("#tabbox").css("top", tabTop + "px");
	var contentTop = tabTop + 60;
	$("#contentbox").css("top", contentTop + "px");
	cavPanel.css('left', $("#infoDiv").css("left"));
}


function getInfoText(accession, resID, treetype, inglygen) {
	customStrings(accession, resID, treetype, inglygen);
	var txt = "<p class='head1'>" + mStr["infoHead"]; 
	if (resID == '0') {
		// the background canvas was clicked
		var counts = countResidues(data[accession].residues);
		// var thisSubCount = countElements(getSubstituents(accession));
		txt += " - " + counts.resCount + " residues, " +
			counts.subCount + " substituent(s) &nbsp; &nbsp; &nbsp; &nbsp; \n";
		
		txt += "<svg xmlns:xlink='http://www.w3.org/1999/xlink' \n\
xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' class='downloader'> \n\
  <path d='M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z' fill='rgb(67,118,278)'> \n\
  </path> \n\
</svg> \n"
		
		txt += "<select name='selDown' id='selDown' onchange=\"downloadGlycan('" +
			accession + "', this);\" class='downloader'>\
  <option value='none' selected disabled hidden>DOWNLOAD</option> \n\
  <option value='svg'>SVG Image</option> \n\
  <option value='json'>Glycan Data</option> \n\
</select> \n";

		txt += "<p class='head1'>" + mStr["gnomeLink"] + "</p>\n";
		
		var cavTop = 92;
		var tabTop = cavTop;
		var contentTop = tabTop + 60;
		
		txt += "<div id='caveatDiv' style='top: " + cavTop + "px;'> ... </div>";
		
		// START OF TABS DIV
		txt += "<div id='tabbox' style='top: " + tabTop + "px;'> \n\
<ul id='tabs'> \n\
	<li><a href='#residue_table_div'>Residues</a></li> \n\
	<li><a href='#enzyme_table_div'>Enzymes</a></li> \n\
	<li><a href='#glycan_table_div' class='gtd'>Related Glycans</a></li> \n\
	</ul> \n\
</div> \n";
		// END OF TABS DIV
		
		// START OF CONTENT BOX
		txt += "<div id='contentbox' style='top: " + contentTop +"px'> \n";
	
		// START OF RESIDUE TABLE SECTION
		txt += "<div class='tableHolder' id='residue_table_div'> \n\
  <b>Residues in " + accession + "</b> \n\
  <table id='residueTable' class='display' width='100%'></table> \n\
</div> \n";
		// END OF RESIDUE TABLE SECTION
		
		
		// START OF ENZYME TABLE SECTION
		txt += "<div class='tableHolder' id='enzyme_table_div'> \n\
	<b>" + mStr["enzAll"] + 
	"<a href=\"javascript: document.getElementById('table_end').scrollIntoView();\" class='no_und'> \
	<sup>&#135;</sup></a></b></p> \n\
	<table id='enzymeTable' class='display' width='100%'></table> \n\
	<p id='table_end'> \n\
		<sup>&#135;</sup>" + dStr["tableEnd"] +
	"</p> \n\
</div> \n";
		// END OF ENZYME TABLE SECTION
		
		
		// START OF GLYCAN TABLE SECTION
		txt += "<div class='tableHolder' id='glycan_table_div'> \n"
		// rg <- glycans related to THIS ACCESSION
		var rg = data[accession]["related_glycans"];
		if  ((typeof rg != "undefined") && (rg.length > 0) ) {
			if (accession == acc[0] ) {
				// create related glycan table object
				txt += "<p><b>" + mStr["listHead"] + "</b> \n";
				// GLYCAN TABLE DROPDOWN (SELECT)
				
				txt += " &emsp; <select class='selectScope' id='glycanSelect'> \n";
				for (key in selectStrings) {
					 txt += "  <option value='" + key + "'>" + selectStrings[key] + "</option> \n";
				}
				txt += "</select> \n" ;
				
				
				txt += "<table id='relatedTable' class='display' width='100%'></table> \n";
			} else {
				txt += "<p><b>" + mStr["sandLink"] + "</b></p> \n"; 
			}
		} else {
			txt += "<hr><p><b>No data for glycans biosynthetically related to " + accession + 
				" are available</b><br>Reason: " + accession + 
				" <i>cannot be fully mapped to GlycoTree</i></p> \n";
		}
		txt += "</div> \n"
		// END OF GLYCAN TABLE SECTION
		

		
		// END OF CONTENT BOX
		txt += "</div>\ \n";

	} else {
		var rd = data[accession].residues['#' + resID];
		// rd is 'residue data'
		if (resID.match(/S/) == null) {
			if (rd.canonical_name == "unassigned") txt += " (unassigned)";
			txt += " residue " + resID + "</p> \n";
			if (typeof rd.limited_to != "undefined")
				if (rd.limited_to.length > 0) 
					txt += "This residue has been found <b>only</b> in " + rd.limited_to + "<br>";
			if (typeof rd.not_found_in != "undefined")
				if (rd.not_found_in.length > 0)
					txt += "This residue has <b>not</b> been found in " + rd.not_found_in + "<br>";
			if (typeof rd.notes != "undefined")
				if (rd.notes.length > 0)
					txt += "Status: " + rd.notes + "<br>";
			if (typeof rd.requires_residue != "undefined")
				if (rd.requires_residue.length > 0)
					txt += "Enzymatic transfer of this canonical residue occurs only when residue " + rd.requires_residue + "  is present<br>";
			if (typeof rd.blocked_by_residue != "undefined")
				if (rd.blocked_by_residue.length > 0)
					txt += "Enzymatic transfer of this canonical residue is blocked by residue " + rd.blocked_by_residue + "<br>";
	
			var svgName = rd.name.split("-")[0];
			
			txt += "<br> <a href='https://pubchem.ncbi.nlm.nih.gov/compound/" + svgName +
"' target='pubchem'><img src='snfg_images/" + svgName + ".svg'></a> \n\
&emsp;<b>" + rd.html_name + "</b> \n\
linked to residue " + rd.parent_id + " at site " + rd.site +
" (<a href='https://www.glygen.org/glycan/" + accession + 
"#Digital-Sequence' target='glycoct'>GlycoCT</a> index: " + rd.glycoct_index + ") \n\
<hr><p><b>" + mStr["enzHead"] + "</b> \n\
<table id='enzymeTable' class='display' width='100%'></table> \n\
<br> <b>Show/Hide Column: </b> \n\
  <a class='toggle-vis' data-column='0'>Gene</a> | \n\
  <a class='toggle-vis' data-column='1'>GlyGen</a> | \n\
  <a class='toggle-vis' data-column='2'>UniProt</a> | \n\
  <a class='toggle-vis' data-column='3'>Species</a> | \n\
  <a class='toggle-vis' data-column='4'>Type</a> | \n\
  <a class='toggle-vis' data-column='5'>Gene ID</a>";
		} else {
			txt += "</p><p>&#128683; The residue you clicked cannot be mapped to a glycoTree object &#128683;</p>"
		}
	}

	return(txt);
} // end of  function getInfoText()


function addGlycan(accession) {
	if (!acc.includes(accession)) {
		// do not do any of the following unless the glycan is absent from array 'acc'
		 // $("#progressDiv").css("visibility","visible");
		populateInput(accession);
		if (v > 2) console.log("  accession array now has " +
									  acc.length + " structures");
	}
} // end of function addGlycan()

function getRelatedAccessions() {
	// add data for all related glycans
	if (v > 1)
		console.log("### Adding Data for All Related Glycans ### ");
	var probe = acc[0];

	var rg = data[probe]["related_glycans"];
	if ( (dataAvailable == true) && (typeof rg != "undefined") ) {
		if (v > 2) console.log("  adding " + rg.length + " glycans related to " + probe);
		var rgCopy = rg.slice();
		// sort rgCopy by relative_dp -> same order as sorted table

		rgCopy.sort(function(a, b) {
			var nameA = a.relative_dp;
			var nameB = b.relative_dp;
			if (nameA < nameB) {
				return 1;
			}
			if (nameA > nameB) {
				return -1;
			}
			// equal relative_dp
			return 0;
		});

		for (var i = 0; i < rgCopy.length; i++) {
			var newAccession = rgCopy[i].homolog;
			if (v > 2) console.log("*** next accession: " +
										  newAccession + " ***");
	// false -> wait until all input variables are populated before fetching data
			addGlycan(newAccession);
		}
		allDataRequested = true;
	}

} // end of function getRelatedAccessions()


function htmlFormatName(residueData) {
			var sName = residueData.name;
			// place ring form before "NAc" or "NGc" or "-", whichever comes first
			var nacLoc = sName.indexOf('NAc');
			var ngcLoc = sName.indexOf('NGc');
			var dashLoc = sName.indexOf('-');
			var nLoc = Math.max(nacLoc, ngcLoc);
			if (dashLoc > -1) { // there is a '-' in the name
				if (nLoc == -1) {  // there is no Nx in the name
					nLoc = dashLoc;
				}
			}
	
			var rf =  residueData.ring;
			if ((rf !== "ol") && (rf !== "n")) {
				rf =  "<i>" + residueData.ring + "</i>";				
			}
			// substituents have no ring form (rf is 'n')
			if (rf === "n") {
				rf =  "";				
			}
			var wholeName = sName + rf;
			if (nLoc > -1) {
				wholeName = sName.substring(0, nLoc) + rf + sName.substring(nLoc);
			} else {		
			}
			var anom = greek[residueData.anomer]; // lookup greek letter
			var aSep = "-";
			if ( (anom === "acyclic") ||  (anom === "") ){
				aSep = "";
				anom = "";
			}

			var abs = residueData.absolute + "-" ;
			if (residueData.absolute === "n") {
				abs = "";
			}
			var cName = anom + aSep + abs + wholeName;
			return(cName);
} // end or function htmlFormatName()

	
	

function parseID(id) {
	if (v > 5) console.log("   parsing " + id);
	var parts = new Array();
	var dashSplit = id.split("-");
	parts["type"] = dashSplit[0];
	var colonSplit = dashSplit[1].split(":");
	parts["accession"] = colonSplit[0];
	parts["resID"] = colonSplit[1];
	return(parts);
}  // end of function parseID()
	
	
function setupFrames() {
	// resize iframe and information div's
	if (v > 2) console.log("##### Resizing Frames #####");
	var canvasH = 155;
	var canvasW = 25;
	var sd = $("#" + sDiv);
	// the contents of svgDiv are dynamically generated
	//   thus, its class must be added explicitly
	$(sd).addClass("svgDiv");
	
	var s = sd.find('svg');
	
	for (var i = 0; i < s.length; i++) {
		// put s[i] in parentheses after $ to make it a jquery object
		var w = 1.0 * $(s[i]).attr('width');
		var h = 1.0 * $(s[i]).attr('height');
		var accession = $(s[i]).attr('id').split('-')[1];
		if (v > 4) console.log("  svg[" + i + "] height: " + h + "; width: " + w );
		canvasH +=  40 + h;
		canvasW = Math.max(canvasW, w + 120);
		// annotate canvas with accession by appending text element
		// this annotation has no ID - it cannot be clicked and it is not toggled
		var textElement = generateTextElement((w/2)-40, 0.96*h, 'svgText', "", accession);
		s[i].appendChild(textElement);
	}
	
	var newLeft = canvasW + 45;

	// setup  svgDiv
	sd.css("width", canvasW + "px");
	sd.css("height", canvasH + "px");
	
	// setup the helpDiv
	$('#' + hDiv).css("width", canvasW + "px");
	
	// setup the control div (div2)
	$('#div2').css('left', newLeft + 'px');
	
	// setup the infoDiv
	$('#' + iDiv).css('left', newLeft + 'px');
	
	// setup the caveatDiv
	//$('#' + cDiv).css('left', newLeft + 'px');
	
	if (v > 4) {
		console.log("  canvas -> height: " + canvasH + "; width: " +
						canvasW );
		console.log("  div2 -> left: " + newLeft + "; height: " +
						canvasH );
	}
	
	annotateResidues();
	annotated = true;
	toggleResidueAnnotations();
} // end of function setupFrames()


function toggleResidueAnnotations() {
	var sd = $('#' + sDiv);

	var s = sd.find("svg"); // all <svg> elements in svgDiv
	var t = s.find('text[id]');  // <text> elements with 'id' attribute

	if (!annotated) {
		if (v > 4) console.log("Showing annotations");
		t.removeClass(["annotationHidden"]);
		t.addClass(["annotationShown"]);
	} else {
		if (v > 4) console.log("Hiding annotations");
		t.removeClass(["annotationShown"]);
		t.addClass(["annotationHidden"]);
	}
	annotated = !annotated;
}	


function annotateResidues() {
	var sd = $('#' + sDiv);
	var s = sd.find("svg"); // all <svg> elements in iframe body

	for (var i = 0 ; i < s.length; i++) { // for each svg image
		var localAcc = s[i].id.split("_")[1];
		if (v > 3) console.log("##### Annotating glycan " +
									  localAcc + " #####");

		// svg image to jquery object
		var ss = $(s[i]); 
		// <g> elements with 'id' attribute
		var g = ss.find('g[id]');  

		g.each(function( index2 ) {
			// each descendant <g> element having 'id'

			var parts = parseID(this.id);
			var type =  parts['type'];
			var resID = parts['resID'];
			if (type == "R") { // annotate residues only
				var x = 0;
				var y = 0;
				// first child of the <g> element having 'id'
				var d = this.children[0];
				if (v > 4) console.log("    annotating node " +
							index2 + ": type " + type + "; id " + resID);
				var nn = d.nodeName;
				switch(nn) {
					case "circle":
						x = 1 * $(d).attr('cx');
						y = 1 * $(d).attr('cy');
						if (v > 4) console.log("         <circle> at " +
													  x + "," + y);
						break;
					case "rect":
						var h = 1 * $(d).attr('height')
						x = 1 * $(d).attr('x') + h/2;
						var w = 1 * $(d).attr('width')
						y = 1 * $(d).attr('y') + w/2;
						if (v > 4) console.log("         <rect> at " + x +
													  "," + y);
						break;
					case "polygon":
						var p = $(d).attr("points").trim();
						var pa = p.split(' ');
						var maxEven = 0;
						var minEven = 1000;
						var maxOdd = 0;
						var minOdd = 1000;
						for (var q = 0; q < pa.length; q++) {
							if (q%2 == 0) {
								minEven = Math.min(minEven, pa[q]);
								maxEven = Math.max(maxEven, pa[q]);
							} else {
								minOdd = Math.min(minOdd, pa[q]);
								maxOdd = Math.max(maxOdd, pa[q]);									
							}
						}
						y = (minOdd + maxOdd) / 2;
						x = (minEven + maxEven) / 2;
						if (v > 4) console.log("         <polygon> at " +
													  x + "," + y);
						break;
				}
				var elemID = "A-" + localAcc + ":" + resID;
				var element = generateTextElement(x, y, 'annotationShown', elemID, resID);
				s[i].appendChild(element);
			}

		});

	};

} // end of function annotateResidues()


function generateTextElement(x, y, c, id, t) {
	var te = document.createElementNS('http://www.w3.org/2000/svg', 'text');
	te. setAttributeNS(null, 'x', x);
	te. setAttributeNS(null, 'y', y);
	te. setAttributeNS(null, 'class', c);
	if (id != "") {
		te. setAttributeNS(null, 'id', id);
	}
	var txt = document.createTextNode(t);
	te. appendChild(txt);
	return(te);
} // end of function generateTextElement()
		

function addSVGevents() {
	// adds event listeners to svg objects and saves their original 'fill' and 'stroke' values
	if (v > 2) {
		console.log("##### Adding Event Listeners to SVG Elements #####");
	}

	var sd = $('#' + sDiv);
	// select all <g> elements in svgDiv with an id attribute
	var g = sd.find("g[id]");
	var elStr = " $$$ found " + g.length + " clickable SVG elements: $$$\n";
	for (var i = 0; i < g.length; i++) {
		// USE VANILLA JAVASCRIPT for attributes (.attr fails - why?)
		elStr += $(g[i]).attr("id") + "  ";
		// add event listeners for objects that have an id
		// using vanilla javascript here (not jquery)
		g[i].addEventListener("mouseout", exitNode);
		g[i].addEventListener("mouseover", enterNode);
		g[i].addEventListener("click", clickNode);
		g[i].addEventListener("dblclick", dblclickNode);
	}
	if (v > 6) console.log(elStr);

} // end of function addSVGevents()

function setResidueKeys() {
	// converts residues of each glycan from array[val0,val1,val2,...] to Object {'key': 'value'}
	if (v > 1) console.log("##### Assigning Keys to Residues #####");
	for (var key in data) {
		var glycan = "glycan[" + key + "]";
		if (v > 4) console.log("   Working on " + glycan);
		var residues = data[key].residues;
		if (v > 4) console.log("There are " + residues.length +
									  " residues in " + key)
		for (var j = 0; j < residues.length; j++ ) {
			if (v > 4) console.log("j is " + j);
			// FAILS WHEN resID == j - so need prefix "#"
			var key2 = "#" + residues[j].residue_id;
			residues[key2] = residues[j];
			var htmlName = htmlFormatName(residues[key2]);
			residues[key2].html_name = htmlName;
			if (v > 4) {
				console.log("   object key generated for " + glycan +
								".residues[" + j + "]  -> '" + key2 + "'");
				console.log("   data['" + key + "'].residues['" +
						key2 + "']: html name is '" + 
						residues[key2].html_name +  "'");
			}
		}
	}
} // end of function setResidueKeys()


function setRelatedParams(probe) {
	if (v > 2) console.log("## Setting reducing-end structure and substituents for related glycans ##");
	// get related_glycans for probe
	var related = data[probe].related_glycans;
	for (var i in related) {
		// for each related_glycan [i]
		// get the accession of the related glycan
		var key = related[i].homolog;
		related[i].reducing_end = getReducingEndStructure(key);
		related[i].substituents = getSubstituents(key);
		related[i].sub_count = countElements(related[i].substituents);
		if (v > 3) {
			console.log("  Set reducing end for " + key +
				" (" + data[key].residues.length + " residues): " + related[i].reducing_end);
			console.log("  Set number of substituents in " + key +
							": " + related[i].sub_count);
		}
	}
} // end of function setRelatedParams()

function getSubstituents(accession) {
	var residues = data[accession].residues;
	var substituents = [];
	for (j in residues) {
		// only check numerically indexed residues
		if (/[A-Z]/.test(j) == false) { 
			var thisSubstituent = {};
			var sugarName = residues[j].name;
			if (sugarName.includes("-")) {
				if (v > 4) console.log(i + "," + j +
					": found substituent " + sugarName +
					 " in " + accession );
				var sugarParts = sugarName.split("-");
				var id = residues[j].residue_id;
				thisSubstituent[id] = sugarParts[1];
				substituents.push(thisSubstituent);
			}
		}
	}
	return(substituents);
} // end of function getSubstituents()


function getReducingEndStructure(accession) {
	var residues = data[accession].residues;
	for (j in residues) {
		// only check numerically indexed residues
		if (residues[j].parent_id == 0) {
			return(residues[j].html_name);
		}
	}
} // end of function getReducingEndStructure()

function showMapped() { // !M
	// grey out residues that are NOT mapped to the glycoTree
	$('#'+hDiv).html("<br>&nbsp; Unmapped residues are 'greyed-out'");
	getClickableSet().each(function( index ) {
		// default opacity is 1.0
		$(this).css('opacity', 1.0);
		var id = $(this).attr("id");
		// the id of each svg object is complex,
		//    containing an accession, a resID, and a type
		var parts = parseID(id)
		var resID = parts['resID'];
		// the resID of each UNMAPPED residue does NOT begin with [A-Z]
		var n = resID.search(/^[A-Z]/i); 
		if (n == -1) {
			//  grey-out unmapped residues/links, but NOT canvas (type = "C")
			type = parts['type'];
			if (type != "C") $(this).css('opacity', 0.3);
		}	
	});
}

function getData(acc) {
	for (var key in data) {
		var glycan = data[key];
		// return json data for glycan with 'glytoucan accession' = acc
		if (key == acc) return(JSON.stringify(glycan, undefined, 2));
	}
	return("not found");	
}  // end of function getData()

		
	
function fetchConfiguration(theURL) {
	$.get(theURL, function(result){
		if (v > 3) {
			console.log("  fetched sugar configuration data from " +
							theURL + ":\n  " + result);
		}
		if (theURL.includes(".json")) {
			// jquery automatically parses files with extension '.json'
			conf = result;
		} else {
			// JSON.parse is not automatically invoked
			conf = JSON.parse(result);
		}
	});
	
} // end of function fetchConfiguration()


function fetchGlycanData(theURL, type, accession) {
	// dest is an array that stores parsed json data
	$.get(theURL + "/" + accession,null,
	function(result){
		if (v > 5) {
			console.log("  for " + accession + ", " + theURL + 
				" returned this data:\n  " + result.toString());
		}

		if (type === 'json') {
			// 'data' is a global Object containing glycan data
			// data[accession] = JSON.parse(result);
			data[accession] = result;
			if (v > 2) {
				console.log("Data for accession " + accession + ":\n" +  JSON.stringify(data[accession], 3, 3));
			}
			if (data[accession].residues === null) {
				noData(accession, theURL);
			} else {
				generateSVG(accession);
			}
		}
	})			
	.fail(function() {
		unavailable.push("JSON data for " + accession);
		console.log("File " + theURL + " not found");
		noData(accession, theURL);
	});
	
} // end of function fetchGlycanData()


function noData(a, u) {
	dataAvailable = false;
	$("#progressDiv").html("<h3>No data available for " + a + "</h3>");
	$("#helpDiv").html("");
	$("#infoDiv").html("");
	console.log("!!! " + u + " DID NOT RETURN MEANINGFUL DATA !!!");
} // end of function noData()


function simXOR(x, y) {
	// NO XOR IN JAVASCRIPT - SO SIMULATE IT
	return( x || y ) && !( x && y );
}


function getSelectedData(selector) {
	if (v > 3) console.log("glycan selector is " + selector);
	// returns an edited array of related_glycans objects for rendering and listing
	var probeData = data[acc[0]];
	var rg = probeData["related_glycans"];

	for (i in rg) {
		if (rg[i].homolog == acc[0]) {
			probeEnd = rg[i].reducing_end;
			probeSubCount = rg[i].sub_count;
		}
	}
	if (v > 3) {
		console.log("   probe is " + acc[0]);
		console.log("   probeEnd is " + probeEnd);
	}
	var rgEdited = [];
	switch(selector) {
		case "all":
			for (i in rg) {
				if (rg[i].homolog != acc[0]) {
					rgEdited.push(rg[i]);
				}
			}
			break;
		case "precursors":
			for (i in rg) {
				var endOK = (rg[i].reducing_end == probeEnd);
				var dpOK = ( (rg[i].sub_count == probeSubCount) && (rg[i].relative_dp < 0));
				var subOK = ( (rg[i].sub_count < probeSubCount) && (rg[i].relative_dp == 0) );
				if (endOK) if( simXOR(dpOK, subOK) ) {
					rgEdited.push(rg[i]);
				}
			}
			break;
		case "products":
			for (i in rg) {
				var endOK = (rg[i].reducing_end == probeEnd);
				var dpOK = ( (rg[i].sub_count == probeSubCount) && (rg[i].relative_dp > 0));
				var subOK = ( (rg[i].sub_count > probeSubCount)  && (rg[i].relative_dp == 0) );
				if (endOK) if( simXOR(dpOK, subOK) ) {
					rgEdited.push(rg[i]);
				}
			}
			break;
		case "anomers":
			for (i in rg) {
				if ((rg[i].relative_dp == 0) && 
					( (rg[i].reducing_end != probeEnd)) ) {
						rgEdited.push(rg[i]);
				}
			}
			break;
		case "match":
			for (i in rg) {
				if ((rg[i].homolog != acc[0]) && (rg[i].reducing_end == probeEnd) ) {
					rgEdited.push(rg[i]);
				}
			}
			break;
		case "specified":
			for (i in rg) {
				//if (rg[i].homolog != acc[0]) {
				if ((rg[i].homolog != acc[0]) && (rg[i].reducing_end.includes("?") == false) ) {
					rgEdited.push(rg[i]);
				}
			}
			break;
	}
	
	// sort rgEdited by relative_dp -> same order as sorted table
	rgEdited.sort(function(a, b) {
		var valA = a.relative_dp;
		var valB = b.relative_dp;
		if (valA < valB) {
			return 1;
		}
		if (valA > valB) {
			return -1;
		}
		// equal relative_dp
		return 0;
	});
	return(rgEdited);
} // end of function getSelectedData()


function displayGlycans() {
	var sd = $("#" + sDiv);
	// clear sDiv content
	sd.html("");
	
	// render the probe structure - write as text then add to sd
	var htmlEncoding = "&emsp; <br><center><h3>" + dStr["imgHead"] + "</h3></center>";
	htmlEncoding += "<p>" + svgEncoding[acc[0]] + "&emsp; <br></p><hr>";
	htmlEncoding += "<center><b>" + mStr["listHead"] + "<br>" +
		selectStrings[glycanSelector] + "</b></center>";

	if (acc.length > 1) {
		// selected data does NOT include the reference structure - only related structures
		selectedData = getSelectedData(glycanSelector);
		if (v > 3) {
			console.log("  selectedData is:\n" + selectedData);
		}
		var sep = "";
		if (v > 3) console.log("selectedData.length is " +
					selectedData.length);
		for (i = 0; i < selectedData.length; i++) {
			var key = selectedData[i].homolog;
			if (v > 3) {
				console.log("*** key is " + key + " ***");
			}
			htmlEncoding += sep + "<p>" + svgEncoding[key];
			sep = "&emsp; <br>";
		}
		htmlEncoding += "<br></p>"
	}

	sd.append(htmlEncoding);
	var s = sd.find("svg"); // all <svg> elements in svgDiv
	s.addClass("zoomer");
}



function processFiles() {
	if (v > 0) console.log("### Processing Data From Files ###");

		setResidueKeys();  // convert json 'residues' to associative array
		var related = data[acc[0]].related_glycans;
		pathStart = data[acc[0]].path_start;
		alternate = data[acc[0]].alternate;
		relatedDataExists = (typeof related != "undefined");
		if ( allDataRequested && relatedDataExists ) 
			setRelatedParams(acc[0]);
		if (v > 3) console.log("probe is " + acc[0]);
 
		displayGlycans();
	
		// set up graphics and data
		setupFrames();  // calculate required <element> sizes and locations
		addSVGevents();
		if (v > 2) console.log("##### Finished Setup #####");
		$("#progressDiv").css("visibility","hidden");
		if (allDataRequested == true)  {
			if (hRes.length > 0) {
				// simulate click of hRes node
				var hResNode = findNodeByResidueID(hRes, acc[0]);
				clickResponse(hResNode);
			} else {
				// simulate click of reference glycan canvas 
				var topCanvas = $('#' + sDiv).find("g")[1];
				clickResponse(topCanvas);			
			}
		}
}


function generateSVG(ac) {
	trees[ac] = plantTree(data[ac]);
	accession = ac;
	svgEncoding[ac] = layout(trees[ac]); // modifies trees object
	// alert(ac + "\n" + svgEncoding[ac])
} // end of function generateSVG()
	
function getFiles(i) {
	if (v > 1) console.log("##  getting glycan data file - index: " +
							i + "; accession: " + acc[i] + " ##");
	// fetchGlycanData arguments are: (URL, type, accession)
	fetchGlycanData(glycanPath, 'json', acc[i]);
	// svg strings are generated from glycan data, and are not fetched
} // end of function getFiles()


function countElements(object) {
	var count = 0;
	for(var key in object) {
		if(object.hasOwnProperty(key)) ++count;
	}
	return count;
}


function dataReady() {
	// returns true iff:
	//    the number of accessions in the 'data' object is > 0
	//    and equals the number of accesions in the 'acc' array
	var n1 = acc.length;
	var n3 = countElements(data);
	return((n1 > 0) && (n3 === n1));
} // end of function dataReady()

function wait2add() {
	if (dataReady() == false) {
		if (v > 3) console.log("  'reference glycan data loaded' is " +
				  dataReady() + " - waiting to add related accessions");
		if (dataAvailable) {
			window.setTimeout(wait2add, 200); 
		} else {
			terminate("Reference Glycan");
		}
    } else {
		if (v > 3) console.log("  'reference glycan data loaded' is " +
							  dataReady() + " - adding accessions");
		getRelatedAccessions();
		if (allDataRequested)
			for (i = 1; i < acc.length; i++) {
				getFiles(i);
			}
		if (acc.length > 1) {
			wait2process();
		} else {
			processFiles();
		}
	}
} // end of function wait2add()



function wait2process () {
	if (dataReady() == false) {
		if (v > 2) console.log("  'all glycan data loaded' is " +
				  dataReady() + " - waiting to process");
		if (dataAvailable) {
			window.setTimeout(wait2process, 200); 
		} else {
			terminate("Related Glycan");
		}
    } else {
		if (v > 4) console.log("... ready to process glycan data ");
		if (v > 8) {
			// check fetchConfiguration
			console.log("   SVG images of the following sugars are supported:");
			var sugars = conf.sugars;
			for (i in sugars) {
				console.log("   " + i + ": " + sugars[i].name);
			}
	        }
                var svg2pubchem = {};
		for (sug of conf.sugars) {
                    if (sug.pubchem) {
                        svg2pubchem[sug.name] = sug.pubchem;
                    } else {
                        svg2pubchem[sug.name] = sug.name;
                    }
		}
                conf.svg2pubchem = svg2pubchem;
                console.log(conf);
		processFiles();
	}
}


// plantTree() returns a JS data object containing only those elements 
//   required for encoding the SVG image of the glycan
//  treeData is a JS data object directly produced when JSON.parse()
// 	is applied to the input JSON data
function plantTree(treeData) {
	if (v > 5) console.log("@@@ planting tree\n" +
									  treeData['glytoucan_ac']);
	//  extract information and populate the object 'tree'
	// tree is the data object consumed by json2svg
	var tree = new Array();
	tree['accession'] = treeData.glytoucan_ac;
	tree['nodes'] = new Array();
	// treeData is original data from json file 
	//   for this example, the nodes are called 'residues' in the original
	var residues = treeData.residues; // residues is the local name of the array
	for (var i=0; i < residues.length; i++) {
		var node = new Array(); // this array will be an element of object 'tree'
		var nameParts = residues[i].name.split("-");
		node["node_id"] = residues[i].residue_id;
		node["name"] = nameParts[0];
		node["substituent"] = "";
		if (nameParts.length > 1) { // the residue has a substituent
			node["substituent"] = nameParts[1];
		}
		node["parent_id"] = residues[i].parent_id;
		node["anomer"] = residues[i].anomer;
		node["absolute"] = residues[i].absolute;
		node["ring"] = residues[i].ring;
		node["site"] = residues[i].site;
		node['touched'] = false;
		tree['nodes'].push(node);
	}
	return (tree);
} // end of function plantTree()



function terminate(which) {
	var termStr = "<br><br><br><br><br><br><h1>" + which + " Data for " + acc[0] + " Is Not Available</h1>";
	termStr += "<h3>Missing: </h3>";
	for (i = 0; i < unavailable.length; i++) {
		termStr += "<h3>" + unavailable[i] + "</h3>";
	}
	$("#" + hDiv).html(termStr);
	$("#" + iDiv).html("");
	$("#progressDiv").css("visibility","hidden");
}


function initialize() {
	$("#progressDiv").css("visibility","visible");
	if (v > 0) console.log("##### Initializing #####");
	setupAnimation('logo_svg', 'header');
	var arg = window.location.search.substring(1);
	// setup arrays with input parameters
	// account for http requests from html <form> (GET method)
	var foc = "";
	var aParts = arg.split("&");
	for (var i = 0; i < aParts.length; i++) {
		aSplit = aParts[i].split("=");
		switch(aSplit[0]) {
			case("focus"):
				foc = aSplit[1];
				break;
			case("residue"):
				hRes = aSplit[1];
				break;
			default:
				foc = aSplit[0];
		}
	}
	populateInput(foc);	
	mStr["listHead"] = templates["listHead"].replace(/@ACCESSION/g, foc);

	// fetch configuration data
	fetchConfiguration(configPath);
	// fetch and process the images and data
	getFiles(0);
	wait2add();

} // end of function initialize()
