import java.io.BufferedWriter;
import java.io.File;
import java.io.FileNotFoundException;
import java.io.FileWriter;
import java.io.IOException;
import java.util.*;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import java.time.ZoneId;
import java.time.ZonedDateTime;

public class CorrelateGlycans {
	/**
	 * the verbosity of the output to stdout
	 */
	static int v = 0; 

	/**
	 * size of BitSet corresponding to (at least) the number of residues in the canonical tree - 
	 *     Due to memory allocation issues, this should be a power of 2 (e.g., 2^9 = 512 )
	 */
	static int nCols = 512; 
	
	/**
	 *  BitSet elements each indicate the presence (set) or absence (unset) 
	 *  of a canonical residue in the structure - 
	 *  Map keys are accessions 
	 */
	static Map<String, BitSet> m = new HashMap<String, BitSet>();  
	
	/**
	 * correlation matrix - each element is the count (as byte) of common residues for a pair of structures
	 */
	static byte[][] c;
			
	/**
	 * This ArrayList contains an ordered list of accessions with (int) indices -
	 * accessions will be ordered the same way they are in the input file (lexical order is best)
	 */
	static ArrayList aMap = new ArrayList();
	
	/**
	 * the number of fully mapped structures to be included in the analysis 
	 *  partially mapped structures cannot be correlated 
	 */
	static int count = 0;
	
	/**
	 * an accession to be explicitly correlated to another accession (accB)
	 */
	static String accA = "";

	/**
	 * an accession to be explicitly correlated to another accession (accA)
	 */
	static String accB = "";
	
	/**
	 * the name of the output directory holding json files (output)
	 *
	*/
	static String jsonDir = "";
	
	/**
	 * the name of a file where base64 encodings will be written
	 */
	static String b64file = "";

	/**
	 * not yet implemented, maybe useful for clustering analysis
	 */
	static Map<String, Double> r = new HashMap<String, Double>();
	

