# %% [markdown]
# # 3. Factgrid Recon
# 
# The code is designed to synchronize identification numbers (IDs) between a local database (WIAG) and an online database called FactGrid. Here's a detailed explanation:
# 
# 1. Load local data: The program reads a local spreadsheet that contains personal information and their corresponding IDs.
# 
# 2. Retrieve online data: It connects to the FactGrid online database to fetch the current IDs associated with the same individuals.
# 
# 3. Identify discrepancies: The code compares the local IDs with the online IDs to find any differences or mismatches.
# 
# 4. Generate an update file: It generates a file listing the discrepancies, formatted in a way that can be used to automatically update the IDs in the FactGrid database.
# 
# In essence, the code helps maintain accurate and consistent records between the local files and the online database by identifying mismatched IDs and preparing the necessary updates.

# %% [markdown]
# ## 1. Import local (WIAG) data
#
# ### Export Csv Personendaten
# 
# Export the "Personendaten" from WIAG by following the [instructions here](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/docs/wiag_export.md).

# %% [markdown]
# ### Import the contents of the CSV file
# Next move the file to the `input_path` path (defined in the cell below) or change `input_path` to where the file is located.
# Lastly, if you renamed the file, change `input_file` to the actual name.

# %%
import requests
import csv
import os
import pandas as pd
import json
import re
from datetime import datetime
import traceback
today_string = datetime.now().strftime('%Y-%m-%d')

# change input_path if your file is located somewhere else, e.g. to "C:\Users\schwart2\Downloads""
input_path = r"C:\Users\Public\WIAGweb2\notebooks\sync_notebooks\input_files"
# change input_file if you renamed the file
input_file = f"WIAG-Domherren-DB-Lebensdaten.csv"

input_path_file = os.path.join(input_path, input_file)
wiag_persons_df = pd.read_csv(input_path_file, sep=';')
print(str(len(wiag_persons_df)) + " entries were imported.")

# %% [markdown]
# ## 2. Import Factgrid data
# This downloads and imports the data from FactGrid automatically.

# %%
url = 'https://database.factgrid.de/sparql'
query = """
SELECT ?person ?wiag WHERE {
  ?person wdt:P601 ?wiag.
  ?person wdt:P2 wd:Q7.
}
"""

r = requests.get(url, params={'query': query}, headers={"Accept": "application/json"})
data = r.json()
fg_wiag_ids_df = pd.json_normalize(data['results']['bindings'])

print(str(len(fg_wiag_ids_df)) + " entries were imported.")

# drop irrelevant columns
fg_wiag_ids_df.drop(columns=[column for column in fg_wiag_ids_df.columns if column.endswith('type')], inplace=True)
fg_wiag_ids_df.drop(columns=[column for column in fg_wiag_ids_df.columns if column.endswith('xml:lang')], inplace=True)

# extract q ID
fg_wiag_ids_df['person.value'] = fg_wiag_ids_df['person.value'].map(lambda x: x.strip('https://database.factgrid.de/entity/'))

# set column names
fg_wiag_ids_df.columns = ['FactGrid_ID', 'factgrid_wiag_id']

# %% [markdown]
# ## 3. Identify discrepancies
# In case any entries are listed, this means they have a different WIAG-ID in FactGrid from the one in WIAG.
# A file to fix these entries automatically will be generated further down after performing another check.
# %%
# merge dataframes
merged_df = fg_wiag_ids_df.merge(wiag_persons_df[['FactGrid_ID', 'id']], on='FactGrid_ID')

# check for entries where the WIAG-ID in FactGrid is different from the one in WIAG
diff_df = merged_df[merged_df['factgrid_wiag_id'] != merged_df['id']]

