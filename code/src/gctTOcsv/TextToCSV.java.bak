package gctTOcsv;


import java.util.HashMap;
import java.util.Map;


/**
 * Converts GlycoCT encoding into glycoTree csv encoding, without using eurocarbDB libraries. 
 * This is not meant to be inclusive of all types of glycans (like bacterial glycans), but is rather
 * limited to glycans that are amenable to specification by mapping to canonical tree structures<br>
 * Input parameters are described in the main() method documentation.
 * <br>
 *  Copyright 2020 William S York
 *  <br>
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *  <br>
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  <br>
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see &lt;https://www.gnu.org/licenses/&gt;.
 * 
 * @author wsyork
 *
 */
public class TextToCSV {


	/**
	 * the verbosity of the output to stdOut
	 */
	static int v = 0;
	
	/**
	 * the String holding the csv encoding
	 */
	static StringBuilder csvStr = null; 

	/**
	 * the core processing method.
	 * @param args the command-line arguments for invoking the GenerateCSV class.<br>
	 * Usage: GenerateCSV.jar input_file [list | single (default)] [verbosity (default = 0)]<br>
	 *  the input_file can be a single GlycoCT file of a list of GlycoCT files<br>
	 */
	public static void main(String[] args) {

		// parse arguments
		if (args.length < 1) {
			System.out.println("Usage: TextToCSV.jar [\"GlycoCT text\"]");
			System.exit(0);
		}

		for (int i = 0; i < args.length; i++) { 
			//System.out.println(args[i]);  
		}
				
		if (v > 0) System.out.printf("\nverbosity is %d", v);
			
			Map<String, Map<String, String>> residueList = new HashMap<String, Map<String, String>>();
			Map<String, String> substituentList = new HashMap<String, String>();

			
			try {
				String glycanName = "undetermined";
				String whichSection = "none";
				Boolean ok2go = true; // may change during iteration of gtcLines
				
				// for security, the GlycoCT encoding is passed to the jar file enclosed in special quotes 
				//   as defined by php [escapeshellarg]
				//     so this argument holds the (space-separated) elements of the GlycoCT encoding
				String[] gctLines = args[0].split(" ");
				
				for (int i = 0; i < gctLines.length; i++) {
					String line = gctLines[i];
					// System.out.printf("\n\n#### gct line[%d]  is \n%s", i, line);
					// whichSection is modified by parseGTCline - but ONLY when line is "RES" or "LIN"
					// otherwise parseGTCline parses the line and returns the same value of whichSection
					whichSection = parseGCTline(line, whichSection, residueList, substituentList);
					// System.out.printf("\n\nwhichSection is %s", whichSection);
					if (whichSection.compareTo("REP") == 0) {
						System.out.printf("\n\n!!! Repeating structures are not supported !!!\n No file generated");
						ok2go = false;
						break;
					}
				}

				if (ok2go) { // no repeating structures
					// find the index of the root of the structure tree
					if (v > 5) System.out.printf("\n\nfinding root residue ..");
					String rootID =  getRoot(residueList);
					// assign "0" to root's parent and link_position
					if (v > 5) System.out.printf("\n\n rootID is %s\n", rootID);
					residueList.get(rootID).put("parent", "0");
					residueList.get(rootID).put("link_position", "0");

					// generate csvStr
					csvStr = new StringBuilder("glytoucan_ac,residue_name,residue_id,name,anomer,absolute,ring,parent_id,site,formName");

					for (String k1 : residueList.keySet()) {
						// ToDo: check if each res element has an apropriate value ... otherwise append appropriate default value (e.g., "0" or "x")
						Map<String, String> res = residueList.get(k1);
						csvStr.append("\n" + glycanName + ",unassigned," + k1) ;

						appendCSVstr(res.get("name"), "none", csvStr);
						appendCSVstr(res.get("anomer"), "x", csvStr);
						// since the following line generates a substring, it must be first checked
						if ( (res.get("absConfig") != null) && (!res.get("absConfig").isEmpty() ) ) 
							appendCSVstr(res.get("absConfig").substring(0, 1), "x", csvStr);
						appendCSVstr(res.get("ring"), "x", csvStr);
						appendCSVstr(res.get("parent"), "x", csvStr);
						appendCSVstr(res.get("link_position"), "x", csvStr);
						appendCSVstr(res.get("formName"), "none", csvStr);
					}
					// csvStr is now complete

					System.out.printf("%s", csvStr);

				}
			}  catch (Exception e) {
				e.printStackTrace();
			}
		

	} // end of main

