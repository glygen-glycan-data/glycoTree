#!/bin/bash
# Usage: ./build_all.sh [clear_flag]
#  if the first argument is set to "clear", previous data for the N-tree will be cleared
#   Example:
#              ./build_all.sh clear &> ./log/build.log

# set -x

start=$(date)
here=`pwd`
echo "This program was called from directory $here" 
echo $start

N_Dir=$here/data/Nlinked
echo "N directory is $N_Dir"
csvN_Dir=$N_Dir/csv
echo "csv N directory is $csvN_Dir"

O_Dir=$here/data/Olinked
echo "O directory is $O_Dir"
csvO_Dir=$O_Dir/csv
echo "csv O directory is $csvO_Dir"

mappedDir=$here/data/mapped
echo "mapped global data directory is $mappedDir"
sortedDir=$mappedDir/sorted
echo "sorted directory is $sortedDir"
logDir=$here/log
echo "log directory is $logDir"
modelDir=$here/model
echo "model directory is $modelDir"
codeDir=$here/code
echo "code directory is $codeDir"
gctIn_N=$here/data/input_N
echo "gct source directory for N-glycans is $gctIn_N"
gctIn_O=$here/data/input_O
echo "gct source directory for O-glycans is $gctIn_O"
sqlDir=$here/SQL
echo "SQL directory is $sqlDir"
portalDir=$here/portal
echo "portal directory is $portalDir"
portalJavaDir=$portalDir/api/java
echo "portal java directory is $portalJavaDir"

for i in "$@"; do
  ## the key word 'clear' is in the argument list
  if [ $i = 'clear' ]; then
    echo "Clearing old glycoTree data files (.txt, csv, .lst, .gTree.svg, .GlycoCT.svg, etc)"
    ./2_clear_data.sh
  fi
done

echo
echo Preparing directories

mkdir -p ./data/mapped
mkdir -p ./data/mapped/sorted
mkdir -p ./model/bak

mkdir -p ./data/Nlinked
mkdir -p ./data/Nlinked/csv
##  make a symbolic link so output to ./data/Nlinked/csv/mapped/ goes to ./data/mapped/
rm -f $here/data/Nlinked/csv/mapped
ln -s $here/data/mapped/ $here/data/Nlinked/csv/mapped

mkdir -p ./data/Olinked
mkdir -p ./data/Olinked/csv
##  make a symbolic link so output to ./data/Olinked/csv/mapped/ goes to ./data/mapped/
rm -f $here/data/Olinked/csv/mapped
ln -s $here/data/mapped/ $here/data/Olinked/csv/mapped


## The following is deprecated (at least temporarily)
## echo
## echo Copying GOG files
## cp ./data/GOG/G*.txt ./data/gct/

## echo Copying extra GlycoCT files
## cp ./data/gct/extra/G*.txt ./data/gct/

NL=$'\n'

echo
echo "Copying GlycoCt files from $gctIn_N to $N_Dir"
cd $here/data/extra_N
cp G*.txt $gctIn_N
if [ -f residmap.txt ]; then
  awk 'NR > 1' residmap.txt >> $gctIn_N/residmap.txt
fi
cd $gctIn_N
cp G*.txt $N_Dir/
cd $here

echo
echo "Generating list of N-linked GlycoCT files to process and placing in file:$NL    $N_Dir/files.lst"
find $N_Dir -maxdepth 1 -name "G*.txt" -print | sort > $N_Dir/files.lst

echo
echo Generating glycoTree csv files for N-glycans
java -jar $codeDir/GenerateCSV.jar $N_Dir/files.lst list 1 &> $logDir/csv.log

echo
echo "Generating list of glycoTree N-linked csv files to process and placing in file:$NL    $csvN_Dir/files.lst"
find $csvN_Dir -maxdepth 1 -name "G*.csv" -print | sort > $csvN_Dir/files.lst

echo
echo Fetching current model files

## There should be only one file in ./model/ that matches 'N-canonical_residues*.csv'
node_file=`ls $modelDir/N_canonical_residues*.csv`
echo using node file $node_file
cp $node_file $portalJavaDir

sugar_file=`ls $modelDir/sugars*.csv`
echo using sugar file:\n    $sugar_file

