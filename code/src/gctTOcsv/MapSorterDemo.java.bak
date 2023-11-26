package gctTOcsv;

import java.util.HashMap;
import java.util.Map;
import java.util.Map.Entry;
import java.util.TreeMap;

/**
 * Demonstrates the sorting of Map elements, lexically or numerically.
 * Lexical sorting is trivial, by conversion of Map to a TreeMap.
 * However, numerical sorting can get complex and arcane. This Class
 * demonstrates a direct, simple approach, where the keys are converted
 * to integers, which are put into an array int[], sorted, and then 
 * converted back to Strings in an array String[], with the same order. 
 * @author wsyork
 *
 */
public class MapSorterDemo {

	public MapSorterDemo() {
	}

	public static void main(String[] args) {
		// generate an example - a Map of maps with String keys
		Map<String, Map<String, String>> students = new  HashMap<String, Map<String, String>>();
		Map<String, String> aStudent = new HashMap<String, String>();
		aStudent.put("id", "6453");
		aStudent.put("name", "Miles");
		aStudent.put("grade", "A");
		// the key for aStudent is the id extracted from aStudent
		students.put(aStudent.get("id"), aStudent);
		aStudent = new HashMap<String, String>();
		aStudent.put("id", "273");
		aStudent.put("name", "Amy");
		aStudent.put("grade", "A");
		students.put(aStudent.get("id"), aStudent);
		aStudent = new HashMap<String, String>();
		aStudent.put("id", "94");
		aStudent.put("name", "Barry");
		aStudent.put("grade", "C-");
		students.put(aStudent.get("id"), aStudent);
		aStudent = new HashMap<String, String>();
		aStudent.put("id", "7");
		aStudent.put("name", "Jerry");
		aStudent.put("grade", "A");
		students.put(aStudent.get("id"), aStudent);	
	
		//list students, unsorted
		System.out.printf("\n\nUnordered Students");
		for (Entry<String, Map<String, String>> entry : students.entrySet())  {
			System.out.printf("\nkey %s;", entry.getKey() );
			Map<String, String> value = entry.getValue();
			System.out.printf("  id %s; Name %s; grade %s", 
					value.get("id"), value.get("name"), value.get("grade"));
		}
		
		TreeMap<String, Map<String, String>> sortedMap = new TreeMap<String, Map<String, String>>(students);
		sortedMap.putAll(students);
		System.out.printf("\n\nLexically ordered Students");
		for (Entry<String, Map<String, String>> entry : sortedMap.entrySet())  {
			System.out.printf("\nkey %s;", entry.getKey() );
			Map<String, String> value = entry.getValue();
			System.out.printf("  id %s; Name %s; grade %s", 
					value.get("id"), value.get("name"), value.get("grade"));
		}
		
		
		System.out.printf("\n\nNumerically ordered Students");
		Sorter mapSorter = new Sorter();
		String[] sortedArray = mapSorter.sortMapKeysNumeric(students);
		for (int i = 0; i < sortedArray.length; i++) {
			String key = sortedArray[i];
			System.out.printf("\nkey %s;",  key);
			Map<String, String> value = students.get(key);
			System.out.printf("  id %s; Name %s; grade %s", 
					value.get("id"), value.get("name"), value.get("grade"));

		}
		
		System.out.printf("\n\nNumerically reverse-ordered Students");
		for (int i = sortedArray.length; i > 0; i--) {
			int j = i -1;
			String key = sortedArray[j];
			System.out.printf("\nkey %s;",  key);
			Map<String, String> value = students.get(key);
			System.out.printf("  id %s; Name %s; grade %s", 
					value.get("id"), value.get("name"), value.get("grade"));

		}
	}
}
