
import sys, json, urllib.request, copy, re
from collections import defaultdict
from itertools import product

class GlycoTree(object):
    baseurl = "https://sandbox.glyomics.org/api"
    enzymes = "getEnzymeMappings.php?limiter=no_filter&val="
    rules = "getAllRuleData.php?limiter=no_filter&val="

    capping_roots = ['N2','N7','N5','N31','N9','O156','O157']

    def __init__(self):
        self.build_tree()

    def build_tree(self):
        self.read_tree()
        self.add_children()
        self.add_rules()
        self.add_levels()
        self.add_capping_levels()

    def enzyme_table(self):
        h = urllib.request.urlopen(self.baseurl + "/" + self.enzymes)
        data = json.loads(h.read())
        for row in data['data']:
            for k in list(row):
                if not row.get(k):
                    del row[k]
            yield row

    def rule_table(self):
        h = urllib.request.urlopen(self.baseurl + "/" + self.rules)
        data = json.loads(h.read())
        for row in data['data']:
            for k in list(row):
                if not row.get(k):
                    del row[k]
            if not row.get('focus'):
                continue
            yield row

    def read_tree(self):
        self.residues = {}
        for row in self.enzyme_table():
            rid = row['residue_id']
            if not rid in self.residues:
                self.residues[rid] = copy.copy(row)
                self.residues[rid]['enzymes'] = []
                self.residues[rid]['rules'] = []
                if row.get('gene_name'):
                    self.residues[rid]['enzymes'].append((row.get('enzyme_id'),row.get('gene_name'),row.get('species'),row.get('uniprot')))
                for k in ('gene_name','species','uniprot'):
                    if k in self.residues[rid]:
                        del self.residues[rid][k]
            else:
                if row.get('gene_name'):
                    self.residues[rid]['enzymes'].append((row.get('enzyme_id'),row['gene_name'],row['species'],row['uniprot']))

    def add_children(self):
        for rid in list(self.residues):
            pid = self.residues[rid].get('parent_id')
            if pid and pid != 'no_id':
                if 'children' not in self.residues[pid]:
                    self.residues[pid]['children'] = dict()
                chkey = (self.residues[rid]['site'],self.residues[rid]['anomer'],self.residues[rid]['absolute'],self.residues[rid]['form_name'])
                if chkey in self.residues[pid]['children']:
                    print("Duplicate residue:",rid)
                    continue
                self.residues[pid]['children'][chkey] = rid

    def get_data(self,rid):
        return self.residues.get(rid,{})

    def add_rules(self):
        for row in self.rule_table():
            rid = row['residue_id']
            if not rid in self.residues:
                continue
            rule_data = tuple(map(lambda k: row.get(k),('rule_id','enzyme_id','other_residue','polymer')))
            self.residues[rid]['rules'].append(rule_data)

    def get_parent(self,rid):
        return self.residues.get(rid,{}).get('parent_id',None)

    def get_root(self,rid):
        prid = self.get_parent(rid)
        if not prid or prid == 'no_id':
            return rid
        return self.get_root(prid)

    def get_category(self,rid):
        anc = self.get_ancestors(rid)
        if len(set(anc) & set(self.capping_roots)):
            return "N/O-linked, capping"
        if len(anc) == 0:
            root = rid
        else:
            root = anc[-1]
        if root.startswith('N'):
            return "N-linked, non-capping"
        if root == "OC":
            return "Mucin-type O-linked, non-capping"
        if root == "OC1":
            return "O-Fuc core"
        if root == "OC2":
            return "O-GlcNAc core"
        if root == "OC3":
            return "O-Gal core"
        if root == "OC4":
            return "O-Man core"
        return None

    def get_ancestors(self,rid):
        anc = []
        prid = self.get_parent(rid)
        if not prid or prid == 'no_id':
            return []
        return ([prid] + self.get_ancestors(prid))

    def get_children(self,rid):
        return self.residues.get(rid,{}).get('children',{}).items()

    def get_enzymes(self,rid):
        return self.residues.get(rid,{}).get('enzymes',[])

    def has_species_enzymes(self,rid,species):
        for _ in self.get_species_enzymes(rid,species):
            return True
        return False

    def get_species_enzymes(self,rid,species):
        return filter(lambda t: t[2] == species,self.get_enzymes(rid))

    def get_rules(self,rid):
        return self.residues.get(rid,{}).get('rules',[])

    def get_enzyme_rules(self,rid,enz):
        for rule in self.get_rules(rid):
            if not rule[1] or enz[2] == rule[1]:
                yield rule

    def get_level(self,rid):
        return self.residues.get(rid,{}).get('level')

    def get_capping_level(self,rid):
        return self.residues.get(rid,{}).get('capping_level')

    def get_edges(self,rid):
        return self.residues.get(rid,{}).get('children',{}).keys()

    def get_toedge(self,rid):
        return (self.residues[rid]['site'],self.residues[rid]['anomer'],self.residues[rid]['absolute'],self.residues[rid]['form_name'])

    def get_child(self,rid,edge,default=None):
        return self.residues.get(rid,{}).get('children',{}).get(edge,default)

    def get_roots(self):
        res = []
        for rid in self.residues:
            pid = self.residues[rid].get('parent_id')                                                                        
            if not pid or pid == 'no_id':
                res.append(rid)
        return res

    def compute_levels(self):
        residuetolevel = dict()
        self._compute_levels(residuetolevel,self.get_roots())
        return residuetolevel

    def compute_capping_levels(self):
        residuetolevel = dict()
        self._compute_levels(residuetolevel,self.capping_roots)
        return residuetolevel

    def _compute_levels(self,residuetolevel,seeds,level='1'):
        # print(level,seeds)
        for s in seeds:
            if s and s != "-":
                residuetolevel[s] = level
                # residuetolevel[level].append(s)
        alledges = set()
        for s in seeds:
            if s and s != "-":
                alledges.update(self.get_edges(s))
        for i,e in enumerate(sorted(alledges,key=lambda t: (t[2],t[1],t[0]))):
            seeds1 = [ self.get_child(s,e,"-") for s in seeds ]
            self._compute_levels(residuetolevel,seeds1,level+"."+str(i+1))

    def add_levels(self):
        map = self.compute_levels()
        for rid,level in map.items():
            self.residues[rid]['level'] = level

    def add_capping_levels(self):
        map = self.compute_capping_levels()
        for rid,level in map.items():
            self.residues[rid]['capping_level'] = level

    def all_residues(self,roots=None):
        if roots is None:
            roots = self.get_roots()
        toexplore=set(roots)
        while len(toexplore) > 0:
            rid = toexplore.pop()
            yield rid
            for edge,crid in self.get_children(rid):
                toexplore.add(crid)

    def _iupac(self,adj,rid,topo=False,label=False,root=False):
        branches = []
        for e,crid in adj[rid]:
            name = e[3].replace("p","").replace("x","").replace("NeuN","Neu")
            if name not in ("NeuAc","NeuGc","KDN"):
                childpos = 1
            else:
                childpos = 2
            b = self._iupac(adj,crid,topo=topo,label=label)
            if label:
                name = "%s[%s]"%(name,crid)
            if not topo:
                branches.append(b+"%s%s%s-%s"%(name,e[1],childpos,e[0]))
            else:
                branches.append(b+"%s%s%s-%s"%(name,"?",childpos,"?"))
        branches.sort(key=lambda b: (b[-1],b[::-1]))
        if root:
            e0 = self.get_toedge(rid)
            name = e0[3].replace("p","").replace("x","").replace("NeuN","Neu")
            if label:
                name = "%s[%s]"%(name,rid)
            if e0[1] == "x" or topo:
                s = name
            else:
                s = name + e0[1]
        else:
            s = ""
        for b in branches[:-1]:
            s = "("+b+")"+s
        if len(branches) > 0:
            s = branches[-1]+s
        return s
  
    def iupac(self,root,rids,topo=False,label=False):
        adj = defaultdict(list)
        for t in rids:
            adj[t[0]].append((self.get_toedge(t[1]),t[1]))
        return self._iupac(adj,root,topo=topo,label=label,root=True)

    def comp(self,root,rids,base=False):
        comp = defaultdict(int)
        comp[self.get_name(root)] += 1
        for t in rids:
            comp[self.get_name(t[1])] += 1
        if base:
            comp = self.fixcomp(comp)
            for key in "Sia dHex Glc Gal Man GlcNAc GalNAc ManNAc".split():
                if key in comp:
                    del comp[key]
        s = []
        for k in sorted(comp):
            if comp[k] > 0:
                s.append("%s(%d)"%(k,comp[k]))
        return "".join(s)

    def bcomp(self,root,rids):
        return self.comp(root,rids,base=True)

    def fixcomp(self,comp):
        if 'Hex' not in comp:
            comp['Hex'] = sum(map(lambda x: comp.get(x,0),"Glc Gal Man".split()))
        if 'HexNAc' not in comp:
            comp['HexNAc'] = sum(map(lambda x: comp.get(x,0),"GlcNAc GalNAc ManNAc".split()))
        if 'Sia' not in comp:
            comp['Sia'] = sum(map(lambda x: comp.get(x,0),"NeuAc NeuGc KDN".split()))
        if 'dHex' not in comp:
            comp['dHex'] = sum(map(lambda x: comp.get(x,0),"Fuc".split()))
        return comp

    def comp_has_residue(self,residue,comp):
        # print(residue,comp,end=" ")
        if comp.get(residue,0) > 0:
            # print(True)
            return True
        else:
            if residue in ('Glc','Gal','Man') and \
                comp.get('Hex',0) - sum(map(lambda x: comp.get(x,0),('Glc','Gal','Man')))>0:
                # print(True)
                return True
            if residue in ('GlcNAc','GalNAc','ManNAc') and \
                comp.get('HexNAc',0) - sum(map(lambda x: comp.get(x,0),('GlcNAc','GalNAc','ManNAc')))>0:
                # print(True)
                return True
            if residue in ('Fuc',) and \
                comp.get('dHex',0) - sum(map(lambda x: comp.get(x,0),('Fuc',)))>0:
                # print(True)
                return True
            if residue in ('NeuAc','NeuGc','KDN') and \
                comp.get('Sia',0) - sum(map(lambda x: comp.get(x,0),('NeuAc','NeuGc','KDN')))>0:
                # print(True)
                return True
        # print(False)
        return False
       
    def comp_remove_residue(self,residue,comp):
        assert self.comp_has_residue(residue,comp)
        if comp.get(residue,0) > 0:
            comp[residue] -= 1
        if residue in ('Glc','Gal','Man'):
            comp['Hex'] -= 1
        if residue in ('GlcNAc','GalNAc','ManNAc'):
            comp['HexNAc'] -= 1
        if residue in ('Fuc',):
            comp['dHex'] -= 1
        if residue in ('NeuAc','NeuGc','KDN'):
            comp['Sia'] -= 1
        return comp 

    def test_rule(self,rid,rule,rids,notrids=None):
        # print(rid,rids,notrids,rule,end=" ")
        if rule[0] == 1:
            if notrids is None and rule[2] in rids:
                # print("TRUE")  
                return True
            elif notrids is not None and (rule[2] in rids or rule[2] not in notrids):
                # print("TRUE")  
                return True
            else:
                # print("FALSE")  
                return False
        elif rule[0] == 2:
            if notrids is None and rule[2] not in rids:
                # print("TRUE")
                return True
            elif notrids is not None and (rule[2] in notrids or rule[2] not in rids):
                # print("TRUE")
                return True
            else:
                # print("FALSE")
                return False
        elif rule[0] in (4,5,7,8):
            # print("FALSE")  
            return False
        raise RuntimeError("BLAH",rid,rule)

    def ridsortkey(self,rid):
        try:
            return rid[0],int(rid[1:])
        except ValueError:
            pass
        map = dict(NA=("N",-3),NB=("N",-2),NC=("N",-1))
        return map[rid]

    def check_rules(self,rids,notrids=None,focus=None):
        rids = set(rids)
        # print("RIDS:"," ".join(sorted(rids,key=self.ridsortkey)))
        # print("NOTRIDS:",None if notrids is None else " ".join(sorted(notrids,key=self.ridsortkey)))
        # print("FOCUS:",focus)
        goodrids = set()
        if focus:
            tocheck = [ focus ]
        else:
            tocheck = sorted(rids,key=self.ridsortkey)
        for rid in tocheck:
            ridgood = True
            if len(self.get_rules(rid)) > 0:
                ridgood = False
                for enz in self.get_species_enzymes(rid,"Homo sapiens"):
                    rule2rules = []
                    enzgood = True
                    for rule in self.get_enzyme_rules(rid,enz):
                        if rule[0] == 2:
                            rule2rules.append(rule)
                        elif not self.test_rule(rid,rule,rids,notrids):
                            enzgood = False
                    if not enzgood:
                        continue
                    if enzgood and len(rule2rules) == 0:
                        ridgood = True
                        break
                    goodrule2 = 0
                    for rule in rule2rules:
                        if self.test_rule(rid,rule,rids,notrids):
                            goodrule2 += 1
                    if goodrule2 == len(rule2rules):
                        ridgood = True
                        break
                            
            if ridgood:
                # print("GOOD RID:",rid)
                goodrids.add(rid)
            else:
                pass # print("BAD RID:",rid)
        # print("RULE CHECK",goodrids == set(tocheck))
        return (goodrids == set(tocheck))
       
    def generate_structures(self,residue,*constraints):

        from glyomicsclient import GlyLookupClient
        glc = GlyLookupClient()
        extras = {}
        for i in range(0,len(constraints),2):
            k,v = constraints[i:(i+2)]
            if k == "depth":
                extras[k] = int(v)
            elif k == "degree":
                extras[k] = int(v)
            elif k == "monos":
                extras[k] = int(v)
            elif k == "required":
                extras[k] = set(map(str.strip,v.split(',')))
            else:
                if 'comp' not in extras:
                    extras['comp'] = {}
                extras['comp'][k] = int(v)
        if 'comp' in extras:
            extras['comp'] = self.fixcomp(extras['comp'])
            # print(extras['comp'])
            assert self.comp_has_residue(self.get_name(residue),extras['comp'])
            self.comp_remove_residue(self.get_name(residue),extras['comp'])
            # print(extras['comp'])

        first = True
        seen = set()
        seentopo = dict()
        i = 0
        for rids in self._generate_structures({residue: (dict(),1)},**extras):
            allids = [ "%s:%d"%t for t in tuple(sorted(set([ (t[0],t[2]-1) for t in rids] + [(t[1],t[2]) for t in rids] + [(residue,1)]),key=lambda t: (t[1],t[0]))) ]
            allids1 = tuple(t.split(':')[0] for t in allids)
            assert allids1 not in seen
            seen.add(allids1)
            # print(i+1,", ".join(allids))
            if 'required' in extras and len(set(allids1).intersection(extras['required'])) != len(extras['required']):
                continue
            if not self.check_rules(allids1):
                continue
            topo_iupac = self.iupac(residue,rids,topo=True)
            if topo_iupac not in seentopo:
                seentopo[topo_iupac] = max([0] + list(seentopo.values()))+1
            iupac = self.iupac(residue,rids)
            iupac0 = re.sub(r'[ab]([12])-\d',r'?\1-?',iupac)
            if iupac0 not in seen:
                seen.add(iupac0)
                bcomp = self.bcomp(residue,rids)
                comp = self.comp(residue,rids)
                iupac1 = self.iupac(residue,rids,label=True)
                acc = glc.get_accession_for_sequence(iupac) or ""
                topoacc = glc.get_accession_for_sequence(topo_iupac) or ""
                # seentopo.add(topo_iupac)
                if first:
                    print("\t".join("index base_composition composition iupac topology topo_iupac labelled_iupac acc topo_acc".split()))
                    first = False
                print("\t".join([str(i+1),bcomp,comp,iupac,str(seentopo[topo_iupac]),iupac0,iupac1,acc,topoacc]))
                i += 1
            # for prid,rid in rids:
            #     print(prid,*self.get_toedge(rid),rid,end=", ")
            # print()

    def get_name(self,rid):
        return self.formname2name(self.get_toedge(rid)[3])

    def formname2name(self,formname):
        return formname.replace("p","").replace("x","").replace("NeuN","Neu")

    def get_edge_name(self,rid):
        t = list(map(self.residues.get(rid,{}).get,("form_name","anomer","site","parent_form_name")))
        if t[0]:
            t[0] = self.formname2name(t[0])
        if t[3]:
            t[3] = self.formname2name(t[3])
        name = "-".join(map(lambda v: v if v is not None else "",t))
        if name.endswith('x-0-'):
            name = name.replace('x-0-','x')
        return name
    
    def _generate_structures(self,residues,**kwargs):
        rids = set(filter(lambda k: residues[k][0] is not None,residues))
        # notrids = set(filter(lambda k: residues[k][0] is None,residues))
        # print("RIDS:",rids)
        # print("NOTRIDS:",notrids)
        # print(residues,kwargs)
        additions = defaultdict(list)
        anyadditions = False
        required = set()
        for rid,(sites,depth) in sorted(residues.items(),key=lambda t: t[1][1] if t[1][1] is not None else 1e+20):
            if sites is None:
                continue
            if 'degree' in kwargs and sum(1 for _ in filter(None,sites.values())) >= kwargs["degree"]:
                continue
            if 'monos' in kwargs and len(rids) >= kwargs["monos"]:
                continue
            for edge,crid in self.get_children(rid):
                if edge[0] in sites:
                    continue
                if edge[3] in ("phosphate","sulfate","Xylf","Xylp","NeupNGc","KDNp","GlcAp","Galf","ManpNAc"):
                    continue
                if 'depth' in kwargs and depth >= kwargs["depth"]:
                    continue
                if not self.has_species_enzymes(crid,'Homo sapiens'):
                    continue
                if 'comp' in kwargs and not self.comp_has_residue(self.get_name(crid),kwargs['comp']):
                    continue
                rids = set(filter(lambda k: residues[k][0] is not None,residues))
                notrids = set(filter(lambda k: residues[k][0] is None,residues))
                if not self.check_rules(rids,notrids,crid):
                    continue
                if 'required' in kwargs and crid in kwargs['required']:
                    required.add((rid,edge[0]))
                anyadditions = True
                additions[rid,edge[0]].append((crid,rid,edge,depth+1))
        if not anyadditions:
            if 'comp' not in kwargs or max(kwargs["comp"].values()) == 0:
                yield [ (k,j,residues[k][1]+1) for k in residues for j in ([] if residues[k][0] is None else residues[k][0].values()) if j is not None ] 
        else:
            for rid,site in sorted(additions,key=lambda t: -1*(t in required)):

                if (rid,site) not in required:
                    residues1 = copy.deepcopy(residues)
                    residues1[rid][0][site] = None
                    for c in additions[rid,site]:
                        residues1[c[0]] = (None,None)
                    for rids in self._generate_structures(residues1,**kwargs):
                        yield rids

                toconsider = list(additions[rid,site])
                if (rid,site) in required:
                    toconsider = [ t for t in toconsider if t[0] in kwargs['required'] ]
                    assert len(toconsider) > 0
                for crid,rid,edge,depth in toconsider:
                    residues1 = copy.deepcopy(residues)
                    assert crid not in residues1, crid
                    residues1[crid] = (dict(),depth)
                    for c in additions[rid,site]:
                        if c[0] != crid:
                            residues1[c[0]] = (None,None)
                    residues1[rid][0][edge[0]] = crid
                    kwargs1 = copy.deepcopy(kwargs)
                    if 'comp' in kwargs1:
                        # print('blah')
                        self.comp_remove_residue(self.get_name(crid),kwargs1['comp'])
                        # print(self.get_name(crid),kwargs1['comp'])
                    for rids in self._generate_structures(residues1,**kwargs1):
                        yield rids

                break

