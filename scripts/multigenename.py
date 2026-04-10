#!/bin/env python3.12

from annotable import *
from collections import defaultdict

species = SpeciesTable()
species.filter(lambda r: r['taxid'] in (9606,10090,10116))

enzymes = Enzymes()
enzymes.filter(lambda r: r['taxid'] in (9606,10090,10116))
goodgn = set(enzymes.distinct('gene_name'))

gggn = GlyGenGeneName(species=species)
gggn.filter(lambda r: r['gene_symbol_recommended'] in goodgn)

gn2up = defaultdict(set)
for r in gggn:
   gn2up[(r['gene_symbol_recommended'],r['taxid'])].add(r['uniprot'])

for gn in gn2up:
    print(":".join(map(str,gn)),",".join(sorted(gn2up[gn])))

for r in gggn:
    if r['gene_symbol_recommended'] == 'Abo' and r['taxid'] == 10116:
        print(r)