# List the entries in a format that FactGrid understands. (used for updating FactGrid automatically)
fg_qs_csv = diff_df.copy()
fg_qs_csv.columns = ['qid', '-P601', 'P601']
fg_qs_csv['-P601'] = '"' + fg_qs_csv['-P601'] + '"' # putting quotes around the value
fg_qs_csv['P601'] = '"' + fg_qs_csv['P601'] + '"' # putting quotes around the value
fg_qs_csv.set_index('qid')

# print any entries found
diff_df

# %% [markdown]
# ## Do a cyclic check for all entries in factgrid

# %% #TODO turn into automatic consistency check (more user-friendly)
print(len(pd.unique(fg_wiag_ids_df['FactGrid_ID'])))
print(len(pd.unique(fg_wiag_ids_df['factgrid_wiag_id'])))
print(len(fg_wiag_ids_df))

# %% [markdown]
# In case your repository (WIAGweb2) folder is **not** located under the path `path_to_repository`, change the variable below to where the folder is located.
# **Please note, that the code cell below can take about 8-10 minutes.**

# %%
path_to_repository = r"C:\Users\Public" # change this if your repository folder is located somewhere else
path_to_function_scripts = path_to_repository + r"\WIAGweb2\notebooks\sync_notebooks\scripts\factgrid_recon_function_definitions.py"
# This runs the python script located under the given path to define two needed functions (one is the main function called below).
%run $path_to_function_scripts

counter = 0
# creates a list with the WIAG-ID and FactGrid-ID paired for each entry 
still_missing_entries = list(zip(list(fg_wiag_ids_df['factgrid_wiag_id']), list(fg_wiag_ids_df['FactGrid_ID'])))

while still_missing_entries:
    counter += 1
    print(f"Starting attempt #{counter}")
    still_missing_entries = await main(still_missing_entries) # final output from the main() function works via the global variables entries_to_be_updated, wiag_different_fgID and wiag_missing_fgID

additional_updates_df = pd.DataFrame(entries_to_be_updated) # FactGrid-IDs which point to an outdated WIAG-ID (WIAG redirected to a newer one) and for which the WIAG entry does not point to the FactGrid-ID
# updating the WIAG entry with the FactGrid-ID happens in step 4
different_fgID_df = pd.DataFrame(wiag_different_fgID, columns = ["fg_wiag_id", "fg_id", "wiag_qid"])
missing_fgID_df = pd.DataFrame(wiag_missing_fgID, columns=["fg_wiag_id", "fg_id"])
print(str(len(additional_updates_df)) + " entries, that point to outdated WIAG-IDs, will be updated.")

# %% [markdown]
# If either of the two cells below lists any entries, these entries needs to be fixed manually. If in doubt, ask Barbara Kroeger!
#%%
different_fgID_df # printing WIAG-IDs, to whom a different FactGrid entry, from the one that they point to, points

#%%
missing_fgID_df # printing WIAG-IDs to whom a FactGrid entry points, but which point to no FactGrid-ID

# %% [markdown]
# This generates a Factgrid file that can be uploaded on to quick statements here https://database.factgrid.de/quickstatements/#/batch. More details to perform this [can be found here](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/docs/Run_factgrid_csv.md)
# Change `output_path` below to where you want the generated file to be saved to.

# %%
# combine the outputs from the two steps
final_fg_qs_csv = pd.concat([fg_qs_csv, additional_updates_df], ignore_index=True)

output_path = r"C:\Users\Public\WIAGweb2\notebooks\sync_notebooks\output_files" # change this to output the file somewhere else

final_fg_qs_csv["-P601"] = final_fg_qs_csv["-P601"].apply(lambda x: f'"{x}"')
final_fg_qs_csv["P601"] = final_fg_qs_csv["P601"].apply(lambda x: f'"{x}"')
final_fg_qs_csv.to_csv( # generate csv file
    os.path.join(
        output_path,
        f'factgrid_wiag_id_update_{today_string}.csv'
    ),
    index=False
)

# %% [markdown]
# You can now continue with [step 4](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/FactGrid-IDs2WIAG.ipynb).

# %% [markdown]
# 


