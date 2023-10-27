#!/bin/env python3

import sys
import csv
from collections import defaultdict

# instance,residue_name,residue_id,type,uniprot,notes,status,proposer_id,administrator,disputer_id
residues_with_enzymes = set()
enzyme_mappings = "SQL/enzyme_mappings.csv"
for row in csv.DictReader(open(enzyme_mappings)):
    resid = row['residue_id'] 
    up = row['uniprot']
    if up:
        residues_with_enzymes.add(resid)

not_fully_mapped_glycans = set()
not_fully_mapped_and_validated_glycans = set()
validated_residues = set()
compositions = "SQL/compositions.csv"
for row in csv.DictReader(open(compositions)):
    if row["residue_name"] == 'unassigned':
        not_fully_mapped_glycans.add(row["glytoucan_ac"])
        not_fully_mapped_and_validated_glycans.add(row["glytoucan_ac"])
    elif row["notes"].lower() not in ("validated by qrator","manually validated"):
        not_fully_mapped_and_validated_glycans.add(row["glytoucan_ac"])
    else:
        validated_residues.add(row["residue_id"])

freq = defaultdict(int)
for row in csv.DictReader(open(compositions)):
    if row["residue_id"] not in residues_with_enzymes and \
       row["residue_id"] in validated_residues and \
       row["glytoucan_ac"] not in not_fully_mapped_and_validated_glycans:
        freq[row["residue_id"]] += 1
            
for i,(resid,cnt) in enumerate(sorted(freq.items(),key=lambda t: -t[1])[:30]):
    print("\t".join(map(str,[i+1,resid,cnt])))
