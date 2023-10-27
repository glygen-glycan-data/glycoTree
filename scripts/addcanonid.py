#!/bin/env python3

import csv, sys, os
from collections import defaultdict

# Accession       GlycoCTResidueIndex     CanonicalResidueIndex
canonids = dict()
allids = defaultdict(set)
for resmapfiles in sys.argv[1:]:
    for row in csv.DictReader(open(resmapfiles),dialect='excel-tab'):
        canonids[(row['Accession'],row['GlycoCTResidueIndex'])] = row['CanonicalResidueIndex']
        allids[row['Accession']].add(row['GlycoCTResidueIndex'])

def output(acc,rows):
    extragctids = set(allids[acc])
    for r in rows:
        gctid = r['glycoct_index']
        if gctid in extragctids:
            extragctids.remove(gctid)
    extracanids = defaultdict(list)
    for gctid in extragctids:
        canid = canonids.get((acc,gctid),"")
        if canid:
            basecanid = canid.split('.')[0]
            extracanids[basecanid].append(canid)
    for r in rows:
        canid = canonids.get((acc,r['glycoct_index']),"")
        # if canid in extracanids:
        #     canid += ";" + ";".join(sorted(extracanids[canid],key=float))
        r['canonical_residue_index'] = canid
        print(",".join(map(lambda h: r.get(h) if r.get(h) else "",headers)))

# glytoucan_ac,residue_name,residue_id,name,anomer,absolute,ring,parent_id,site,form_name,glycoct_index,notes
headers = None
rows = csv.DictReader(sys.stdin)
lastacc = None
foracc = []
for row in rows:
    if not headers:
        headers = list(rows.fieldnames)
        if 'canonical_residue_index' not in headers:
            ind = headers.index('glycoct_index')
            headers.insert(ind+1,'canonical_residue_index')
        print(",".join(headers))
    acc = row['glytoucan_ac']
    if acc != lastacc:
        output(lastacc,foracc)
        foracc = []
        lastacc = acc
    foracc.append(dict(row.items()))
if lastacc != None:
    output(lastacc,foracc)
