
import sys, re, csv, copy
from collections import defaultdict

species_data = """
scientific_name		taxid	glygen_name
Homo sapiens		9606	human
Mus musculus		10090	mouse
Rattus norvegicus	10116	rat
Bos taurus		9913	bovine
Sus scrofa		9823	pig
Drosophila melanogaster	7227	fruitfly
Cricetulus griseus	10029	hamster
"""

class ModelTable(object):
    id_column = '_id'
    indexes = []
    transform = {}
    unique = []
    filename = None
    data = None
    notnull = []
    nulls = ["NULL",None,"None",""]

    def __init__(self,filename=None,headers=None,rows=None):
        self._table = {} 
        self._maxid = -1
        self._headers = headers
        self._orig_headers = headers
        self._unique = set()
        self._width = defaultdict(lambda: 6)
        if filename is None and self.filename is None:
            if rows is not None:
                self.parse(rows)
            elif self.data is not None:
                self.parse(self.data_to_rows())
        else:
            if not filename:
                filename = self.filename
            if filename.endswith('.tsv'):
                self.parse(csv.DictReader(open(filename),dialect='excel-tab'))
                self._sep = '\t'
            elif filename.endswith('.csv'):
                self.parse(csv.DictReader(open(filename)))
                self._sep = ','
            else:
                raise RuntimeError("Bad file type")
        self.index()

    def data_to_rows(self):
        headers = None
        for line in self.data.splitlines():
            sl = [ s.strip() for s in re.split(r'\t+',line) ]
            if len(sl) <= 1:
                continue
            if not headers:
                headers = sl
                continue
            yield dict(zip(headers,sl))

    def addrows(self,rows):
        for r in rows:
            self.addrow(r)

    def addrow(self,r):
        for k in self.notnull:
            if k not in r or r[k] in self.nulls:
                return
        if self.id_column not in r:
            r[self.id_column] = self._maxid+1
        else:
            r[self.id_column] = int(r[self.id_column])
        for k,v in self.transform.items():
            if v is None:
                if k in self._headers:
                    self._headers.remove(k)
                if k in r:
                    del r[k]
                continue
            r[k] = v[0](*(map(r.get,v[1:])))
            if k not in self._headers:
                self._headers.append(k)
        for i,key in enumerate([(self.id_column,)] + self.unique):
            keyvalue = tuple([i,*map(r.get,key)])
            if keyvalue in self._unique:
                raise KeyError("%s=%s not unique in table"%(tuple(key),keyvalue[1:]))
        for i,key in enumerate([(self.id_column,)] + self.unique):
            keyvalue = tuple([i,*map(r.get,key)])
            self._unique.add(keyvalue)
        for k,v in r.items():
            self._width[k] = max(self._width[k],len(str(k)),len(str(v)))
        self._table[r[self.id_column]] = dict(r.items())
        self._maxid = max(self._maxid,r[self.id_column])
        return r[self.id_column]

    def parse(self,rows):
        for r in rows:
            if not self._headers:
                self._headers = list(r.keys())
                self._orig_headers = list(self._headers)
            self.addrow(r)

    def uniq(self,unique=[]):
        self._unique = set()
        for r in self._table.values():
            for i,key in enumerate(set([(self.id_column,)] + self.unique + unique)):
                keyvalue = tuple([i,*map(r.get,key)])
                assert keyvalue not in self._unique, "%s=%s not unique in table"%(tuple(key),keyvalue[1:])
                self._unique.add(keyvalue)

    def index(self,indexes=[]):
        self._index = defaultdict(set)
        for r in self._table.values():
            for ind in set(indexes + self.indexes + self.unique):
                key = tuple(map(r.get,ind))
                self._index[*key].add(r[self.id_column])

    def get(self,i):
        return self._table[i]

    def clone(self,i,**kw):
        d = copy.copy(self._table[i])
        del d[self.id_column]
        d.update(kw)
        return d

    def count(self):
        return len(self._table.keys())

    def any(self,*key):
        for r in self._index.get(key,[]):
            return True
        return False

    def one(self,*key):
        value = None
        for i,ind in enumerate(self._index.get(key,[])):
            if value != None:
                raise KeyError("Key %s not unique"%(key,))
            value = self._table[ind]
            if i == 1:
                break
        if value == None:
            raise KeyError("Key %s does not exist"%(key,))
        return value

    def allid(self,*key):
        for ind in self._index.get(key,[]):
            yield ind

    def all(self,*key):
        for ind in self._index.get(key,[]):
            yield self._table[ind]

    def filter(self,func):
        for i in list(self._table):
            if not func(self._table[i]):
                del self._table[i]
        self.uniq()
        self.index()
        self.compute_widths()

    def compute_widths(self):
        self._width = defaultdict(lambda: 6)
        for row in self:
            for k,v in row.items():
                self._width[k] = max(self._width[k],len(str(k)),len(str(v)))

    def distinct(self,*key):
        seen = set()
        for r in self:
            keyvalue = tuple(map(r.get,key))
            if keyvalue not in seen:
                if len(key) == 1:
                    yield keyvalue[0]
                else:
                    yield keyvalue
                seen.add(keyvalue)

    def groupby(self,*key):
        thegroup = None
        rows = None
        for r in sorted(self,key=lambda r: tuple(map(r.get,key))):
            grp = tuple(map(r.get,key))
            if grp != thegroup:
                if rows is not None:
                    if len(thegroup) == 1:
                        yield thegroup[0],rows
                    else:
                        yield thegroup,rows
                rows = []
                thegroup = grp
            rows.append(r)
        if rows is not None:
            if len(thegroup) == 1:
                yield thegroup[0],rows
            else:
                yield thegroup,rows

    def __iter__(self):
        for r in sorted(self._table.values(),key=lambda r: r[self.id_column]):
            yield r
    
    def headerstr(self,sep=None):
        if sep is None:
            return "  ".join([ "%-*s"%(self._width[h],h) for h in self._headers ])
        return sep.join([ str(h) for h in self._headers ])


    def rowstr(self,r,sep=None):
        if sep is None:
            return "  ".join([ "%-*s"%(self._width[h],r.get(h,"")) for h in self._headers ])
        return sep.join([ str(r.get(h,"")) for h in self._headers ])

    def origrowstr(self,r,sep=None):
        if sep is None:
            return "  ".join([ "%-*s"%(self._width[h],r.get(h,"")) for h in self._orig_headers ])
        return sep.join([ str(r.get(h,"")) for h in self._orig_headers ])

    def head(self,n=10,sep=None):
        retval = [ self.headerstr(sep=sep) ]
        for r in sorted(self._table.values(),key=lambda r: r[self.id_column])[:n]:
            retval.append(self.rowstr(r,sep=sep))
        return "\n".join(retval)

    def tostr(self,sep=None):
        retval = [ self.headerstr(sep=sep) ]
        for r in sorted(self._table.values(),key=lambda r: r[self.id_column]):
            retval.append(self.rowstr(r,sep=sep))
        return "\n".join(retval)

    def __str__(self):
        return self.tostr(sep=self._sep)

