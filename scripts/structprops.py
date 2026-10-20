#!/bin/env python3

import sys, csv, gzip
from collections import defaultdict

abioticresidues = set()

# species
species_table = """
Homo sapiens: human
Mus musculus: mouse
Rattus norvegicus: rat
Sus scrofa: pig
Bos taurus: bovine
Dictyostelium discoideum: dicty
"""

species = []
for l in species_table.splitlines():
    if l.strip():
        sp = tuple(map(str.strip,l.strip().split(': ')))
        if len(sp) == 2:
            species.append(sp)

species_abiotic = defaultdict(set)
# rule_data.tsv
for r in csv.DictReader(open(sys.argv[5]),dialect='excel-tab'):
    resid = r['focus']
    ruleid = int(r['rule_id'])
    status = r['status']
    if status == 'active' and 4 <= ruleid <= 7:
        abioticresidues.add(resid)
    if status == 'active' and ruleid == 8:
        if r['taxonomy'] in ["Fungi","Nematoda","Schistosoma"]:
	    # none of our species are in these taxonomic groups
            abioticresidues.add(resid)
        if r['taxonomy'] in ["plants, insects, and parasitic worms"]:
            # dicty is ok, the others are not, despite this text
            for sciname,shortname in species:
                if shortname == "dicty":
                    continue
                species_abiotic[sciname].add(resid)

# enzymes.csv
enzyme2species = dict()
enzyme2uniprot = dict()
for r in csv.DictReader(open(sys.argv[3])):
    enzid = r['enzyme_id']
    sp = r['species']
    up = r['uniprot']
    enzyme2species[enzid] = sp
    enzyme2uniprot[enzid] = up

# glycotree_annotated_glycans.tsv.gz
gtc2caveat = defaultdict(lambda: defaultdict(set))
for r in csv.DictReader(gzip.open(sys.argv[6],'rt'),dialect='excel-tab'):
    gtcacc = r["glytoucan_ac"]
    up = r["uniprot"]
    caveat = r["caveat"]
    focus = r["residue_id"]
    if caveat.strip() and up.strip():
        gtc2caveat[gtcacc][focus].add(up)

# enzyme_mappings.csv
hasenzyme = defaultdict(set)
hasenzyme1 = defaultdict(lambda: defaultdict(set))
for r in csv.DictReader(open(sys.argv[4])):
    resid = r['residue_id']
    enzid = r['enzyme_id']
    sp = enzyme2species[enzid]
    hasenzyme[resid].add(sp)
    hasenzyme1[resid][sp].add(enzid)

# composition.csv
data = defaultdict(lambda: defaultdict(lambda: False))
for r in csv.DictReader(open(sys.argv[2])):
    acc = r['glytoucan_ac']
    if r['residue_name'] == 'unassigned':
        data[acc]['unmapped'] = True
    if r['notes'] not in ('validated by Qrator','manually validated','Manually validated'):
        data[acc]['unvalidated'] = True
    if r['residue_id'].startswith('N'):
        data[acc]['tree'] = 'N'
    if r['residue_id'].startswith('O'):
        data[acc]['tree'] = 'O'
    if r['residue_id'] in abioticresidues:
        data[acc]['abiotic'] = True
    # print(r['residue_id'],hasenzyme[r['residue_id']])
    if data[acc]['unmapped'] or r['residue_id'] not in hasenzyme or len(hasenzyme[r['residue_id']]) == 0:
        data[acc]['nothasenzymes'] = True
    for sciname,shortname in species:
        if data[acc]['unmapped']:
            data[acc]['nothasenzymes_'+shortname] = True
        else:
            if sciname not in hasenzyme[r['residue_id']]:
                data[acc]['nothasenzymes_'+shortname] = True
    for sciname,shortname in species:
        found = False
        if not data[acc]['abiotic'] and r['residue_id'] not in species_abiotic[sciname] and not data[acc]['unmapped']:
            for enz in hasenzyme1[r['residue_id']][sciname]:
                if enzyme2uniprot[enz] not in gtc2caveat[acc][r['residue_id']]:
                    found = True
                    break
        if not found:
            data[acc]['nothasgoodenzymes_'+shortname] = True

    if not data[acc]['residues']:
        data[acc]['residues'] = 0
    if 'composition' not in data[acc]:
        data[acc]['composition'] = defaultdict(int)
    data[acc]['residues'] += 1
    data[acc]['composition'][r['name']] += 1

    # print(acc,data[acc])

# glygen_allacc.txt
glygenacc = set(open(sys.argv[1]).read().split())
for acc in glygenacc:
    data[acc]['inglygen'] = True

for acc in data:
    if not data[acc]['unmapped']:
        data[acc]['mapped'] = True
    if not data[acc]['unvalidated']:
        data[acc]['validated'] = True
    if not data[acc]['nothasenzymes']:
        data[acc]['hasenzymes'] = True
    for sciname,shortname in species:
        if not data[acc]['nothasenzymes_'+shortname]:
            data[acc]['hasenzymes_'+shortname] = True
        if not data[acc]['nothasgoodenzymes_'+shortname]:
            data[acc]['hasgoodenzymes_'+shortname] = True
    compstr = ""
    if 'composition' in data[acc]:
        for k in "GlcNAc Man Gal Fuc NeuNAc NeuNGc GalNAc Glc GlcA KDN phosphate sulfate Xyl".split():
            v = data[acc]['composition'].get(k,0)
            if v > 0:
                compstr += f"{k}({v})"
    data[acc]['composition'] = compstr

headers = ["glytoucan_ac","tree","abiotic","mapped","validated","hasenzymes","inglygen"]
for sciname,shortname in species:
    headers.append("hasenzymes_"+shortname)
for sciname,shortname in species:
    headers.append("hasgoodenzymes_"+shortname)
print(",".join(headers))
for acc in sorted(data):
    if data[acc].get('tree') == None:
        continue
    print(",".join([acc,data[acc]['tree']] + [ str(data[acc][h]*1) for h in headers[2:] ]))
