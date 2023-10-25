#!/bin/env python3

import sys 

from tree import GlycoTree

gt = GlycoTree()

for row in gt.rule_table():
    focus = row.get('focus')
    other = row.get('other_residue')
    if not other:
        continue
    # print(focus,other,gt.get_parent(focus))
    if gt.get_parent(focus) != gt.get_parent(other):
        print(focus,other)
