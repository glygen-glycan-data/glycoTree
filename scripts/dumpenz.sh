#!/bin/sh

wget -q -O - 'https://edwardslab.bmcb.georgetown.edu/sandboxdev/api/getEnzymeMappings.php?limiter=no_filter&val=' | jq -r '.data[] | [.residue_id, .anomer, .site, .form_name, .parent_id, .gene_name]  | @tsv'
