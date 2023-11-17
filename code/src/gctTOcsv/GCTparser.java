import java.util.ArrayList;
import java.util.Arrays;
import java.util.HashMap;
import java.util.Iterator;
import java.util.List;
import java.util.Map;

/**
 *  GCTparser is a utility class for parsing and disambiguating the semantics of a GlycoCT file
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
 * <br>
 * @author wsyork
 *
 */
public class GCTparser {
	/**
	 * array of valid GlycoCT anomer attributes, mapped to their glycoTree Strings
	 */
	static String[] ANOMER = {
			"x,x",
			"a,a",
			"b,b",
			"o,o"
	};

	/**
	 * array of valid GlycoCT ring form attributes, mapped to their glycoTree Strings
	 */
	static String[] RING = {
			"1:4,f",
			"1:5,p",
			"2:6,p",
			"0:0,ol",
			"x:x,x",
			"1:x,x"
	};

	/**
	 * array of valid GlycoCT "stereoidentifier" attributes, mapped to their glycoTree Strings
	 */
	static String[] CONFIG = {
			"dglc,Dglc",
			"xglc,xglc",
			"dman,Dman",
			"xman,xman",
			"dgal,Dgal",
			"xgal,xgal",
			"lgal,Lgal",
			"dgro,Dgro",
			"dxyl,Dxyl",
			"lgul,Lgul",
			"xxyl,xxyl"
	};

	/**
	 * array of valid GlycoCT "superclass" attributes, mapped to their glycoTree Strings
	 */
	static String[] SUGSIZE = {
			"pen,pen",	
			"hex,hex",	
			"hep,hep",	
			"oct,oct",	
			"non,non"	
	};

	/**
	 * array of valid GlycoCT "modification" attributes, mapped to their glycoTree Strings
	 */
	static String[] SUGMOD = {
			"1:a,1:a",
			"1:d,1:d",
			"2:d,2:d",
			"2:keto,2:keto",
			"3:d,3:d",
			"6:d,6:d",
			"6:a,6:a",
			"1:aldi,1:aldi"
	};
	
	/**
	 * array of GlycoTree sugar names, mapped to combinations of monosaccharide residue attributes
	 */
	static String[] MATCHER = {
			"Res,x",
			"Hex,hex",
			"Gal,gal,hex",
			"GalA,gal,hex,6:a",
			"Glc,glc,hex",
			"GlcA,glc,hex,6:a",
			"Man,man,hex",
			"Neu,gro-gal,non,1:a-2:keto-3:d",
			"Fuc,gal,hex,6:d",
			"Xyl,xyl,pen",
			"Gul,gul,hex"
	};
	
	/**
	 * array of valid GlycoCT N-substituent attributes, mapped to their glycoTree Strings
	 */
	static String[] NSUBTITUTES = {
			"n-acetyl,NAc",
			"n-glycolyl,NGc",
			"n-sulfate,NS"
	};

	/**
	 * array of valid GlycoCT O-substituent attributes, mapped to their glycoTree Strings
	 */
	static String[] OSUBTITUTES = {
			"sulfate,S",
			"phosphate,P",
			"methyl,Me"
	};
	

	/**
	 * a map of feature maps, classified by type (anomer, ring form, etc) - populated during 
	 * instantiation of the class using data in CONSTANT arrays  
	 */
	Map<String, Map<String, String>> features = new HashMap<String, Map<String, String>>();


	/**
	 * generator of GCTparser, populating feature maps
	 */
	public GCTparser() {
		Map<String, String> anomer = generateSubmap(ANOMER) ;
		this.features.put("anomer", anomer);
		
		Map<String, String> ring = generateSubmap(RING) ;
		this.features.put("ring", ring);
		
		Map<String, String> config = generateSubmap(CONFIG) ;
		this.features.put("config", config);
		
		Map<String, String> sugSize = generateSubmap(SUGSIZE) ;
		this.features.put("sugSize", sugSize);
		
		Map<String, String> sugMod = generateSubmap(SUGMOD) ;
		this.features.put("sugMod", sugMod);
		
	} // end of Generator for baseParser()

	

