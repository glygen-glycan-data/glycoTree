#!/bin/env python3.12

from annotable import *
import sys

enzymes = Enzymes()
# print(enzymes.head())

mapping = EnzymeMapping()
# print(mapping.head())

orig_enz = ModelTable(sys.argv[1])
# print(orig_enz.head())
orig_enz.index([('uniprot',)])

orig_mapping = ModelTable(sys.argv[2])
# print(orig_mapping.head())

curated = set()
for row in mapping:
    if row['proposer_id'] == "AN" and row['administrator'] == "WSY":
        enz = enzymes.one(row['enzyme_id'])
        curated.add((row['residue_id'],enz['gene_name'],enz['species']))

# print(curated)

orig_curated = set()
for row in orig_mapping:
    if row['proposer_id'] == "AN" and row['administrator'] == "WSY":
        enz = orig_enz.one(row['uniprot'])
        orig_curated.add((row['residue_id'],enz['gene_name'],enz['species']))

common = curated & orig_curated
current_only = curated - common
orig_only = orig_curated - common

print("Common:",len(common))
print("Current Only:",len(current_only))
print("Original Only:",len(orig_only))

print("Current Only:",current_only)
print("Original Only:")
for t in sorted(orig_only):
    print("\t".join(t))