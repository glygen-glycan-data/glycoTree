#!/bin/sh
echo
echo "dumping curated SQL Tables"
echo

user='gt_user'
export MYSQL_PWD="$MYSQL_PASSWORD"

set -x
MYSQL="mysql -u $user -h localhost -P 3306 --raw --batch --database glycotree"

# echo "SHOW GRANTS for 'gt_user'@'localhost';" | mysql -uroot -h localhost -P 3306 -v -v -v
# echo "GRANT FILE ON *.* TO 'gt_user';" | mysql -uroot -h localhost -P 3306 -v -v -v
# echo "SHOW GRANTS for 'gt_user'@'%';" | mysql -uroot -h localhost -P 3306 -v -v -v

# HEADERS="type,orthology_group,uniprot,protein_refseq,dna_refseq,gene_name,gene_id,species,branch_site_specificity"
# ./dumptsv.sh enzymes "$HEADERS" | $MYSQL | tr '\t' ',' > enzymes.csv 

HEADERS="instance,residue_name,residue_id,type,uniprot,notes,status,proposer_id,administrator,disputer_id"
./dumptsv.sh enzyme_mappings "$HEADERS" | $MYSQL | tr '\t' ',' > enzyme_mappings.csv

# HEADERS="rule_id,class,description,logic"
# ./dumptsv.sh rules "$HEADERS" | $MYSQL > rules.tsv

HEADERS="instance,rule_id,focus,enzyme,other_residue,polymer,taxonomy,proposer_id,refs,comment,status,administrator,disputer_id"
./dumptsv.sh rule_data "$HEADERS" | $MYSQL > rule_data.tsv

cp -f enzyme_mappings.csv rule_data.tsv ../model
