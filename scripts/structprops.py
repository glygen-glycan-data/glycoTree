#!/bin/env python3

import sys, csv
from collections import defaultdict

abioticresidues = set()

# rule_data.tsv
for r in csv.DictReader(open(sys.argv[3]),dialect='excel-tab'):
    resid = r['focus']
    ruleid = int(r['rule_id'])
    status = r['status']
    if status == 'active' and 4 <= ruleid <= 7:
        abioticresidues.add(resid)

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

# glygen_allacc.txt
glygenacc = set(open(sys.argv[1]).read().split())
for acc in glygenacc:
    data[acc]['inglygen'] = True

for acc in data:
    if not data[acc]['unmapped']:
        data[acc]['mapped'] = True
    if not data[acc]['unvalidated']:
        data[acc]['validated'] = True

headers = ["glytoucan_ac","tree","abiotic","mapped","validated","hasenzymes","inglygen"]
print(",".join(headers))
for acc in sorted(data):
    if data[acc].get('tree') == None:
        continue
    print(",".join([acc,data[acc]['tree']] + [ str(data[acc][h]*1) for h in headers[2:] ]))

