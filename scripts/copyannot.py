#!/bin/env python3

import os, os.path, sys, csv, copy, shutil

enzymemap = 'model/enzyme_mappings.csv'
ruledata = 'model/rule_data.tsv'

if not os.path.exists(enzymemap):
    print("No enzyme mappings file.")
    sys.exit(1)

if not os.path.exists(ruledata):
    print("No rule data file.")
    sys.exit(1)

fromresid = sys.argv[1].split(":")
toresids = list(map(lambda a: a.split(":"),sys.argv[2:]))
for trid in toresids:
    assert(len(trid) == len(fromresid))

maxenzid = 0
enzymeheaders = None
enzymerows = []
rows = csv.DictReader(open(enzymemap))
for r in rows:
    if not enzymeheaders:
        enzymeheaders = rows.fieldnames
    if maxenzid < int(r['instance']):
        maxenzid = int(r['instance'])
    if r['residue_id'] == fromresid[0]:
        enzymerows.append(copy.copy(r))
 
maxruleid = 0
ruleheaders = None
rulerows = []
rows = csv.DictReader(open(ruledata),dialect='excel-tab')
for r in rows:
    if not ruleheaders:
        ruleheaders = rows.fieldnames
    if maxruleid < int(r['instance']):
        maxruleid = int(r['instance'])
    if r['focus'] == fromresid[0] and r['other_residue'] != 'NULL' and r['other_residue'] not in fromresid[1:]:
        print("need other residue(s)",file=sys.stderr)
        sys.exit(1)
    if r['focus'] == fromresid[0]:
        rulerows.append(copy.copy(r))

shutil.copy(enzymemap,enzymemap+".orig")
wh = open(enzymemap,'a')
for resid in toresids:
    for r in enzymerows:
        r['residue_id'] = resid[0]
        r['residue_name'] = r['residue_name'].rsplit('_',1)[0] + "_" + resid[0][1:]
        maxenzid += 1
        r['instance'] = str(maxenzid)
        wh.write(",".join(map(r.get,enzymeheaders)) + "\n")
wh.close()

shutil.copy(ruledata,ruledata+".orig")
wh = open(ruledata,'a')
for resid in toresids:
    for r in rulerows:
        r['focus'] = resid[0]
        maxruleid += 1
        r['instance'] = str(maxruleid)
        if r['other_residue'] != 'NULL':
            ind = fromresid.index(r['other_residue'])
            r['other_residue'] = resid[ind]
        wh.write("\t".join(map(r.get,ruleheaders)) + "\n")
wh.close()