	public static void main(String[] args) {
		
		String listFileName = null;
		String glycanFileName = null;
		String csvFileName = "";

		for (int i = 0; i < args.length; i++) {
			char c1 = args[i].charAt(0);
			char c2 = '0';
			if (args[i].length() > 1) c2 = args[i].charAt(1);

			if (c1 == '-') {
				switch (c2) {
				case 'v':
					i++;
					v = Integer.valueOf(args[i]);
					break;
				case 'c':
					i++;
					nCols = Integer.parseInt(args[i]);
					break;
				case 'l':
					i++;
					glycanFileName = null; // if list file is given LAST, glycan file is ignored
					listFileName = args[i];
					break;
				case 'g':
					i++;
					listFileName = null; // if glycan file is given LAST, list file is ignored
					glycanFileName = args[i];
					break;	
				case 'o':
					i++;
					csvFileName = args[i];
					break;						
				case 'a':
					i++;
					accA = args[i];
					break;
				case 'b':
					i++;
					accB = args[i];
					break;
				case 'j':
					i++;
					jsonDir = args[i];
					break;
				case 's':
					i++;
					b64file = args[i];
					break;
				default:
					System.out.printf("unsupported argument: %s\n", args[i]);
				}
			} else {
				System.out.printf("poorly formed argument (no dash): %s\n", args[i]);
			}
		}

		if (v > 0) {
			ZoneId zonedId = ZoneId.of("America/New_York");
			ZonedDateTime justNow = ZonedDateTime.now(zonedId);
			String nowStr = String.format("Current Time : %tF %<tR", justNow);
			System.out.printf("%s\n", nowStr);
			System.out.printf("Verbosity: %s\n", v);
			if (listFileName != null)
				System.out.printf("List File Name: %s\n", listFileName);
			if (glycanFileName != null)
				System.out.printf("Glycan File Name: %s\n", glycanFileName);
			if (csvFileName != null)
				System.out.printf("Output CSV File Name: %s\n", csvFileName);
		}
		
		ArrayList<String> files = new ArrayList<String>();
		if (listFileName != null) {
			// open and parse the list file, putting elements in the ArrayList "files"
			if (v > 0) 
				System.out.printf("\n###### Using a list (%s) containing input files ######", listFileName);
			files = parseListFile(listFileName);
			if (v > 0) 
				System.out.printf("\n######  The list contains %d files ######\n", files.size());
		} else if (glycanFileName != null) {
			// just use the glycanFileName as the glycan file file
			files.add(glycanFileName);
		} else {
			System.out.printf("** No valid glycan input files ** \n");
		}
		
		Boolean foundAfile = false;
		 //for (Iterator<String> f_iter = files.iterator(); f_iter.hasNext();) {
		for (int i = 0; i < files.size(); i++) {
			foundAfile = true;
			// glycanFileName = f_iter.next(); 
			glycanFileName = files.get(i); 
			// Process the current file
			if (v > 4) {
				System.out.printf("\n########## Processing file %s ##########", glycanFileName);
			}

			// m is Map<String, BitSet>
			importData(glycanFileName, m);
		}	
		
		// c is the correlation matrix
		c = new byte[count][count];
		
		if (v > 4) System.out.printf("\n\nMap m contains %d BitSet(s)", m.size());
		// generate a write base64 compositions to file
		StringBuffer b64Str = new StringBuffer("glytoucan_ac\tbase64_composition");
		for (Map.Entry<String, BitSet> entry : m.entrySet()) {
			String key = entry.getKey();
			BitSet a = entry.getValue();
			byte [] bytes = a.toByteArray();
		    String b64 = Base64.getEncoder().encodeToString(bytes);
		    b64Str.append("\n" + key + "\t" + b64);
		    if (v > 8) {
    			System.out.printf("\n BitSet %s contains %d bits", key, a.size());
    			System.out.printf(": bits %s are set", a.toString());
    		    System.out.printf("\nbase64 encoding:\n   %s", b64); 
    		}
		}

		// generate correlation matrix, over aMap, which only contains accessions for fully mapped structures
		for (int i = 0; i < aMap.size(); i++) {
			String key1 = (String) aMap.get(i);
	    	BitSet a = m.get(key1);
	    	// initialize a vector of cardinalities for inner products of BitSet 'a' with each BitSet 'b'
            //  for large data sets, cRow is byte[], and c is byte[][]
			byte[] cRow = new byte[count];

		    if (v > 4) System.out.printf("\n Calculating correlations for %s", key1);
		    for (int j = 0; j < aMap.size(); j++) {
		    	String key2 = (String) aMap.get(j);
		    	BitSet b = m.get(key2);

		    	if (v > 8) {
		    		System.out.printf("\n\n  %s: ", key1);
		    		int[] bitsA = a.stream().toArray();
		    		for (int k = 0; k < a.cardinality(); k++) System.out.printf("%d ", bitsA[k]);
		    		System.out.printf("\n  %s: ", key2);
		    		int[] bitsB = b.stream().toArray();
		    		for (int k = 0; k < b.cardinality(); k++) System.out.printf("%d ", bitsB[k]);
		    	}
		    	// calculate inner product (ip) of a with b (as bitwise AND)
		    	BitSet ip = (BitSet) b.clone();
		    	ip.and(a);
		    	if (v > 8) {
		    		int[] bitsIP = ip.stream().toArray();
		    		System.out.printf("\n  AND bits: ");
		    		for (int k = 0; k < ip.cardinality(); k++) System.out.printf("%d ", bitsIP[k]);
		    		System.out.printf("\n  correlation: %d", ip.cardinality());
		    	}
		    	cRow[j] = (byte) ip.cardinality();
		    }
		    c[i] = cRow;
        } 
		
		writeData(b64Str.toString(), b64file);
		
		// print correlation matrix
		if (v > 8) {
			System.out.printf("\n\n         ");
			for (int i = 0; i < count; i++) 
				System.out.printf("%9s", aMap.get(i));
			for (int i = 0; i < count; i++) {
				System.out.printf("\n%9s", aMap.get(i));
	    		for (int j = 0; j < count; j++) {
					System.out.printf("%6d   ", c[i][j]);
	    		}
			}
		}

		if (v > 1) 
			System.out.printf("\n\n### %d of the processed accessions are fully mapped to GlycoTree and included in the correlation matrix ###", count);
		
		if (v > 3) {
			if (count > 0) System.out.printf("\n\n### Accessions added to correlation matrix ###");
			for (int i = 0; i < count; i++) {
				if ( (i % 10) == 0) System.out.printf("\n");
				System.out.printf("%9s", aMap.get(i));
			}
		}
		
		StringBuffer csvBuffer = new StringBuffer("glytoucan_ac,dp,homolog,relative_dp,shared");
		System.out.println();
		for (int i = 0; i < count; i++) {
			String accI = (String) aMap.get(i);
			String record = generateCSV(accI);
			if (v > 8) System.out.printf("\n   returned record:%s", record);
			csvBuffer.append(record);
		}
		if (v > 4) System.out.printf("\n\ncsv output is:\n%s", csvBuffer.toString());
		writeData(csvBuffer.toString(), csvFileName);

		if ( (accA != "") && (accB != "") ) showCorrelation(accA, accB);
		
		if (v > 0) System.out.printf("\n\n### Processing Complete ###", args);
	} // end of method main()
	
