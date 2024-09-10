#!/bin/env python3

import sys 

from tree import GlycoTree
from collections import defaultdict

enzymes = defaultdict(lambda: defaultdict(set))
nodeid = defaultdict(dict)
sia = ('NeupNAc','NeupNGc','KDNp')
geneto = defaultdict(set)

gt = GlycoTree()
for r in gt.enzyme_table():
    if r['form_name'] in sia:
        if 'gene_name' in r:
            enzymes[r['parent_id'],r['anomer'],r['site']][r['form_name']].add(r['gene_name'])
            pfn = r['parent_form_name'].replace('xNAc','pNAc')
            geneto[r['gene_name']].add((pfn,r['anomer'],r['site']))
        nodeid[r['parent_id'],r['anomer'],r['site']][r['form_name']] = r['residue_id']

for g in sorted(geneto):
    if len(geneto[g]) > 1:
        print(g,geneto[g])

for pid,anomer,site in enzymes:
    allgene = set()
    for s in sia:
        if s in nodeid[pid,anomer,site]:
            allgene.update(enzymes[pid,anomer,site][s])
    bad = False
    for s in sia:
        if s in nodeid[pid,anomer,site]:
            if enzymes[pid,anomer,site][s] != allgene:
                bad = True
                break
    if not bad:
        continue
    print("Parent id %s has inconsistent Sia residue enzymes"%(pid,))
    for s in sia:
        if s in nodeid[pid,anomer,site]:
            print("  %s-%s %s (%s,%s) has genes: %s."%(anomer,site,s,pid,nodeid[pid,anomer,site][s],", ".join(sorted(enzymes[pid,anomer,site][s]))))
    print()
        

