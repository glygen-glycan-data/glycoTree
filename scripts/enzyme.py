#!/bin/env python3

import os, sys, json
mmgeneid = sys.argv[1]

def findvalue(d,vin,path=[]):
  if hasattr(d,'keys'):
      for k,v in d.items():
          if isinstance(v,str):
              if v == vin:
                  print(".".join(path + [k]) + ": " + vin)
          else:
              findvalue(v,vin,path+[k])
  else:
      for i,item in enumerate(d):
          if isinstance(item,str):
              if item == vin:
                  print(".".join(path + [k]) + ": " + vin)
          else:
              findvalue(item,vin,path+[str(i)])
 

from Bio import Entrez
Entrez.email = 'nje5@georgetown.edu'



def getgene(gid):
    retval = dict(geneid=str(gid))
    handle = Entrez.efetch(db='gene',id=gid,retmode='xml')
    data = Entrez.read(handle)
    # findvalue(data[0],"A4gnt")
    # findvalue(data[0],"Q14BT6")
    retval['genename'] = data[0]['Entrezgene_gene']['Gene-ref']['Gene-ref_locus']
    comments = data[0]['Entrezgene_comments']
    for comment in comments:
        if comment['Gene-commentary_type'] == "23":
            for comment1 in comment.get('Gene-commentary_comment',[]):
                for source in comment1.get('Gene-commentary_source',[]):
                    if source.get('Other-source_anchor') in ('human','mouse'):
                        if source['Other-source_src']['Dbtag']['Dbtag_tag']['Object-id']['Object-id_id'] != gid:
                            retval['orthgeneid'] = source['Other-source_src']['Dbtag']['Dbtag_tag']['Object-id']['Object-id_id']
        if comment.get('Gene-commentary_heading') == "NCBI Reference Sequences (RefSeq)":
            for comment1 in comment.get('Gene-commentary_comment',[]):
                if comment1.get('Gene-commentary_heading') != 'RefSeqs maintained independently of Annotated Genomes':
                    continue
                for product in comment1['Gene-commentary_products']:
                    if product.get('Gene-commentary_heading') == 'mRNA Sequence':
                        if 'rsrna' not in retval:
                            retval['rsrna'] = product['Gene-commentary_accession']
                        else:
                            retval['rsrna'] += ";"+product['Gene-commentary_accession']
                        for product1 in product.get('Gene-commentary_products',[]):
                            if product1.get('Gene-commentary_heading') == 'Product':
                                if 'rsprot' not in retval:
                                    retval['rsprot'] = product1['Gene-commentary_accession']
                                else:
                                    retval['rsprot'] += ";"+product1['Gene-commentary_accession']
                                for comment2 in product1.get('Gene-commentary_comment',[]):
                                    for comment3 in comment2.get('Gene-commentary_comment',[]):
                                         for source1 in comment3.get('Gene-commentary_source',[]):
                                             if source1.get('Other-source_src',{}).get('Dbtag',{}).get('Dbtag_db') == 'UniProtKB/Swiss-Prot':
                                                 if 'upprot' not in retval:
                                                     retval['upprot'] = source1['Other-source_anchor']
                                                 else:
                                                     retval['upprot'] += ";"+source1['Other-source_anchor']
 
    return retval

mmvals = getgene(mmgeneid)
mmvals['orthname'] = mmvals['genename']
if 'orthgeneid' in mmvals:
    hsvals = getgene(mmvals['orthgeneid'])
    hsvals['orthname'] = mmvals['genename']
else:
    hsvals = None

if any([';' in v for v in mmvals.values()]):
    print("!",end="")
print("GT,%(orthname)s,%(upprot)s,%(rsprot)s,%(rsrna)s,%(genename)s,%(geneid)s,Mus musculus,"%mmvals),
if hsvals != None:
    if any([';' in v for v in hsvals.values()]):
        print("!",end="")
    print("GT,%(orthname)s,%(upprot)s,%(rsprot)s,%(rsrna)s,%(genename)s,%(geneid)s,Homo sapiens,"%hsvals),