enzyme_file=`ls $modelDir/enzyme_mappings*.csv`
echo using enzyme file:\n    $enzyme_file

echo
echo Mapping residues in N-glycan csv files to canonical tree 
java -jar $codeDir/TreeBuilder4.jar -l $csvN_Dir/files.lst -s $sugar_file -c $node_file -n 3 -v 1 -m 3 -e 1 -o $modelDir/ext.csv &> $logDir/map_N.log

echo
echo "Copying GlycoCt files from $gctIn_O to $O_Dir"
cd $here/data/extra_O
cp G*.txt $gctIn_O
if [ -f residmap.txt ]; then
  awk 'NR > 1' residmap.txt >> $gctIn_O/residmap.txt
fi
cd $gctIn_O
cp G*.txt $O_Dir/
cd $here

echo
echo "Generating list of O-linked GlycoCT files to process and placing in file:$NL    $O_Dir/files.lst"
find $O_Dir -maxdepth 1 -name "G*.txt" -print | sort > $O_Dir/files.lst

echo
echo Generating glycoTree csv files for O-glycans
java -jar $codeDir/GenerateCSV.jar $O_Dir/files.lst list 1 &> $logDir/csv.log

echo
echo "Generating list of glycoTree O-linked csv files to process and placing in file:$NL    $csvO_Dir/files.lst"
find $csvO_Dir -maxdepth 1 -name "G*.csv" -print | sort > $csvO_Dir/files.lst

## There should be only one file in ./model/ that matches 'O-canonical_residues*.csv'
node_file=`ls $modelDir/O_canonical_residues*.csv`
echo using node file $node_file
cp $node_file $portalJavaDir

echo
echo Mapping residues in O-glycan csv files to canonical tree 
java -jar $codeDir/TreeBuilder4.jar -l $csvO_Dir/files.lst -s $sugar_file -c $node_file -n 1 -v 1 -m 3 -e 1 -o $modelDir/ext.csv &> $logDir/map_O.log

echo 
echo Sorting mapped residues
$codeDir/sortCSV.sh $mappedDir 1 > $logDir/sort.log

echo
echo "Generating list of unassigned residues and placing in file:$NL    $modelDir/unassigned.csv"
echo "glycan_ID,residue,residue_ID,name,anomer,absolute,ring,parent_ID,site,form_name" > $modelDir/unassigned.csv
find $sortedDir -maxdepth 1 -name "G*.csv" -print | sort | xargs -I % grep -h "unassigned" % >> $modelDir/unassigned.csv

##  Make a large csv file containing data in directory ./data/mapped/sorted ##
echo "Assembling mapped/sorted csv file for import into DB file:$NL     $sqlDir/compositions.csv"
cd $sortedDir
awk -f $codeDir/assembleCompositions.awk G*.csv  | \
  $here/scripts/addcanonid.py $here/data/input_N/residmap.txt $here/data/input_O/residmap.txt \
  > $sqlDir/compositions.csv
cd $here

echo
echo "Generating file containing a list of mapped csv files:$NL    $mappedDir/files.lst"
find ./data/mapped -maxdepth 1 -name "G*.csv" -print | sort > $mappedDir/files.lst

echo
echo "Calculating common canonical residues in all accession pairs and writing related glycan data in file:$NL    $sqlDir/correlation.csv"
java -jar $codeDir/CorrelateGlycans.jar -v 2 -l $mappedDir/files.lst -c 1024 -j $modelDir/json/match -o $sqlDir/correlation.csv  -s $sqlDir/bitSet.tsv &> ./log/correlate.log

echo
echo "Generating a list of accessions currently supported by GlycoTree into file:$NL     $here/accessions.lst"
find $mappedDir -maxdepth 1 -name "G*.csv" -print | sed -e 's/^.*\///' -e 's/\..*$//' | sort > $here/accessions.lst

echo
echo "Writing file of structure properties:$NL	$sqlDir/structure.csv"
$here/scripts/structprops.py $here/data/glygen_allacc.txt $sqlDir/compositions.csv $sqlDir/rule_data.tsv > $sqlDir/structure.csv

echo
echo "The home page for the portal:$NL     $portalDir/index.html$NL is no longer automatically generated"

echo Done
echo
echo Started processing: $start
echo Ended processing: $(date)
