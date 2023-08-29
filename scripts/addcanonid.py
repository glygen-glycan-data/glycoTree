#!/bin/env python3

import csv, sys, os

# Accession       GlycoCTResidueIndex     CanonicalResidueIndex
canonids = dict()
for resmapfiles in sys.argv[1:]:
    for row in csv.DictReader(open(resmapfiles),dialect='excel-tab'):
        canonids[(row['Accession'],row['GlycoCTResidueIndex'])] = row['CanonicalResidueIndex']

# glytoucan_ac,residue_name,residue_id,name,anomer,absolute,ring,parent_id,site,form_name,glycoct_index,notes
headers = None
rows = csv.DictReader(sys.stdin)
for row in rows:
    if not headers:
        headers = rows.fieldnames
        ind = headers.index('glycoct_index')
        headers.insert(ind+1,'canonical_residue_index')
        print(",".join(headers))
    row['canonical_residue_index'] = canonids.get((row['glytoucan_ac'],row['glycoct_index']),"")
    print(",".join(map(lambda h: row.get(h) if row.get(h) else "",headers)))
    
    


