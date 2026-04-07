#!/bin/env python3.12

from collections import defaultdict
import csv, sys

from annotable import *

species = SpeciesTable()
enzymes = Enzymes()
ggpml = GlyGenProteinMasterlist()
gggn = GlyGenGeneName()

print(ggpml)

clusters = ClusterTable(*species.distinct('taxid'))
clusters.add_clusters(enzymes,'orthology_group')
clusters.add_clusters(GlyGenHomoClusters(),'homolog_cluster_id')
clusters.index_by_uniprot()

for up in clusters.alluniprot():
    if not ggpml.any(up):
        for cl in clusters.byuniprot(up):
            print("Bad UniProt accession %s: "%(up,),clusters.tostr(cl))

enzmap = EnzymeMapping()
print(enzmap)
ruledata = EnzymeRuleData()
print(ruledata) 

headers = "instance        rule_id focus   enzyme  other_residue   polymer taxonomy        proposer_id     refs    comment status  administrator   disputer_id".split()

for ogrp in clusters:
    rids = defaultdict(lambda : defaultdict(set))
    for taxid in clusters[ogrp]:
        for up in clusters[ogrp][taxid]:
            for inst in torule[up]:
                rids[rules[inst]['focus']][taxid].add(inst)
    for rid in rids:
        for taxid in clusters[ogrp]:
            for up in clusters[ogrp][taxid]:
                if (up,rid) not in rulekey:
                    rulng = dict(rules[list(rids[rid]['10090'])[0]].items())
                    rulng['instance'] = nextinst
                    nextinst += 1
                    mapng['uniprot'] = up
                    mapng['proposer_id'] = 'NE'
                    mapng['administrator'] = 'NE'
                    mapng['disputer_id'] = ''
                    # print(up,taxid,rid,map)
                    print(",".join(map(str,map(mapng.get,headers))))