	/**
	 * displays the correlation data for two glycan structures
	 * @param acc1 accession of the first glycan
	 * @param acc2 accession of the second glycan
	 */
	public static void showCorrelation (String acc1, String acc2) {
		System.out.printf("\n\nExplicit relationship between two structures specified in command-line arguments");
		int idA = aMap.indexOf(acc1);
		System.out.printf("\naccession[%d]: %s - dp: %d", idA, acc1, c[idA][idA]);
		BitSet mii = m.get(acc1);
		System.out.printf("\nBitSet:   %s", mii.toString());

		int idB = aMap.indexOf(acc2);
		System.out.printf("\naccession[%d]: %s - dp: %d", idB, acc2, c[idB][idB]);
		BitSet mjj = m.get(acc2);
		System.out.printf("\nBitSet:   %s", mjj.toString());
		System.out.printf("\nnumber of common residues: %d (symmetrically %d)", c[idA][idB], c[idB][idA]);
	}
	
	
	/**
	 * Generates a list of file names by reading an input file whose name is specified by the String fn
	 * @param fn A String containing the name of a file listing fully-specified input (csv) files  
	 * @return An ArrayList containing names of csv input file for processing
	 */
	public static ArrayList<String> parseListFile(String fn) {
		ArrayList<String> result = new ArrayList<String>();
		File file = new File(fn);
		if (file.exists()) {
			try {
				Scanner input = new Scanner(file);
				while (input.hasNext()) {
					String line = input.next();
					result.add(line);
					if (v > 5)
						System.out.printf("\nAdding file %s to list", line);
				}
				input.close();
			} catch (FileNotFoundException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			}
		} else {
			System.out.printf("\n### file %s does not exist ###", fn);
		}
		return (result);
	} // end of parseListFile

	
	public static int importData(String fn, Map<String, BitSet> map) {
		File file = new File(fn);
		String accession = fn.substring(0, fn.indexOf("."));
		int start = fn.lastIndexOf("/");
		String directory = "";
		if (start != -1) { // check if filename includes directory
			String trimmedFileName = fn.substring((start + 1), fn.length());
			directory = fn.substring(0, start + 1);
			accession = trimmedFileName.substring(0, trimmedFileName.indexOf("."));
		}
		if (v > 5) System.out.printf("\n  Accession is %s\n", accession);
		
	    Pattern p0 = Pattern.compile("^[0-9]");  // pattern starts with a number  
	    
  		// idMap is tiny, catering only to a few specific unusual node IDs
  		Map<String, Integer> idMap = new HashMap<String, Integer>();
  		idMap.put("NA", nCols - 3);
  		idMap.put("NB", nCols - 2);
  		idMap.put("NC", nCols - 1);
  		idMap.put("OC", nCols - 4);
  		
		if (file.exists()) {
			try {
				Scanner input = new Scanner(file).useDelimiter("\n");
				String header = input.next(); // blow past the header
				ArrayList<String> nodes = new ArrayList<String>();

				BitSet bs = new BitSet(nCols);

				while (input.hasNext()) {
					String line = input.next();
					String[] vals = line.split(",");
					if (v > 6) {
						System.out.printf("\nnode name is %s\n", vals[1]);
						System.out.printf("node ID is %s\n", vals[2]);
					}
					
				    Matcher idMatch = p0.matcher(vals[2]); // vals[2] is the node_id
					if (idMatch.find() == true) {  // // vals[1] starts with a number (not N or O) - it's unassigned
						if (v > 6) System.out.printf("## Residue %s is not assigned, setting bs[0] to 1\n", vals[2]);
						// bs[0] is 0 if structure HAS NO unassigned nodes, or 1 if structure HAS unassigned nodes
						bs.set(0);
					}
					nodes.add(vals[2]);
				}
				input.close();
				if (v > 6) System.out.printf("\n  Nodes %s\n", nodes);
			    Pattern p = Pattern.compile("(N[A-C]|OC)");   // include both N- and O-linked
			    Pattern p1 = Pattern.compile("[A-Za-z]"); // includes any non-numeric character
			    // iterate through all nodes in the current structure
				for (Iterator<String> n_iter = nodes.iterator(); n_iter.hasNext();) {
					String nd = n_iter.next();
					if (v > 8) System.out.printf("\nchecking node %s", nd);
				    Matcher ma = p.matcher(nd);
				    int id = 0;
				    // assign a numerical value to 'id' based on value of current node
				  	if (ma.find() == true) { // the current node is NA, NB, or NC
				  		id = idMap.get(nd);
				  		if (v > 8) System.out.printf("\nfound core node %s", nd);
				  	} else {
				  		// check if id is canonical (contains with a non-numeric character)
					    Matcher mf = p1.matcher(nd);
					    if (mf.find() == true) {
					    	id = Integer.parseInt(nd.substring(1));
					    	if (v > 6) System.out.printf("      Node %s has index %d\n", nd, id);
					    }
				  	}
			  		// set the bit corresponding to the node in the BitSet 
			  		if (id > 0) bs.set(id);
				}
				// add bs to the map<BitSet> and add accession to aMap
				map.put(accession, bs);
	            if (v > 3) System.out.printf("\n*** accession[%d] is %s ***", count, accession);
	            // the next line is executed only if bit[0] of bs is NOT true; i.e., mapping is complete 
	            //    then, aMap contains only fully mapped accessions
	            if (!bs.get(0)) aMap.add(count++, accession);
			}  catch (FileNotFoundException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
				return (0);
			} catch (ArrayIndexOutOfBoundsException e) {
				System.out.printf("\nArray index out of bounds for file %s\n", fn);
				e.printStackTrace();
			}
		} else {
			System.out.printf("\n### file %s does not exist ###", fn);
			return (0);
		}
		return (1);
	}
	
	
	public static int getDP(int id) {
		int dp = c[id][id];
		return(dp);
	}
	