class SpeciesTable(ModelTable):
    data = species_data 
    indexes = [('scientific_name',),('glygen_name',),('taxid',),('strtaxid',)]
    unique = [('scientific_name',),('taxid',),('glygen_name',)]
    transform = {'strtaxid': (str,'taxid'), 'taxid': (int,'taxid')}

    def taxid(self,name):
        return self.one(name)['taxid']

    def sciname(self,taxid):
        return self.one(taxid)['scientific_name']

species_table = SpeciesTable()

class Enzymes(ModelTable):
    filename = '../model/enzymes.csv'
    transform = {'taxid': (species_table.taxid,'species')}
    unique = [ ('uniprot',) ]

class ClusterTable(ModelTable):
    unique = [ ('clustid',) ]

    @staticmethod
    def tohdr(tid,key):
        return key+str(tid)
    upkey = lambda self,tid: self.tohdr(tid,"up:")
    gnkey = lambda self,tid: self.tohdr(tid,"gn:")

    def __init__(self,species=None):
        if not species:
            species = SpeciesTable()
        self.taxids = list(species.distinct('taxid'))
        headers = [ "clustid" ]
        self.uniprot_headers = []
        self.genename_headers = []
        self.gks = defaultdict(set)
        self.insert_headers = 1
        for tid in self.taxids:
            key = self.upkey(tid)
            headers.append(key)
            self.uniprot_headers.append(key)
            key = self.gnkey(tid)
            headers.append(key)
            self.genename_headers.append(key)
        self.gggn = GlyGenGeneName(species=species)
        super().__init__(headers=headers)

    def add_to_headers(self,**kw):
        for key in kw:
            if kw.get(key) not in self.nulls and key not in self._headers:
                self._headers.insert(self.insert_headers,key)
                self.insert_headers += 1

    def add_clusters(self,table,orthkey,source=None,**kw):
        self.add_to_headers(source=source,**kw)
        for grp,rows in table.groupby(orthkey):
            toadd = dict(clustid=grp,**kw)
            if source:
                toadd['source'] = set([source])
            cnt = 0
            for r in rows:
                tid = r['taxid']
                if tid not in self.taxids:
                    continue
                up = r['uniprot']
                gn1 = r.get('gene_name')
                gn2 = set(self.gggn.genenames(up))
                if gn1 and gn2 and gn1 not in gn2:
                    # print(r,gn1,gn2,file=sys.stderr)
                    # assert gn1 == gn2, "Gene name mismatch? %s != %s"%(gn1,gn2)
                    # print("Warning: Gene name mismatch? %s != %s"%(gn1,gn2))
                    pass
                gn = set([gn1]) if gn1 else gn2
                if 'source' in r:
                    self.add_to_headers(source=r['source'])
                    if 'source' not in toadd:
                        toadd['source'] = set([r['source']])
                    else:
                        toadd['source'].add(r['source'])
                if self.upkey(tid) not in toadd:
                    toadd[self.upkey(tid)] = set([up])
                else:
                    toadd[self.upkey(tid)].add(up)
                if self.gnkey(tid) not in toadd:
                    toadd[self.gnkey(tid)] = gn
                else:
                    toadd[self.gnkey(tid)].update(gn)
                cnt += 1
            if cnt > 0:
                toadd['source'] = ",".join(sorted(toadd['source']))
                for h in self.uniprot_headers + self.genename_headers:
                    toadd[h] = ",".join(sorted(toadd.get(h,[])))
                toadd['genekey'] = self.max_freq_gene(toadd)
                for h in self.uniprot_headers + self.genename_headers:
                    for k in filter(None,toadd[h].split(',')):
                        self.gks[k].add(toadd['genekey'])
                toadd['complete'] = (cnt == len(self.taxids))
                toadd['consistent'] = all(map(lambda h: toadd.get(h,"").lower() == toadd['genekey'],self.genename_headers))
                self.addrow(toadd)

    def index_by_uniprot(self):
        self.index([ (h,) for h in self.uniprot_headers ])

    def mark_multi_genekey(self,sym="*"):
        for i in self._table:
            for h in self.uniprot_headers + self.genename_headers:
                val = self._table[i][h].split(',')
                for j in range(len(val)):
                    if len(self.gks[val[j]]) > 1:
                        val[j] += sym
                self._table[i][h] = ",".join(val)

    def mark_notmasterlist(self,masterlist,sym="!"):
        for i in self._table:
            for h in self.uniprot_headers:
                val = self._table[i][h].split(',')
                for j in range(len(val)):
                    if val[j] and val[j].rstrip("*") not in masterlist:
                        val[j] += sym
                self._table[i][h] = ",".join(val)

    def max_freq_gene(self,r):
        f = defaultdict(int)
        f['~~~~~~~~~~~~~~~~'] = -1
        for gn in map(r.get,self.genename_headers):
            if gn:
                f[gn.lower()] += 1
        return min(f.items(),key=lambda p: (-p[1],p[0]))[0]

    def sortedbygene(self):
        for r in sorted(self,key=itemgetter('genekey')):
            yield r

    def alluniprot(self):
        seen = set([None])
        for r in self:
            for h in self.uniprot_headers:
                up = r.get(h)
                if up not in seen:
                    yield up
                    seen.add(up)

    def asset(self,cl):
        retval = set()
        for h1,h2 in zip(self.uniprot_headers,self.genename_headers):
            if cl.get(h1):
                retval.add((cl[h1],cl.get(h2,"")))
        return retval

    def byuniprot(self,upacc):
        return self.all(upacc)

    def byname(self,name):
        return self.one(name)

