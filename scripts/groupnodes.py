#!/bin/env python3

import sys 

from tree import GlycoTree, GlycoTreeDev
from collections import defaultdict

if len(sys.argv) > 1:
    focusnodes = set(open(sys.argv[1]).read().split())

gt = GlycoTreeDev()

#     Bos taurus
#     Sus scrofa
#     Rattus norvegicus                     

enzyme_species = set(filter(None,map(str.strip,"""
    Homo sapiens
    Mus musculus
""".splitlines())))

residue2enz = dict()
residue2edge = dict()
residue2cat = dict()
for rid in gt.all_residues():
    enz = []
    for e in gt.get_enzymes(rid):
        if e[2] in enzyme_species:
            enz.append(e[0])
    enzkey = "-".join(map(str,sorted(set(enz))))
    residue2enz[rid] = enzkey
    residue2edge[rid] = gt.get_edge_name(rid)
    residue2cat[rid] = gt.get_category(rid)

nodegroup = defaultdict(set)
totalresidues = 0
for rid in gt.all_residues():
    if not residue2enz[rid]:
        continue
    nodegroup[(residue2enz[rid],residue2edge[rid],residue2cat[rid])].add(rid)
    totalresidues += 1

print(f"{len(nodegroup)} groups, {totalresidues} residues")

print("\t".join("Nodes Category MonoAddition Focus HumanEnzymes MouseEnzymes".split()))
for i,(key,rids) in enumerate(sorted(nodegroup.items(),key=lambda t: (t[0][2],t[0][1]))):
    nodestr = str(i+1)+":"+",".join(sorted(rids)[:3])
    if len(rids) > 3:
        nodestr += ",..."
        nodestr += "(%d)"%(len(rids))
    rid = next(iter(rids))
    root = gt.get_category(rid)
    addn = gt.get_edge_name(rid)
    focus = ""
    if len(rids&focusnodes) > 0:
        focus = "*"
     
    henz = ",".join(sorted([ t[1] for t in gt.get_species_enzymes(rid,'Homo sapiens') ]))
    menz = ",".join(sorted([ t[1] for t in gt.get_species_enzymes(rid,'Mus musculus') ]))
    print("\t".join([nodestr,root,addn,focus,henz,menz]))
