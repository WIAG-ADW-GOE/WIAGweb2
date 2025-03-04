# %% [markdown]
# # 2. Update wiag id (in table persons) in Digitales Personenregister (DPr)
# 
# Updates DPr with the WIAG IDs assigned to each person, ensuring DPr remains the primary and up-to-date source of data.

# ## Requirements
# * You need access to the following sql databases at https://vwebfile.gwdg.de/phpmyadmin/:
#   * wiag database
#   * Digitales Personenregister database
# 
# In you can't access the database at the link above, please check if you are in the GWDG network. You can fix this by using the GWDG VPN.
#
# ## Steps in the notebook
# 1. Get data from WIAG
# 
# 2. Get data from the Digitales Personenregister (DPr)
# 
# 3. Check for problematic entries
# 
# 4. If everything is fine, check for outdated entries
# 
# 5. Generate SQL file to update the outdated entries in the Digitales Personenregister

# %% [markdown]
# ## Get data from wiag
#
# ### Run the SQL query
#
# Run the "Step 2 of the sync notebooks for WIAG" sql query on the WIAG database (https://vwebfile.gwdg.de/phpmyadmin/) and then export the results as csv. Please refer to the instructions at [Run_SQL_Query_and_Export_CSV.md](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/docs/Run_SQL_Query_and_Export_CSV.md) for a detailed explanation of running the sql command.
#
# ### Import the file
# Please move the downloaded file to the `input_path` directory defined below or change the `input_path` to where the file is located.

# %%
# first loading necessary libraries
import requests
import csv
import os
import pandas as pd
import json
import re
import os
from datetime import datetime

# change this to where the csv file is located (e.g. C:\Users\<your_username_here>\Downloads\) or move the csv file to this directory
input_path = r"C:\Users\Public\WIAGweb2\notebooks\sync_notebooks\input_files"

filename = 'item.csv' # change this in case you renamed the file

ic_df = pd.read_csv(os.path.join(input_path, filename), names=["id", "wiag_id", "gsn"]) # importing the data

# %% [markdown]
# ### Check data from WIAG

# The following code block should be empty. If this is not empty, you cannot proceed. This problem needs to fixed manually. Please contact Barbara Kroeger to resolve this issue.

# %%
ic_df[ic_df['gsn'].isna()] # checking for entries with an empty Germania Sacra Number field

# %% [markdown]
# ## Get data from DPr
# 
# ### Run the SQL query
# This time run the query (with the same name) on the Digitales Personenregister database.
#
# ### Import the file
# Once again, please move the downloaded file to the `input_path` directory defined above.

# %%
filename = 'persons.csv' # change this in case you renamed the file

dpr_df = pd.read_csv(os.path.join(input_path, filename), names=["wiag_id", "id", "gsn_table_id", "gsn"])

# %% [markdown]
# ### Check data from DPr
#
# If both code cell outputs are empty, there are no problems and you can proceed.

# %%
dpr_df[dpr_df['gsn'].isna()] # checking for entries without a GSN

# %%
dpr_df_gp = dpr_df.groupby('wiag_id').count()
dpr_df_gp[dpr_df_gp['gsn'] > 1] # checking for entries with the same WIAG-ID

# %% [markdown]
# ## Check combined data
#
# ### Check for multiple records in WIAG for one Germania Sacra Number (GS Number) - manual fix
# 
# The code cell below finds GSNs that more than one entry in WIAG are referencing and lists the problematic entries. Should any entries be listed, these need to be fixed manually before proceeding.
# 
# In case a GSN should be ignored, e.g. because it is unclear whether the linked WIAG entries reference the same person, add the GSN to the list at the start of the cell and it will be omitted.
#
# %%
# A list of GSNs of known DPr entries which are linked to more than one entry in WIAG, for which it is unclear whether the different entries reference the same person or not.
gsns_of_known_problematic_wiag_entries = ['046-02872-001', '007-00413-001']

# find duplicates
gp_df = ic_df.groupby('gsn').count()
duplicate_wiag_gsns = gp_df[gp_df['id'] > 1].index.to_list()

# remove known entries
for gsn in gsns_of_known_problematic_wiag_entries:
    if gsn in duplicate_wiag_gsns: duplicate_wiag_gsns.remove(gsn)
    else :
        print("Entry " + gsn + " seems to have been resolved and should most likely be removed from the list. If you are unsure, ask Barbara Kroeger.\n")

