// These data could be easily converted to json objects and consumed as such

// strings used in 'select' tag
var selectStrings = {
	"all": "All Biosynthetically Related Glycans",
	"precursors": "Possible Precursors",
	"products": "Possible Products",
	"anomers": "Modified Reducing End",
	"match": "Matching Reducing End",
	"specified": "Fully Specified Reducing End"
};

// template strings modified during runtime
var templates = {
        "glygenLink": " (<a href='@GLYGEN@ACCESSION' target='glygen_frame'>GlyGen Data</a>)",
	"infoHead": "Exploring glycan @ACCESSION@GLYGENLINK",
	"listHead": "Glycans Biosynthetically Related to @ACCESSION",
	"gnomeLink": "Explore accessions related to @ACCESSION using the <a href='@GNOME@ACCESSION' target='_blank'><i>GNOme Subsumption Navigator</i></a>",
	"sandLink": "<a href='explore.html?@ACCESSION' target='_blank'>Click here to open a <i>new Sandbox Window</i></a> to explore glycans biosynthetically related to @ACCESSION<br><br>Alternatively, you can double click on any glycan's canvas to fully explore it in <i>this window</i><br>This will allow you to navigate from sandbox to sandbox using the back and forward buttons on your browser ",
	"enzHead": "Enzymes that directly impact residue @RESID during biosynthesis of @ACCESSION",
	"enzAll": "Enzymes that directly impact residues in @ACCESSION during its biosynthesis"
};

// modified versions of template strings
var mStr = {
	"null": "null"
}

// constant strings
var dStr = {
	"resTable": "Go to the Residue Table",
	"glyTable": "Go to the Glycan Table",
	"imgHead": "Reference Glycan",
	"tableEnd":	"These data were compiled by \
<a href='https://www.ccrc.uga.edu/personnel/index.php?uid=79&personnel=Non%20Tenure-Track%20Faculty' \
target='_blank'>Dr. Alison Nairn</a>, and reflect knowledge accumulated by \
Dr. Kelley Moremen, Dr. Nairn, and other members of Dr. Moremen's laboratory.  (See \
<a href='https://www.ccrc.uga.edu/~moremen/glycomics/' target='_blank'><b>this web site</b></a>.) \
<p>The proteins in this list are limited to those enzymes  \
(e.g., glycosyl transferases) that <i>directly impact</i> (e.g., attach) \
<b>residues that are mapped to GlycoTree <i>and</i> that are found in the mature glycan</b>. \
<br>Enzymes involved in generating precursors \
such as lipid-linked intermediates or nucleotide sugars are <b>not shown</b>.</p> \
Software to generate and display the full biosynthetic pathway for each glycan is currently under development. <br>\
To see specific enzymes that directly impact each residue in the glycan, click on the \
<a href='https://www.ncbi.nlm.nih.gov/glycans/snfg.html' target='_blank'>SNFG</a> \
symbol for that residue in a structure on the left."
}

var URLs = {
	"gnome_glycotree_nlinked": "https://gnome.glyomics.org/restrictions/GlycoTree_NGlycans.StructureBrowser.html?focus=",
	"gnome_glycotree_olinked": "https://gnome.glyomics.org/restrictions/GlycoTree_OGlycans.StructureBrowser.html?focus=",
	"gnome": "https://gnome.glyomics.org/restrictions/GlyGen.StructureBrowser.html?focus=",
	"glygen_glycan": "https://www.glygen.org/glycan/",
	"gene": "https://www.genecards.org/cgi-bin/carddisp.pl?gene=",
	"glygen_protein": "https://www.glygen.org/protein/",
	"uniprot": "https://www.uniprot.org/uniprot/",
	"taxonomy": "https://www.ncbi.nlm.nih.gov/taxonomy/?term=",
	"gene_id": "https://www.ncbi.nlm.nih.gov/gene/"
}
