#!/bin/env python3

import os, sys, json
from collections import defaultdict
import urllib.request

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
    retval = defaultdict(str)
    retval['geneid'] = str(gid)
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

def uvals(s):
    lvals = list(reversed(s.split(';')))
    return sorted(set(lvals),key=lambda v: -lvals.index(v))

def testup(s):
    try:
        h = urllib.request.urlopen('https://rest.uniprot.org/uniprotkb/%s.json'%(s,))
        data = json.loads(h.read())
        return data['primaryAccession'] == s
    except IOError:
        pass # print(s,'IOError')
    return False

for mmgeneid in sys.argv[1:]:
    mmvals = getgene(mmgeneid)
    mmvals['orthname'] = mmvals['genename']
    if 'orthgeneid' in mmvals:
        hsvals = getgene(mmvals['orthgeneid'])
        hsvals['orthname'] = mmvals['genename']
    else:
        hsvals = None

     
    mmvals['upprot'] = ";".join(filter(testup,uvals(mmvals['upprot'])))
    hsvals['upprot'] = ";".join(filter(testup,uvals(hsvals['upprot'])))

    mmbang = False
    hsbang = False
    for k in ('orthname','upprot','rsprot','rsrna','genename','geneid'):
        if not mmvals.get(k):
            mmvals[k] = ""
            mmbang = True
        elif len(uvals(mmvals[k])) > 1:
            mmvals[k] = uvals(mmvals[k])[-1]
            mmbang = True
        else:
            mmvals[k] = uvals(mmvals[k])[-1]
        if not hsvals.get(k):
            hsvals[k] = ""
            hsbang = True
        elif len(uvals(hsvals[k])) > 1:
            hsvals[k] = uvals(hsvals[k])[-1]
            hsbang = True
        else:
            hsvals[k] = uvals(hsvals[k])[-1]
    if mmbang:
        mmvals['bang'] = 'Ambiguous transcript or protein accession'
    if hsbang:
        hsvals['bang'] = 'Ambiguous transcript or protein accession'
    print("GT,%(orthname)s,%(upprot)s,%(rsprot)s,%(rsrna)s,%(genename)s,%(geneid)s,Mus musculus,%(bang)s"%mmvals),
    if hsvals != None:
        print("GT,%(orthname)s,%(upprot)s,%(rsprot)s,%(rsrna)s,%(genename)s,%(geneid)s,Homo sapiens,%(bang)s"%hsvals),
