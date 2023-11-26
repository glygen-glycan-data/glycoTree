import java.util.Arrays;
import java.util.Collections;
import java.util.Map;
import java.util.Map.Entry;

 /**
 * Sorter is a gctTOcsv utility class to facilitate sorting of various types of Collections
 * 
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
 */
public class Sorter {

	public Sorter() {

	}
	
	/**
	 * generates a numerically sorted array of the keys for a Map of Maps, which can be 
	 * iterated in numerically increasing order simply by using "for" loop
	 * @param map the Map whose keys are to be numerically sorted
	 * @return an array containing the keys as Strings, whose values have been <i>numerically</i> sorted
	 */
	public String[] sortMapKeysNumeric (Map<String, ?> map) {
		String[] sortedKeys = new String[map.size()];
		int[] numericIndices = new int[map.size()];
		int count = 0;
		// populate numericIndices with (int) keys
		try {
			for (Map.Entry<String, ?> entry : map.entrySet())  {
				numericIndices[count++] = Integer.valueOf(entry.getKey() );
			} 
		} catch(NumberFormatException e) {
			System.out.println("key cannot be properly sorted, it is not a number");
		}
		
		// numerically (NOT lexically) sort the elements of numericIndices 
		Arrays.sort(numericIndices);
		
		// convert sorted numericIndices to Strings and populate sortedKeys
		for (int i = 0; i < numericIndices.length; i++) {
			sortedKeys[i] = Integer.toString(numericIndices[i]);
		}
		return (sortedKeys);
	}
	



}
