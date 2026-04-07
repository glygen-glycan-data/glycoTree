#!/bin/env python3.12

from collections import defaultdict
import csv

from pygly.GlycanResource import GlyGenWS
from pygly.GlycanResource import GlycoTreeSandboxDev

speciesmap = dict(map(lambda l: map(str.strip,l.split('\t')),filter(None,"""
Homo sapiens	9606
Mus musculus	10090
Rattus norvegicus	10116
""".splitlines())))
print(speciesmap)
validtaxids = set(speciesmap.values())

ggws = GlyGenWS(verbose=True)
sandbox = GlycoTreeSandboxDev(local=True,verbose=True)

up2gn = dict()
up2geneid = defaultdict(set)
up2rsnp = defaultdict(set)
gts = set()
for spec in ("human","mouse","rat","fruitfly"):
    for row in ggws.protein_genenames(spec):
        upacc = row['uniprotkb_canonical_ac'].split('-')[0]
        up2gn[upacc] = row['gene_symbol_recommended']
    for row in ggws.glycosyltransferases(spec):
        upacc = row['uniprotkb_canonical_ac'].split('-')[0]
        gts.add(upacc)
    for row in ggws.geneid(spec):
        upacc = row['uniprotkb_canonical_ac'].split('-')[0]
        up2geneid[upacc].add(row['xref_id'])
    for row in ggws.refseqnp(spec):
        upacc = row['uniprotkb_canonical_ac'].split('-')[0]
        up2rsnp[upacc].add(row['xref_id'])

clusters = defaultdict(lambda: defaultdict(set))
tocluster = defaultdict(set)
for row in ggws.protein_homolog_clusters():
    clid = row['homolog_cluster_id']
    upacc = row['uniprotkb_canonical_ac']
    upacc = upacc.split('-')[0]
    taxid = row['tax_id']
    clusters[clid][taxid].add(upacc)
    tocluster[upacc].add(clid)

tokeep = set()
for row in csv.DictReader(open('../model/enzymes.csv')):
    upacc = row['uniprot']
    genename = row['gene_name']
    taxid = speciesmap[row['species']]
    ogrp = row['orthology_group']
    if upacc not in gts:
        pass #print("Bad enzyme row",row)
    clusters[ogrp][taxid].add(upacc)
    tocluster[upacc].add(ogrp)
    # up2gn[upacc] = genename
    tokeep.add(upacc)

cltokeep = set()
for upacc in tokeep:
    for clid in tocluster[upacc]:
        cltokeep.add(clid)

for clid in list(clusters):
    if clid not in cltokeep:
        del clusters[clid]

def clkey(clid):
    gns = set()
    for taxid in clusters[clid]:
        if taxid not in validtaxids:
            continue
        gns.update(map(lambda up: up2gn.get(up,"").lower(),clusters[clid][taxid]))
    if "" in gns:
        gns.remove("")
    # if len(gns) > 1:
    #     print(clid,gns)
    #     print(clusters[clid])
    return min(gns)

key2cl = defaultdict(set)
lastk = None
for clid in sorted(clusters,key=clkey):
    # if '9606' not in clusters[clid] and '10090' not in clusters[clid]:
    #     continue
    # if '10116' not in clusters[clid]:
    #     continue
    k = clkey(clid)
    if k != lastk:
        print("** "+k+" **")
        lastk = k
    print(" ",clid+":")
    # for taxid in sorted(clusters[clid],key=int):
    for taxid in ('9606','10090','10116'):
         if taxid in ('9606','10116','10090'):
             print("   ",taxid,end="")
             for upacc in clusters[clid][taxid]:
                 print(" ",upacc,up2gn.get(upacc,"-"),end="")
             print()
         key = tuple([",".join(map(lambda upacc: "%s:%s"%(upacc,up2gn.get(upacc,"-")),sorted(clusters[clid].get(taxid,[])))) for taxid in ('9606','10090','10116')])
         key2cl[key].add(clid)

goodkeys = set()
up2key = defaultdict(set)
for key in key2cl:
    if key[2] == "":
        continue
    if tuple(list(key[0:2])+[""]) not in key2cl:
        continue
    if ',' in key[0] or ',' in key[1] or ',' in key[2]:
        continue
    if "" in key:
        continue
    if len(set([ki.split(':')[1].lower() for ki in key])) != 1:
        continue
    for upacc in [ki.split(':')[0] for ki in key]:
        up2key[upacc].add(key)
    goodkeys.add(key)

for upacc in up2key:
    if len(up2key[upacc]) > 1:
        for key in up2key[upacc]:
            if key in goodkeys:
                goodkeys.remove(key)

# GT,Eogt,Q8BYW9,NP_780522,NM_175313,Eogt,101351,Mus musculus,

for key in goodkeys:
    acc = key[2].split(':')[0]
    if acc in tokeep:
        continue
    if len(up2rsnp.get(acc,[])) != 1:
        rsnp = ""
    else:
        rsnp = up2rsnp[acc].pop()
    if len(up2geneid.get(acc,[])) != 1:
        geneid = ""
    else:
        geneid = up2geneid[acc].pop()
    print(",".join(["GT",up2gn[acc],acc,rsnp,"",up2gn[acc],geneid,"Rattus norvegicus",""]))
     
    