	/**
	 * generates a feature map for a specific feature type (anomer, ring form, etc)
	 * @param mapString an CONSTANT String array mapping features of a given type 
	 * @return the feature Map for the feature type
	 */
	public 	Map<String, String> generateSubmap(String[] mapString) {
		Map<String, String> subMap = new HashMap<String, String>();
		for (int i = 0; i < mapString.length; i++) {
			String[] kv = mapString[i].split(",");
			subMap.put(kv[0],  kv[1]);
		}
		return(subMap);
	} // end of method generateSubmap()
	
	
	/**
	 * fetches a glycoTree representation of a specific GlycoCT feature, mapped to its feature type
	 * @param fString the string specifying a GlycoCT feature (e.g., "dman" or "1:5")
	 * @return the glycoTree representation of the feature, expressed as a comma-separated key-value pair 
	 */
	public String getFeature(String fString) {
		String result = "";
		for(String k2 : features.keySet()) {
			 Map<String, String> fClass = features.get(k2);
			 // fClass is a HashMap "submap" of HashMap features, like the Hashmap called "anomer"
				for(String k1 : fClass.keySet()) {
					String fValue = fClass.get(k1);
					// check if the fClass HashMap has an element that matches fString
					if (k1.toLowerCase().matches(fString.toLowerCase())) {
						result = String.format("%s,%s", k2, fValue);
					}
					
				}
		}
		return(result);
	} // end of method getFeature()

	
	/**
	 * generates a map of features for a given monosaccharide, indexed by feature type
	 * @param baseString a string holding the data part of a single line from the RES section of a GlycoCT file
	 * @param v the verbosity of the output to StdOut
	 * @return a map of features for a given monosaccharide, indexed by feature type
	 */
	public Map<String, String> parseBaseType(String baseString, int v) {
		// process the features 
		Map<String, String> result = new HashMap<String, String>();
		result.put("anomer", "");
		result.put("absConfig", "");
		result.put("relConfig", "");
		result.put("sugSize", "");
		result.put("ring", "");
		result.put("sugMod", "");

		String[] parts = baseString.split("[-|]");
		for (int i = 0; i < parts.length; i++) {
			String fss = getFeature(parts[i]);
			if (v > 4) System.out.printf("\n feature substring is %s", fss);
			addFeature(fss, result);
		}

		// addFeature APPENDS text to the value of the feature, so initializing the 
		//   value of the feature with a default value will sometimes lead to errors
		//    - the next few lines address this
		if (result.get("anomer") == "") result.put("anomer", "x");
		if (result.get("absConfig") == "") result.put("absConfig", "x");
		if (result.get("relConfig") == "") result.put("relConfig", "x");
		if (result.get("sugSize") == "") result.put("sugSize", "x");
		if (result.get("ring") == "") result.put("ring", "x");
		if (result.get("sugMod") == "") result.put("sugMod", "x");
		
		if (v > 4) {
			System.out.printf("\n%s", baseString );
			System.out.printf("\nabsConfig is %s", result.get("absConfig") );
			System.out.printf("\nrelConfig is %s", result.get("relConfig") );
			System.out.printf("\nanomer is %s", result.get("anomer") );
			System.out.printf("\nsugSize is %s", result.get("sugSize") );
			System.out.printf("\nring is %s", result.get("ring") );
			System.out.printf("\nsugarMod is %s", result.get("sugMod") );
		}
			
		return(result);
	} // end of method parseBaseType()
		
	
	/**
	 * generates a map of features for a given substituent, indexed by feature type
	 * @param sString a string holding the data part of a single line from the RES section of a GlycoCT file
	 * @param v the verbosity of the output to StdOut
	 * @return a map of features for a given substituent, indexed by feature type
	 */
	public Map<String, String> parseSubstituent(String baseString, int v) {
		// process the features 
		Map<String, String> result = new HashMap<String, String>();
		result.put("anomer", "n");
		result.put("absConfig", "n");
		result.put("relConfig", "n");
		result.put("sugSize", "n");
		result.put("ring", "n");
		result.put("sugMod", "x");
		
		if (v > 4) {
			System.out.printf("\n%s", baseString );
			System.out.printf("\nabsConfig is %s", result.get("absConfig") );
			System.out.printf("\nrelConfig is %s", result.get("relConfig") );
			System.out.printf("\nanomer is %s", result.get("anomer") );
			System.out.printf("\nsugSize is %s", result.get("sugSize") );
			System.out.printf("\nring is %s", result.get("ring") );
			System.out.printf("\nsugarMod is %s", result.get("sugMod") );
		}
			
		return(result);
	} // end of method parseSubstituent()
	
	
	
