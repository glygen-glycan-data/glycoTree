#!/bin/env python3

import sys, csv
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

# rule_data.tsv
for r in csv.DictReader(open(sys.argv[5]),dialect='excel-tab'):
    resid = r['focus']
    ruleid = int(r['rule_id'])
    status = r['status']
    if status == 'active' and 4 <= ruleid <= 7:
        abioticresidues.add(resid)

# enzymes.csv
enzyme2species = dict()
for r in csv.DictReader(open(sys.argv[3])):
    enzid = r['enzyme_id']
    sp = r['species']
    enzyme2species[enzid] = sp

# enzyme_mappings.csv
hasenzyme = defaultdict(set)
for r in csv.DictReader(open(sys.argv[4])):
    resid = r['residue_id']
    enzid = r['enzyme_id']
    sp = enzyme2species[enzid]
    hasenzyme[resid].add(sp)

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
    if r['residue_id'] not in hasenzyme or len(hasenzyme[r['residue_id']]) == 0:
        data[acc]['nothasenzymes'] = True
    for sciname,shortname in species:
        if sciname not in hasenzyme[r['residue_id']]:
            data[acc]['nothasenzymes_'+shortname] = True
    if not data[acc]['residues']:
        data[acc]['residues'] = 0
    if 'composition' not in data[acc]:
        data[acc]['composition'] = defaultdict(int)
    data[acc]['residues'] += 1
    data[acc]['composition'][r['name']] += 1

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
print(",".join(headers))
for acc in sorted(data):
    if data[acc].get('tree') == None:
        continue
    print(",".join([acc,data[acc]['tree']] + [ str(data[acc][h]*1) for h in headers[2:] ]))

