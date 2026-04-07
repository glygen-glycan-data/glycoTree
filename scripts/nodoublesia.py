#!/bin/env python3.12

import sys

from tree import GlycoTreeDev

from annotable import RuleData

from collections import defaultdict

gt = GlycoTreeDev()
siacluster = defaultdict(set)
rd = RuleData()
badrules = set()
for rule in rd:
    if rule['rule_id'] == '2' and rule['status'] == "proposed" and rule['comment'] == "Steps 3&7, N-Glycan Capping":
        focus = rule['focus']
        other_residue = rule['other_residue']
        edge = gt.get_toedge(other_residue)
        parent = gt.get_parent(focus)
        parent_edge = gt.get_toedge(parent)
        
        if edge[0] == "2" and edge[1] == 'a' and edge[2] == 'L' and gt.get_name(other_residue) == "Fuc" and gt.get_parent(other_residue) == parent and gt.get_name(parent) == "Gal" and parent_edge[0] in ('3','4') and parent_edge[1] == 'b':
            badrules.add(rule['instance'])
        elif edge[0] == "4" and edge[1] == 'b' and gt.get_name(other_residue) == "GalNAc" and gt.get_parent(other_residue) == parent and gt.get_name(parent) == "Gal" and parent_edge[0] == "4" and parent_edge[1] == "b":
            badrules.add(rule['instance'])

# print(len(badrules))
# rd.filter(lambda r: r['instance'] not in badrules)
# print(rd)

# sys.exit(1)

capping_roots = ['N2','N7','N5','N31','N9','O156','O157']
# capping_roots = ['N2','N7','N5','N31','N9']

for rid in gt.all_residues(capping_roots):
    edge = gt.get_toedge(rid)
    # print(rid,edge)
    if edge[0] in ('3','6') and edge[1] in ('a',) and edge[3] in ("NeupNAc","NeupNGc","KDNp"):
        if gt.has_species_enzymes(rid,'Homo sapiens'):
            siacluster[gt.get_parent(rid)].add(rid)

# for prid in siacluster:
#     print("%s:"%(prid,),", ".join(sorted(siacluster[prid])))

template = 466
# print(rt.get(template))
for prid in siacluster:
    for rid in siacluster[prid]:
        ridedge = gt.get_toedge(rid)
        for enz in gt.get_enzymes(rid):
            for rid1edge,rid1 in gt.get_children(prid):
                if rid1 == rid or ridedge[0] == rid1edge[1]:
                    continue
                predge = gt.get_toedge(prid)
                if rid1edge[:3] == ("2","a","L") and gt.get_name(rid1) == "Fuc" and gt.get_name(prid) == "Gal" and predge[0] in ("3","4") and predge[1] == "b":
                    continue
                if rid1edge[:3] == ("4","b","D") and gt.get_name(rid1) == "GalNAc" and gt.get_name(prid) == "Gal" and predge[0] == "4" and predge[1] == "b":
                    continue
                newrow = rd.clone(template,focus=rid,taxonomy=enz[1],enzyme=enz[2],other_residue=rid1)
                try:
                    rowid = rd.addrow(newrow)
                    print(rd.rowstr(rd.get(rowid),sep="\t"))
                except KeyError as e:
                    pass #print(e,file=sys.stderr)
    # break

