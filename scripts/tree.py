
import sys, json, urllib.request, copy
from collections import defaultdict

class GlycoTree(object):
    baseurl = "https://edwardslab.bmcb.georgetown.edu/sandboxdev/api"
    enzymes = "getEnzymeMappings.php?limiter=no_filter&val="
    rules = "getAllRuleData.php?limiter=no_filter&val="

    def __init__(self):
        self.build_tree()

    def build_tree(self):
        self.read_tree()
        self.add_children()
        self.add_rules()

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
                    self.residues[rid]['enzymes'].append((row.get('gene_name'),row.get('species'),row.get('uniprot')))
                for k in ('gene_name','species','uniprot'):
                    if k in self.residues[rid]:
                        del self.residues[rid][k]
            else:
                if row.get('gene_name'):
                    self.residues[rid]['enzymes'].append((row['gene_name'],row['species'],row['uniprot']))

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

    def add_rules(self):
        for row in self.rule_table():
            rid = row['residue_id']
            if not rid in self.residues:
                continue
            rule_data = tuple(map(lambda k: row.get(k),('rule_id','enzyme','other_residue','polymer')))
            self.residues[rid]['rules'].append(rule_data)

    def get_parent(self,rid):
        return self.residues.get(rid,{}).get('parent_id',None)

    def get_children(self,rid):
        return self.residues.get(rid,{}).get('children',{}).items()

    def get_enzymes(self,rid):
        return self.residues.get(rid,{}).get('enzymes',[])

    def get_rules(self,rid):
        return self.residues.get(rid,{}).get('rules',[])

    def get_edges(self,rid):
        return self.residues.get(rid,{}).get('children',{}).keys()

    def get_toedge(self,rid):
        return (self.residues[rid]['site'],self.residues[rid]['anomer'],self.residues[rid]['absolute'],self.residues[rid]['form_name'])

    def get_child(self,rid,edge,default=None):
        return self.residues.get(rid,{}).get('children',{}).get(edge,default)

if __name__ == "__main__":
    
    if len(sys.argv) >= 2:
        cmd = sys.argv[1]
    else:
        cmd = "help"
    args = sys.argv[2:]

    gt=GlycoTree()
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
            uniprot 
            gene_name
            species 
            type
            status
            proposer_id
        """.split()
        print("\t".join(headers))
        for r in gt.enzyme_table():
            if not r.get('uniprot'):
                continue
            print("\t".join(map(lambda k: str(r.get(k,"")),headers)))

    elif cmd == "rules":
        headers = """
            residue_id
            site
            anomer
            absolute
            form_name
            rule_id
            focus
            enzyme
            other_residue
            polymer
            status
        """.split()
        print("\t".join(headers))
        for r in gt.rule_table():
            print("\t".join(map(lambda k: str(r.get(k,"-")),headers)))

    elif cmd == "help":
        print("Usage: python tree.py [ enzymes | rules ]",file=sys.stderr)
        sys.exit(1)

    else:
        print("bad cmd",file=sys.stderr)
        sys.exit(1)
