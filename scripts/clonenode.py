#!/bin/env python3.12

import sys
from annotable import NCanonicalResidues, OCanonicalResidues

rid = sys.argv[1]
if rid.startswith('N'):
    nodes = NCanonicalResidues()
elif rid.startswith('O'):
    nodes = OCanonicalResidues()
else:
    raise RuntimeError

for toid in sys.argv[2:]:
    nodes.clonenode(rid,toid)