class GlycoTreeDev(GlycoTree):
    baseurl = "https://edwardslab.bmcb.georgetown.edu/sandboxdev/api"

if __name__ == "__main__":

    if len(sys.argv) >= 2:
        cmd = sys.argv[1]
    else:
        cmd = "help"
    args = sys.argv[2:]

    gt=GlycoTreeDev()
    if cmd == "enzymes":
        headers = """
            residue_id
            site
            anomer
            absolute
            form_name
            parent_id
            parent_absolute
            parent_form_name
            enzyme_id
            uniprot 
            gene_name
            species 
            type
            status
            proposer_id
        """.split()
        print("\t".join(headers))
        for r in gt.enzyme_table():
            # if not r.get('uniprot'):
            #     continue
            print("\t".join(map(lambda k: str(r.get(k,"")),headers)))

    elif cmd == "nodegroups":
        restoenzset = defaultdict(set)
        restofn = dict()
        for r in gt.enzyme_table():
            if not r.get('uniprot'):
                continue
            if r['species'] not in ('Homo sapiens','Mus musculus'):
                continue
            restoenzset[r['residue_id']].add(r['gene_name'].lower())
            restofn[r['residue_id']] = tuple(map(r.get,("form_name","anomer","site","parent_form_name")))
        for k,v in list(restoenzset.items()):
            restoenzset[k] = tuple(sorted(restoenzset[k])+[restofn[k]])
        enzsettores = defaultdict(set)
        enzsettofn = defaultdict(set)
        for k,v in restoenzset.items():
            # print(k,v)
            # print(restofn[k])
            enzsettores[v].add(k)
            toadd = "-".join(map(lambda v: v if v is not None else "",restofn[k]))
            if toadd.endswith('x-x-0-'):
                toadd = toadd.replace('x-x-0-','x')
            enzsettofn[v].add(toadd)
        nodeset = dict()
        for i,(k,v) in enumerate(enzsettores.items()):
            key = ",".join(sorted(v))
            if key not in nodeset:
                nodeset[key] = max(list(nodeset.values())+[0])+1
        for k,v in enzsettores.items():
            nodes = ",".join(sorted(v)[:3])
            if len(v) > 3:
                nodes += ",..."
                nodes += "(%d)"%(len(v))
            nodes = str(nodeset[",".join(sorted(v))]) + ":" + nodes
            for fn in enzsettofn[k]:
                for enz in k[:-1]:
                    print(" ".join(["%-15s"%(enz,),"%-25s"%(nodes,),"%-20s"%(fn,)]))

    elif cmd == "rules":
        headers = """
            residue_id
            site
            anomer
            absolute
            form_name
            rule_id
            focus
            enzyme_id
            uniprot
            other_residue
            polymer
            status
        """.split()
        print("\t".join(headers))
        for r in gt.rule_table():
            print("\t".join(map(lambda k: str(r.get(k,"-")),headers)))

    elif cmd == "generate":
        gt.generate_structures(*args)

    elif cmd == "help":
        print("Usage: python tree.py [ enzymes | rules ]",file=sys.stderr)
        sys.exit(1)

    else:
        print("bad cmd",file=sys.stderr)
        sys.exit(1)