from pygly.GlycanResource import GlyGenWS
glygenws = GlyGenWS(verbose=True)

class GlyGenTable(ModelTable):

    @staticmethod
    def touniprot(up):
        return up.rsplit('-',1)[0]
  
    transform = {'uniprot': (touniprot,'uniprotkb_canonical_ac')}

    def __init__(self,species=None):
        super().__init__(rows=self.make_rows(self.method,species))

    @staticmethod
    def make_rows(ggws,species):
        if not species:
            species=species_table
        for spec in species.distinct('glygen_name'):
            for row in ggws(spec):
                if 'taxid' not in row and 'tax_id' not in row:
                    row['taxid'] = species.taxid(spec)
                yield row

class GlyGenGeneName(GlyGenTable):
    indexes = [ ('uniprot',), ('gene_symbol_recommended',) ]
    method = glygenws.protein_genenames

    def genename(self,uniprot):
        return self.one(uniprot)['gene_symbol_recommended']

    def genenames(self,uniprot):
        return sorted([ r['gene_symbol_recommended'] for r in self.all(uniprot) ])

class GlyGenGeneID(GlyGenTable):
    indexes = [ ('uniprot',) ]
    method = glygenws.protein_geneid

    def geneid(self,uniprot):
        return self.one(uniprot)['xref_id']