# find/merge all relevant information for duplicate entries
dupl_ppl = ic_df[ic_df['gsn'].isin(duplicate_wiag_gsns)]
dupl_ppl = dupl_ppl.merge(dpr_df, on='gsn', suffixes=('_wiag', '_dpr'))

# detect special cases where the WIAG-ID of the DPr entry is outdated
print("Special cases with outdated WIAG-ID in DPr, if any are detected:")
for gsn in duplicate_wiag_gsns:
    dpr_wiag_id = dpr_df[dpr_df['gsn'] == gsn]['wiag_id'].values[0]
    dpr_wiag_id = ["", "1"]
    if len(dupl_ppl[dupl_ppl['wiag_id_wiag'] == dpr_wiag_id]) == 0:
        print(dupl_ppl[dupl_ppl['gsn'] == gsn])

# print detected problematic entries (without known entries)
print('\nAll "normal" cases:')
dupl_ppl

# %% [markdown]
# ## Check for entries with (probably) outdated WIAG-IDs in DPr
#
# Compares the records downloaded from WIAG and DPr. The output lists entries with differing WIAG-IDs in WIAG and DPr. Check the listed entries to make sure these DPr entries simply need their WIAG-ID updated! Should you be unsure, do not proceed, but contact Barbara Kroeger!
# 
# Should the output be empty, there is nothing to be updated and you can proceed with the third notebook. Otherwise, proceed below.
# 
# In the step above a list of problematic entries, that should be ignored in both steps, is defined. Currently (2025-02) there are two such DPr entries which each have two WIAG entries linked to them, because it is unclear whether the WIAG entries reference the same person (neither can the entries be merged, nor should a separate DPr entry be created). Should you wish to have an entry ignored for a similar reason, simply add the GSN to the list.

# %%
# Join the dataframes from WIAG and DPr
joined_df = ic_df.merge(dpr_df, on='gsn', suffixes=('_wiag', '_dpr'))

# Check for linked entries that don't have the same WIAG ID
unequal_df = joined_df[joined_df['wiag_id_wiag'] != joined_df['wiag_id_dpr']]

# remove known entries that should be ignored (defined in step "Multiple records in WIAG for one GSN (manual fix)")
for entry in gsns_of_known_problematic_wiag_entries:
    index = unequal_df[unequal_df['gsn'] == entry].index
    if(index.empty):
        print("Entry " + entry + " seems to have been resolved and should most likely be removed from the list. If you are unsure, ask Barbara Kroeger.")
    else:
        unequal_df = unequal_df.drop(index)

unequal_df

# %% [markdown]
# ## Generate sql file to update DPr
# 
# Change the `output_path` to where you want the sql file to be output.
#
# Please run the code cell below. After generating the file, please open the DPr database on phpmyAdmin and run the SQL file there. You can do this by copying the text in the file and pasting it as sql in the interface (same instructions as [Run_SQL_Query_and_Export_CSV.md](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/docs/Run_SQL_Query_and_Export_CSV.md)). The file is located in the `output_files` folder or the `output_path` if you changed it.
# 
# Note: 
#  - Please do a quick check to see if there are a reasonable number of lines in the sql file (for eg: 20-200). If there are thousands of lines, this might indicate that something has gone wrong.
# 
#  - Another way to check if the generated SQL file is correct is by checking the GSNs (Germania Sacra Numbers). In the SQL file you will find lines with `-- id: <gsn>`. Please copy the id at the end of the following url: `https://personendatenbank.germania-sacra.de/index/gsn/<gsn>` and check if the wiag id noted there is missing or is incorrect by clicking on the link.

# %%
output_path = r"C:\Users\Public\WIAGweb2\notebooks\sync_notebooks\output_files"

query = "LOCK TABLES persons WRITE;\n"
for _, row in unequal_df.iterrows():
    query += f"""
    UPDATE persons
    SET wiag = '{row['wiag_id_wiag']}'
    WHERE id = {row['id_dpr']}; -- id: {row['gsn']}
"""
query += "\nUNLOCK TABLES;"
with open(os.path.join(output_path, f'update_dpr_{today_string}.sql'), 'w') as file:
    file.write(query)

# %% [markdown]
# You can now continue with the [third notebook here](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/factgrid_recon.ipynb)


