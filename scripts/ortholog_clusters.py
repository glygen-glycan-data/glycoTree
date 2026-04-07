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
parser.add_argument('--problems',action = 'store_true',default = False,help = 'Output clusters with problems')
parser.add_argument('--clean',action = 'store_true',default = False,help = 'Output clusters with no problems')
parser.add_argument('--delim',type=str,default=None,help='Output field delimitor.')

args = parser.parse_args()

assert args.glycotree or args.glygen or args.genename
assert not args.glygen or not args.nomgi or not args.nooma

species = SpeciesTable()
taxids = [ species.taxid(sp) for sp in args.species ]
species.filter(lambda r: r['taxid'] in taxids)

enzymes = Enzymes()
enzymes.filter(lambda r: r['taxid'] in taxids)
goodup = set(enzymes.distinct('uniprot'))

ggpm = GlyGenProteinMasterlist(species)
ggpm.filter(lambda r: r['taxid'] in taxids)
masterup = set(ggpm.distinct('uniprot'))

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
clusters.mark_notmasterlist(masterup)
clusters.compute_widths()

goodgk = set()
for gk,cls in clusters.groupby('genekey'):
    # print(gk)
    complete = None
    for cl in cls:
        if cl['complete'] and cl['consistent']:
            complete = clusters.asset(cl)
            # print(complete)
            break
    good = False
    if complete:
        good = True
        for cl in cls:
            if not (clusters.asset(cl) <= complete):
                # print(clusters.asset(cl))
                good = False
                break
    # if good:
    #     sources = [cl['source'] for cl in cls]
    #     if len(set(sources)) != len(sources):
    #         good = False
    if good:
        # print(gk)
        goodgk.add(gk)

lastkey = None
for gk,cls in clusters.groupby('genekey'):
    if args.problems and gk in goodgk:
        continue
    if args.clean and gk not in goodgk:
        continue
    if sorted(cls,key=lambda cl: (cl['source']!='gt',cl['source']))[0]['source'] != 'gt':
        continue
    print(">"+gk)
    print("  ",clusters.headerstr(sep=args.delim))
    for cl in sorted(cls,key=lambda cl: (cl['source']!='gt',cl['source'])):
        if cl['source'] == 'gn' and cl['clustid'] != gk:
            continue
        print("  ",clusters.rowstr(cl,sep=args.delim))
    print()