class GlyGenRefSeq(GlyGenTable):
    indexes = [ ('uniprot',) ]
    method = glygenws.protein_refseqnp

class GlyGenHomoClusters(GlyGenTable):
    method = glygenws.protein_homolog_clusters
    transform = {'uniprot': (GlyGenTable.touniprot,'uniprotkb_canonical_ac'), 
                 'taxid': (int,'tax_id'),
                 'tax_id': (int,'tax_id'),
                 'source': (lambda s: s[len('protein_xref_'):].replace('_homologset',''),'xref_key')
                }
    @staticmethod
    def make_rows(ggws,species):
        taxids = set(species.distinct('taxid'))
        for row in ggws():
            if int(row['tax_id']) in taxids:
                yield row

class GlyGenGeneNameClusters(ModelTable):
    transform = {'uniprot': (GlyGenTable.touniprot,'uniprotkb_canonical_ac'),
                 'clustid': (str.lower,'gene_symbol_recommended'),
                 'gene_symbol_alternative': None,
                 'uniprotkb_canonical_ac': None,
                 'gene_symbol_recommended': None,
                 'orf_name': None,
                 }
    def __init__(self,species=None):
        super().__init__(rows=self.make_rows(species))

    def key(self,row):
        return row['taxid'],GlyGenTable.touniprot(row['uniprot']),row['gene_symbol_recommended'].lower()

    def make_rows(self,species):
        seen = set()
        for row in GlyGenGeneName(species=species):
            del row['_id']
            row1 = None
            if row.get('gene_symbol_alternative'):
                row1 = dict(row.items())
                row1['gene_symbol_recommended'] = row1['gene_symbol_alternative']
            key = self.key(row)
            if key not in seen:
                yield row
                seen.add(key)
            if row1:
                key1 = self.key(row1)
                if key1 not in seen:
                    yield row1
                    seen.add(key1)

class GlyGenProteinMasterlist(GlyGenTable):
    indexes = [ ('uniprot',) ]
    method = glygenws.protein_masterlist

class EnzymeMapping(ModelTable):
    id_column = 'instance'
    filename = '../model/enzyme_mappings.csv'
    unique = [ ('residue_id','uniprot') ]
    indexes = [ ('uniprot',) ]

class RuleData(ModelTable):
    filename = '../model/rule_data.tsv'
    id_column = 'instance'
    # notnull = [ 'enzyme' ]
    unique = [ ('rule_id','enzyme','focus','other_residue') ]
    indexes = [ ('enzyme',) ]
    # transform = {'uniprot': (str,'enzyme'), 'residue_id': (str,'focus')}

# residue_name,residue_id,name,anomer,absolute,ring,parent_id,site,form_name,notes
class CanonicalResidues(ModelTable):

    @staticmethod
    def toindex(rid):
         m = re.search(r'^([A-Z]+)(\d*)$',rid)
         if not m.group(2):
             return m.group(1),0
         return m.group(1),int(m.group(2))

    unique = [ ('residue_name',), ('residue_id',), ('name','anomer','absolute','site','ring','parent_id') ]

    def __init__(self):
         super().__init__()
         self.maxid = self.maxid()

    def newid(self):
        self.maxid = (self.maxid[0],self.maxid[1]+1)
        return self.maxid[0]+str(self.maxid[1])

    def maxid(self):
        maxind = max([ self.toindex(r['residue_id']) for r in self ],key=lambda t: t[1])
        return maxind[0],maxind[1]

    def clonenode(self,rid,parentid):
        row = self.one(rid)
        row['residue_id'] = self.newid()
        residue_index = self.toindex(row['residue_id'])
        row['residue_name'] = row['residue_name'].rsplit('_',1)[0] + "_" + str(residue_index[1])
        row['parent_id'] = parentid
        row['notes'] = "cloned from node %s"%(rid,)
        del row['_id']
        self.addrow(row)
        print(self.rowstr(row,sep=self._sep))

class NCanonicalResidues(CanonicalResidues):
    filename = '../model/N_canonical_residues.csv'
    
class OCanonicalResidues(CanonicalResidues):
    filename = '../model/O_canonical_residues.csv'