	public static int getDP(String acc) {
		int id = aMap.indexOf(acc);
		int dp = c[id][id];
		return(dp);
	}
	
	
	/**
	 * DEPRECATED
	 * generate json encoding describing other accessions that are closely correlated to the argument accession
	 * @param accession a String holding the accession to be processed
	 */
	public static String generateJSON(String accession) {
		int probeDP = getDP(accession);
		int probeID = aMap.indexOf(accession);

		StringBuffer jsonStr = new StringBuffer();
		jsonStr.append(String.format("{\n  \"probe_acc\": \"%s\",", accession));
		jsonStr.append(String.format("\n  \"dp\":  \"%d\",", probeDP) );
		jsonStr.append(String.format("\n  \"related_glycans\": [ ") );
		String sep = "";	

		for (int i = 0; i < c.length; i++) {
			int targetDP = getDP(i);
			int nMatch = c[probeID][i];
			String targetAcc = (String) aMap.get(i);
			if ( (probeDP + targetDP - 2 * nMatch) < 2 ) { 
				// the target and probe match except for a single residue at most
				jsonStr.append(String.format("%s\n   {", sep));
				sep = ",";
				jsonStr.append(String.format("\n     \"accession\": \"%s\",", targetAcc));
				jsonStr.append(String.format("\n     \"relative_dp\": \"%+d\",", (targetDP - probeDP)));
				jsonStr.append(String.format("\n     \"match\": \"%d\"", nMatch));
				jsonStr.append(String.format("\n   }") );
			}

		}
		jsonStr.append(String.format("\n  ]")) ;
		jsonStr.append(String.format("\n}"));

		return(jsonStr.toString());
	}

	/**
	 * generate csv encoding describing other accessions that are closely correlated to the argument accession
	 * @param accession a String holding the accession to be processed
	 */
	public static String generateCSV(String accession) {
		int probeDP = getDP(accession);
		int probeID = aMap.indexOf(accession);

		StringBuffer recordBlock = new StringBuffer();
		if (v > 8) System.out.printf("\n\ngenerating record for %s:", accession);
		for (int i = 0; i < c.length; i++) {
			int targetDP = getDP(i);
			int nMatch = c[probeID][i];
			String targetAcc = (String) aMap.get(i);
			if ( (probeDP + targetDP - 2 * nMatch) < 2 ) { 
				String record = String.format("\n%s,", accession) + String.format("%d,", probeDP) +
						String.format("%s,", targetAcc) + String.format("%+d,", (targetDP - probeDP)) +
								String.format("%d", nMatch);	
				recordBlock.append(record.toString());
			}
		}
		if (v > 8) System.out.printf("   %s", recordBlock.toString());
		return(recordBlock.toString());
	}
	
	/**
	 * Writes String text to file instantiated with fileName
	 * @param text the String to be written
	 * @param fileName the name of the file to be written to
	 */
	public static void writeData(String text, String fileName) {
		if (v > 2)
			System.out.printf("\n### Writing to file: %s ###", fileName);
		try {
			BufferedWriter w = new BufferedWriter(new FileWriter(fileName));
			w.write(text);
			w.close();
		} catch (IOException e) {
			e.printStackTrace();
			System.out.printf("IO exception for file %s\n", fileName);
		}
	}
}