	/**
	 * adds feature attribute(s) (key-value pair) to the feature Map for a given monosaccharide residue  
	 * @param fStr a String holding the representation of the feature
	 * @param featureMap the Map holding the features of a given monosaccharide residue 
	 */
	public void addFeature(String fStr, Map<String, String> featureMap) {
		String[] s = fStr.split(",");
		String key = s[0];
		String val = s[1];
		String sep = "";
		if (key.matches("config") ) {
			// split into absolute and relative parts
			String f = featureMap.get("absConfig");
			if (f.length() > 0) sep = "-";
			f += sep + val.substring(0,1);
			featureMap.replace("absConfig", f);
			f = featureMap.get("relConfig");
			f += sep + val.substring(1,val.length());
			featureMap.replace("relConfig", f);
		} else {
			String f = featureMap.get(key);
			if (f.length() > 0) sep = "-";
			f += sep + val;
			featureMap.replace(key, f);
		}
	} // end of addFeature()
	
	/**
	 * calculates the glycoTree sugar name from a feature Map for a given monosaccharide residue
	 * @param query the feature Map for a given monosaccharide residue
	 * @return the glycoTree sugar name
	 */
	public String getSugarName (Map<String, String> query) {
		String name = "";
		int maxMatch = 0;
		for (int i = 0; i < MATCHER.length; i++ ) {
			String[] criteria = MATCHER[i].split(",");
			int matchCount = 0;
			int n = criteria.length;
			for (int j = 0; j < n; j++ ) {
				for(String k : query.keySet()) {
					if (query.get(k).matches(criteria[j] )) {
						matchCount++;
					}
				}
			}
			if ( (matchCount == (n - 1) ) && (matchCount > maxMatch) ) {
				maxMatch = matchCount;
				name = criteria[0];
			}
		}
		return(name);
	} // end of method getSugarName
	
	/**
	 * extends the name of a monosaccharide residue to account for substituents and their linkage positions
	 * @param resName the glycoTree name of the residue
	 * @param substituentName the GlycoCT string for the substituent
	 * @param linkPos the position at which the substituent is linked to the monosaccharide
	 * @return the extended name of the the glycoTree sugar name
	 */
	public String extendName(String resName, String substituentName, String linkPos) {
		String extendedName = resName;
		// TODO: account for second N-substituent - this should be a full-fledged residue
		for (int i = 0; i < NSUBTITUTES.length; i++ ) {
			String[] kv = NSUBTITUTES[i].split(",");
			if (kv[0].matches(substituentName.toLowerCase()) ) {
				// ensure that previously processed O-substituents are KEPT at the END of the name
				if (resName.contains("-")) {
					String[] nameParts = resName.split("-");
					System.out.printf("\n!!!!!!! split components are %s %s %s", nameParts[0], kv[1], nameParts[1]);
					extendedName = nameParts[0] + kv[1] + "-" + nameParts[1];
				} else {
					extendedName = resName + kv[1];
				}
			}
		}
		
		// TODO: delete the following - substitutents now are full-fledged glycotree residues
		for (int i = 0; i < OSUBTITUTES.length; i++ ) {
			String[] kv = OSUBTITUTES[i].split(",");
			if (kv[0].matches(substituentName.toLowerCase()) )
				extendedName = resName + "-" + linkPos + kv[1];
		}

		return(extendedName);
	}
	
	
	
}
