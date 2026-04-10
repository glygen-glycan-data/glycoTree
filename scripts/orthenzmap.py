#!/bin/env python3.12

from collections import defaultdict
import csv, sys

speciesmap = dict(map(lambda l: map(str.strip,l.split('\t')),filter(None,"""
Homo sapiens	9606
Mus musculus	10090
Rattus norvegicus	10116
""".splitlines())))
print(speciesmap)

clusters = defaultdict(lambda: defaultdict(set))
tocluster = defaultdict(set)
for row in csv.DictReader(open('../model/enzymes.csv')):
    upacc = row['uniprot']
    taxid = speciesmap[row['species']]
    ogrp = row['orthology_group']
    clusters[ogrp][taxid].add(upacc)
    tocluster[upacc].add(ogrp)

mappings = dict()
tomapping = defaultdict(set)
mapkey = set()
for row in csv.DictReader(open('../model/enzyme_mappings.csv')):
    row['instance'] = int(row['instance'])
    mappings[row['instance']] = dict(row.items())
    tomapping[row['uniprot']].add(row['instance'])
    mapkey.add((row['uniprot'],row['residue_id']))

nextinst = max(mappings)+1

headers = "instance,residue_name,residue_id,type,uniprot,notes,status,proposer_id,administrator,disputer_id".split(",")

for ogrp in clusters:
    rids = defaultdict(lambda : defaultdict(set))
    for taxid in clusters[ogrp]:
        for up in clusters[ogrp][taxid]:
            for inst in tomapping[up]:
                rids[mappings[inst]['residue_id']][taxid].add(inst)
    for rid in rids:
        for taxid in clusters[ogrp]:
            for up in clusters[ogrp][taxid]:
                if (up,rid) not in mapkey:
                    mapng = dict(mappings[list(rids[rid]['10090'])[0]].items())
                    mapng['instance'] = nextinst
                    nextinst += 1
                    mapng['uniprot'] = up
                    mapng['proposer_id'] = 'NE'
                    mapng['administrator'] = 'NE'
                    mapng['disputer_id'] = ''
                    # print(up,taxid,rid,map)
                    print(",".join(map(str,map(mapng.get,headers))))
