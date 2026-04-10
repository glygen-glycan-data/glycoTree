#!/bin/env python3.12

from annotable import *
import argparse

parser = argparse.ArgumentParser(description="Ortholog Cluster browser")

parser.add_argument('--species',type=str,required=True,nargs="+",help = 'Taxonomy ids to analyze')
parser.add_argument('--glycotree',action = 'store_true',default = False,help = 'Include GlycoTree orthologs')
parser.add_argument('--glygen',action = 'store_true',default = False,help = 'Include GlyGen orthologs')
parser.add_argument('--genename',action = 'store_true',default = False,help = 'Include gene name orthologs')
parser.add_argument('--nomgi',action = 'store_true',default = False,help = 'Exclude MGI (GlyGen) orthologs')
parser.add_argument('--nooma',action = 'store_true',default = False,help = 'Exclude OMA (GlyGen) orthologs')

args = parser.parse_args()

assert args.glycotree or args.glygen or args.genename
assert not args.glygen or not args.nomgi or not args.nooma

species = SpeciesTable()
taxids = [ species.taxid(sp) for sp in args.species ]
species.filter(lambda r: r['taxid'] in taxids)

enzymes = Enzymes()
enzymes.filter(lambda r: r['taxid'] in taxids)
goodup = set(enzymes.distinct('uniprot'))

gggn = GlyGenGeneName()
gggi = GlyGenGeneID()

clusters = ClusterTable(species)
if args.glycotree:
    clusters.add_clusters(enzymes,'orthology_group',source='gt')

if args.glygen:
    gghc = GlyGenHomoClusters(species=species)
    goodcl = set()
    for cl in gghc:
        if cl['uniprot'] in goodup:
            goodcl.add(cl['homolog_cluster_id'])
    gghc.filter(lambda cl: (cl['homolog_cluster_id'] in goodcl))
    if args.nomgi:
        gghc.filter(lambda cl: (cl['source'] not in ('mgi',)))
    if args.nooma:
        gghc.filter(lambda cl: (cl['source'] not in ('oma',)))
    clusters.add_clusters(gghc,'homolog_cluster_id')

if args.genename:
    gggnc = GlyGenGeneNameClusters(species=species)
    goodcl = set()
    for cl in gggnc:
        if cl['uniprot'] in goodup:
            goodcl.add(cl['clustid'])
    gggnc.filter(lambda cl: (cl['clustid'] in goodcl))
    clusters.add_clusters(gggnc,'clustid',source='gn')

clusters.mark_multi_genekey()
clusters.compute_widths()

def initialize(s):
    return s[0].upper() + s[1:]

missing = []
for gk,cls in clusters.groupby('genekey'):
    gtcl = None
    united=defaultdict(set)
    for cl in cls:
        if cl['source'] == 'gt':
            gtcl = cl
            continue
        for h in clusters.uniprot_headers:
            if cl[h]:
                united[h].update(map(lambda s: s.strip("*"),cl[h].split(',')))
    if gtcl is None:
        continue
    for h in clusters.uniprot_headers:
        if not gtcl.get(h) and united[h]:
            spec = species.sciname(h.split(':')[1])
            for upacc in united[h]:
                try:
                    gi=gggi.geneid(upacc)
                except KeyError:
                    gi = ""
                for gn in set(gggn.genenames(upacc)):
                    row = dict(orthology_group=initialize(gk),uniprot=upacc,gene_name=gn,gene_id=gi,species=spec,type="GT")
                    try:
                        rid = enzymes.addrow(row)
                        missing.append(rid)
                    except KeyError:
                        pass
                    

# type,orthology_group,uniprot,protein_refseq,dna_refseq,gene_name,gene_id,species
for rid in missing:
    # print(enzymes.get(rid))
    print(enzymes.origrowstr(enzymes.get(rid),sep=","))
