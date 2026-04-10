#!/bin/env python3.12

from annotable import *
import argparse

parser = argparse.ArgumentParser(description="Ortholog Cluster browser")
parser.add_argument('-e','--enzyme_mapping',action = 'store_true',default = False,help = 'Output enzyme mapping rows')
parser.add_argument('-r','--rule_data',action = 'store_true',default = False,help = 'Output rule data rows')
args = parser.parse_args()

assert args.enzyme_mapping or args.rule_data

enzymes = Enzymes()
taxids = set(enzymes.distinct('taxid'))

species = SpeciesTable()
species.filter(lambda r: r['taxid'] in taxids)

mapping = EnzymeMapping()
ruledata = RuleData()

clusters = ClusterTable(species)
clusters.add_clusters(enzymes,'orthology_group',source='gt')

# for gk,cls in clusters.groupby('genekey'):
#     print(">"+gk)
#     print("  ",clusters.headerstr())
#     for cl in sorted(cls,key=lambda cl: (cl['source']!='gt',cl['source'])):
#         print("  ",clusters.rowstr(cl))
#     print()

for gk,cls in clusters.groupby('genekey'):
    assert len(cls) == 1, cls
    maps = set()
    rules = set()
    for h in clusters.uniprot_headers:
        if args.enzyme_mapping:
            maps.update(mapping.allid(cls[0][h]))
        if args.rule_data:
            rules.update(ruledata.allid(cls[0][h]))
    for h in clusters.uniprot_headers:
        if not cls[0][h]:
            continue
        upaccs = cls[0][h].split(',')
        taxid = h.split(':')[1]
        sciname = species.sciname(taxid)
        for mi in maps:
            for upacc in upaccs:
                try:
                    newrow = mapping.clone(mi,uniprot=upacc,proposer_id='NE',administrator='NE')
                    rowid = mapping.addrow(newrow)
                    print(mapping.rowstr(mapping.get(rowid),sep=","))
                except KeyError as e:
                    pass # print(e)
        for ri in rules:
            for upacc in upaccs:
                try:
                    newrow = ruledata.clone(ri,enzyme=upacc,taxonomy=sciname,proposer_id='NE',administrator='NE')
                    rowid = ruledata.addrow(newrow)
                    print(ruledata.rowstr(ruledata.get(rowid),sep="\t"))
                except KeyError as e:
                    pass # print(e)

