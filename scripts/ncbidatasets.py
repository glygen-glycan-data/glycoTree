import requests
import sys

def get_closest_orthologs(gene_id, target_species):
    """
    Fetches orthologs for a given NCBI Gene ID using the NCBI Datasets API.
    
    :param gene_id: The NCBI Gene ID for the query gene (e.g., '85365' for human ALG2).
    :param target_species: A dictionary mapping NCBI Taxonomy IDs to species names.
    :return: A dictionary mapping species names to a dict containing their ortholog 'gene_id' and 'uniprot' accession.
    """
    # NCBI Datasets v2 production endpoint for retrieving gene orthologs
    url = f"https://api.ncbi.nlm.nih.gov/datasets/v2/gene/id/{gene_id}/orthologs"
    
    headers = {
        "Accept": "application/json"
    }
    
    try:
        response = requests.get(url, headers=headers)
        response.raise_for_status() # Raise an exception for bad status codes (4xx, 5xx)
    except requests.exceptions.RequestException as e:
        print(f"Error fetching data from NCBI API: {e}")
        return {}

    data = response.json()
    reports = data.get("reports", [])
    
    seen_species = set()
    
    for report in reports:
        gene_info = report.get("gene")
        if not gene_info:
            continue

        tax_id = gene_info.get("tax_id")
        taxname = gene_info.get("taxname")
        gene_id_found = gene_info.get("gene_id")
        gene_name = gene_info.get("symbol")
        
        uniprot_acc = None
        swiss_prot = gene_info.get("swiss_prot_accessions", [])
        if swiss_prot and len(swiss_prot) == 1:
            uniprot_acc = swiss_prot[0]

        gene_groups = []
        for gene_group_item in gene_info.get("gene_groups", []):
            method = "".join(gene_group_item.get("method","").split())
            gene_groups.append(method+":"+gene_group_item.get("id")) 
                
        # If the returned ortholog belongs to one of our target species
        if tax_id in target_species:
            if tax_id in seen_species:
                continue
            
            seen_species.add(tax_id)
            for gg in gene_groups:
                record = {
                    "gene_id": gene_id_found,
                    "gene_name": gene_name,
                    "uniprot": uniprot_acc,
                    "tax_id": tax_id,
                    "species": taxname,
                    "clusterid": gg
                }
                for k in list(record):
                    if not record[k]:
                        del record[k]
                yield record
                
if __name__ == "__main__":
    # Define our target species mapped by their official NCBI Taxonomy IDs
    TARGET_TAXA = {
        "9606": "human",
        "10090": "mouse",
        "10116": "rat",
        "9823": "pig",
        "9913": "bovine",
        "7227": "fruit fly",
        "44689": "slime mold",
        "10029": "hamster"
    }

    # Use command-line arguments if provided, otherwise use the test case
    if len(sys.argv) > 1:
        query_gene_ids = sys.argv[1:]
    else:
        # Test case: ALG2 (human)
        query_gene_ids = ["85365"]
    
    headers = "clusterid gene_name gene_id uniprot tax_id species".split()
    print("\t".join(headers))

    for query_gene_id in query_gene_ids:
        for data in get_closest_orthologs(query_gene_id, set(TARGET_TAXA)):
            # print(data)
            print("\t".join([str(data.get(h,"")) for h in headers]))
