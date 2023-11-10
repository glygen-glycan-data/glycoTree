#!/bin/env python3

import sys 

from tree import GlycoTree
from collections import defaultdict

gt = GlycoTree()
# origseeds = ['N2','N7','N5','N31','N9','O156','O157']
origseeds = ['N2','N7','N5','N31','N9']
# origseeds = ['O156','O157']
residuemap = dict()
residuemap[None] = None
leveltoresid = defaultdict(list)

def explore(seeds,level='1'):
    for s in seeds:
        if s:
            residuemap[s] = level
            leveltoresid[level].append(s)
    alledges = set()
    for s in seeds:
        alledges.update(gt.get_edges(s))
    for i,e in enumerate(sorted(alledges,key=lambda t: (t[2],t[1],t[0]))):
        seeds1 = [ gt.get_child(s,e,"-") for s in seeds ]
        explore(seeds1,level+"."+str(i+1))

explore(origseeds)

# print(residuemap)
# print(leveltoresid)

for level,residues in leveltoresid.items():
    if level == "1":
        continue
    # print(level," ".join(residues))
    allenz = set([e[0] for s in residues for e in gt.get_enzymes(s) ])
    allrules = set((r[0],r[1],residuemap.get(r[2]),r[3]) for s in residues for r in gt.get_rules(s))
    good = [ True ] * len(residues)
    for i,s in enumerate(residues):
        if s not in ("","-") and set(t[0] for t in gt.get_enzymes(s)) != allenz:
            good[i] = False
            continue
        if s not in ("","-") and set((r[0],r[1],residuemap.get(r[2]),r[3]) for r in gt.get_rules(s)) != allrules:
            good[i] = False
            continue
    # print(residues)
    # print(good)
    # print(allenz)
    # print(allrules)
    seedsanot = [ s if good[i] else "%s*"%(s,) for i,s in enumerate(residues) ]
    edge = gt.get_toedge(list(filter(lambda r: r not in ("","-"),residues))[0])
    line = [ "%- 25s"%(level,), "%- 15s"%("%s %s %s %s"%edge,) if edge else " "*15 ] + [ "%- 10s"%(s,) for s in seedsanot ]
    print(" ".join(map(str,line)))