	public static void appendCSVstr(String value,  String defaultStr, StringBuilder csvStr) {
		if ( (value != null) && (!value.isEmpty() ) ) {
			csvStr.append("," + value);
		} else {
			csvStr.append("," + defaultStr);
		}
	}

	/**
	 * parses a line in the GlycoCT file, populating the resList and subList Maps, which hold the attributes
	 * of the residues and substituents
	 * @param gctLine the String holding the contents of a line from the GlycoCT file 
	 * @param section the GlycoCT section (RES | LIN)
	 * @param resList Map that holds the attributes of the residues
	 * @param subList Map that holds the attributes of the substituents
	 * @return a String holding the name of the GlycoCT section encoded by the gtcLine
	 */
	public static String parseGCTline(String gctLine, String section, Map<String, Map<String, String>> resList, Map<String, String> subList) {
		// if line is a section header (e.g., "RES"), just return the section currently with focus
		if (gctLine.matches("RES")) {
			return("RES"); // no data in this line
		}
		if (gctLine.matches("LIN")) {
			return("LIN"); // no data in this line
		}
		if (gctLine.matches("REP.*")) {
			return("REP"); // no data in this line
		}
		// catch different types of GlycoCT section headers
		if (gctLine.matches("UND.*")) {
			return("UND"); // no data in this line
		}

		// the following code is reachable if the section passed to method is "RES" or "LIN" or "UND"
		GCTparser gctParser = new GCTparser();

		int splitIndex = 0;
		String prefix = "";
		String suffix = "";
		String gctID = "";

		try {
			if ( (section.matches("RES") ) || (section.matches("LIN") ) ) {
				// parse the gctLine
				splitIndex = gctLine.indexOf(':');
				prefix = gctLine.substring(0, splitIndex);
				suffix = gctLine.substring(splitIndex+1, gctLine.length());
				gctID = prefix.split("[a-zA-Z]+")[0];
			}
		
			switch(section) {
			case "RES":
				String type = prefix.split("[0-9]+")[1];
				if (v > 1) System.out.printf("\n\nRES: id %s;  type %s", gctID, type);
				Map<String, String> sugarAtts =  new HashMap<String, String>();
				switch (type) {
				case "b":
						if (v > 6) System.out.printf("\ngctLine suffix is %s", suffix);
						sugarAtts =  gctParser.parseBaseType(suffix, v);
						String sugarName = gctParser.getSugarName(sugarAtts);
						sugarAtts.put("name", sugarName);
						String formName = getFormName(sugarAtts);
						sugarAtts.put("formName", formName);
						sugarAtts.put("gctID", gctID);

						if (v > 2) {
							System.out.printf("\n[%s] ", suffix );
							for(String k : sugarAtts.keySet()) {
								String p = sugarAtts.get(k);
								System.out.printf(" %s %s;", k, p );
							}
						}
						resList.put(gctID, sugarAtts);
					break;
				case "s":
					String subType = "O";
					if (suffix.indexOf('n') == 0) subType = "N";
					if (v > 2) {
						System.out.printf("\n[%s]:  This is an %s-linked substituent", suffix, subType);
					}
					if (subType.equals("N")) {
						subList.put(gctID, suffix);
					} else {
						sugarAtts =  gctParser.parseSubstituent(suffix, v);
						sugarAtts.put("name", suffix);
						sugarAtts.put("formName", suffix);
						sugarAtts.put("gctID", gctID);
						resList.put(gctID, sugarAtts);
					}
					break;
				}
				break;

			case "LIN":
				String[] parts = suffix.split("[\\(\\)]");  // split at open and close parentheses
				String parentID = parts[0].replaceAll("[a-zA-Z].*", "");  // numerical part before parentheses open
				String childID = parts[2].replaceAll("[a-zA-Z].*", ""); // numerical part after parentheses close
				String linkPos = parts[1].split("[+]")[0]; // part in parentheses before +
				if (v > 2) System.out.printf("\n\nLIN: [%s]: parentID %s; childID %s; link %s", suffix, parentID, childID, linkPos);
				if (linkPos.matches("-1")) {
					if (v > 2) System.out.printf("\n ### linkPos undefined %s",  linkPos);
					linkPos = "";
				}
				if ( resList.containsKey(parentID) ) { // the parent is a residue
					if ( resList.containsKey(childID) ) { // the child is a residue
						Map<String, String> childNode = resList.get(childID);
						childNode.put("parent", parentID);
						childNode.put("link_position", linkPos);
					} else if (subList.containsKey(childID) ) { // the child is a substituent
						// extend parent name
						String pName = resList.get(parentID).get("name");
						String sName = subList.get(childID);

						// extend parent (base) name
						String extName = gctParser.extendName(pName, sName, linkPos);
						// System.out.printf("\n outside");
						if (v > 3) System.out.printf("\n extended parent residue name for substituent %s is %s", childID, extName);
						resList.get(parentID).replace("name", extName);


						// !! Extend formName ONLY for N-substituents !! 
						//    I.e., do not extend formName for O-substutuents
						if (sName.contains("n-") ) {
							// System.out.printf("\n inside");
							pName = resList.get(parentID).get("formName");
							extName = gctParser.extendName(pName, sName, linkPos);
							if (v > 3) System.out.printf("\n extended parent residue formName for substituent %s is %s", childID, extName);
							resList.get(parentID).replace("formName", extName);
						}
					}
				}
				break;
			case "UND" :
				break;
			default:
				break;
			}
		} catch (Exception e) {
			System.out.printf("\n### Error in GlycoCT file ###\n    %s\n", gctLine);
			e.printStackTrace();
		}
		return(section);
	} // end of method parseGCTline()
	
	
	/**
	 * calculates the formName String, which includes the sugar name and its ring form
	 * @param query a Map holding the attributes of a specific sugar residue
	 * @return a String holding the sugar name and its ring form
	 */
	public static String getFormName(Map<String, String> query) {
		// query is a Map of attributes for a specific residue
		// do not include any chars in name after "-"
		String formName = query.get("name");
		for(String k : query.keySet()) {
			if (query.get(k).matches("[fp]|ol") ) {
				formName = formName + query.get(k);
				return(formName);
			}
		}
		return(formName);
	} // end of method getFormName()
	
	
	/**
	 * retrieves the root residue of the structure tree
	 * @param resList a Map that holds the attributes of the residues in the structure tree
	 * @return the GlycoCT index of the root residue of the structure tree
	 */
	public static String getRoot(Map<String, Map<String, String>> resList) {
		// must order keys NUMERICALLY so first element without a parent is the root
		            
		Sorter mapSorter = new Sorter();
		String[] sortedArray = mapSorter.sortMapKeysNumeric(resList);
		
		// root is the first (numerically) residue having no parent
		if (v > 7) for (int i = 0; i < sortedArray.length; i++) {
			System.out.printf("\n residue[%s] has parent ID: %s", sortedArray[i], resList.get(sortedArray[i]).get("parent") );
		}
			
		for (int i = 0; i < sortedArray.length; i++) {
			Map<String, String> res = resList.get(sortedArray[i]);
			if (!res.containsKey("parent") ) {
				return(sortedArray[i]);
			}
			
		}

		return("1"); // by default
	} // end of method getRoot()
	
	


}
