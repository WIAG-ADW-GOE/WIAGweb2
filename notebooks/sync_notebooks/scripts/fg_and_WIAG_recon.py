# %% [markdown]
# # 3 & 4. Reconciliation of FactGrid and WIAG (merge of steps 3 and 4)
# 
# The code is designed to synchronize identification numbers (IDs) between a local database (WIAG) and an online database called FactGrid. Here's a detailed explanation:
# 
# 1. Load local data: The program reads a local spreadsheet that contains personal information and their corresponding IDs.
# 
# 2. Retrieve online data: It connects to the FactGrid online database to fetch the current IDs associated with the same individuals.
# 
# 3. Identify discrepancies: The code compares the local IDs with the online IDs to find any differences or mismatches.
# 
# 4. Find entries to update in FactGrid: Check for entries that need to be updated in FactGrid (and for which it can be done automatically)
#
# 5. Update FactGrid: A file listing the discrepancies is generated, formatted in a way that can be used to automatically update the IDs in the FactGrid database. This needs to be uploaded manually.
#
# 6. Retrieve updated online data: The data from FactGrid is redownloaded, now that changes were made.
#
# 7. Identify some further discrepancies: e.g. multiple FactGrid-entries linking to the same WIAG-entry
#
# 8. Find entries to update in WIAG: Check for entries that need to be updated in WIAG (and for which it can be done automatically)
#
# 9. Update WIAG: WIAG-entries that do not yet link to a FactGrid-entry, but to which an FG-entry links, are updated to link back
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

# change input_path if your file is located somewhere else, e.g. to "C:\Users\schwart2\Downloads""
input_path = r"C:\Users\Public\WIAGweb2\notebooks\sync_notebooks\input_files"
# change input_file if you renamed the file
input_file = f"WIAG-Domherren-DB-Lebensdaten.csv"

input_path_file = os.path.join(input_path, input_file)
wiag_persons_df = pd.read_csv(input_path_file, sep=';')
wiag_persons_df = wiag_persons_df[['FactGrid_ID', 'id']] # selecting columns
print(str(len(wiag_persons_df)) + " entries were imported.")

# %% [markdown]
# ## 2. Import Factgrid data
# This downloads and imports the data from FactGrid automatically.

# %%
fg_url = 'https://database.factgrid.de/sparql'
fg_query = """
SELECT ?person ?wiag WHERE {
  ?person wdt:P601 ?wiag.
  ?person wdt:P2 wd:Q7.
}
"""

r = requests.get(fg_url, params={'query': fg_query}, headers={"Accept": "application/json"})
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
# ## 3. Check for problematic entries
# These need to be **fixed manually** before starting again by exporting the data (step 1).
#
# This checks whether any FactGrid-entries link to multiple WIAG-IDs and lists them.
# %%
fg_wiag_ids_df[fg_wiag_ids_df.duplicated(subset = ['FactGrid_ID'], keep = False)].sort_values(by = 'FactGrid_ID')
# %% [markdown]
# This checks whether any WIAG-entries link to multiple FactGrid-IDs and lists them.
# %%
wiag_persons_df[wiag_persons_df.duplicated(subset = ['id'], keep = False)].sort_values(by = 'id')
# %% [markdown]
# This checks whether any FactGrid-entries link to the same WIAG-ID.
# %%
fg_wiag_ids_df[fg_wiag_ids_df.duplicated(subset = ['factgrid_wiag_id'], keep = False)].sort_values(by = ['factgrid_wiag_id'])
# %% [markdown]
# For the listed IDs, a WIAG-entry links to a FactGrid-entry, which does not yet link to any WIAG-entry
# %%
# merge dataframes (outer join on WIAG-ID)
# renaming the columns
wiag_persons_df.columns = ['wiag_fg_id', 'wiag_id']
fg_wiag_ids_df.columns = ['fg_fg_id', 'wiag_id']
outer_df = fg_wiag_ids_df.merge(wiag_persons_df, how='outer' ,on='wiag_id')
fg_missing_wiag_id = outer_df[~outer_df['wiag_fg_id'].isna() & outer_df['fg_fg_id'].isna()]

# renaming the columns again for the rest of the notebook
wiag_persons_df.columns = ['FactGrid_ID', 'id']
fg_wiag_ids_df.columns = ['FactGrid_ID', 'factgrid_wiag_id']

fg_missing_wiag_id[['wiag_id', 'wiag_fg_id']]

# %% [markdown]
# ## 4. Find entries to update
# ### Check all WIAG-IDs that FactGrid-entries link to
# In case your repository (WIAGweb2) folder is **not** located under the path `path_to_repository`, change the variable below to where the folder is located.
# %%
path_to_repository = r"C:\Users\Public"
# %% [markdown]
# Please note, that the code cell below can take **up to 10 minutes.**
# The cell automatically checks for entries to update by checking WIAG-IDs that are referenced in FactGrid.
# %%
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
different_fgID_df = pd.DataFrame(wiag_different_fgID, columns = ["fg_wiag_id", "wiag_redirected", "fg_id", "wiag_fg_id"])
missing_fgID_df = pd.DataFrame(wiag_missing_fgID, columns=["fg_wiag_id", "fg_id"]) # WIAG-IDs to whom a FactGrid entry points, but which point to no FactGrid-ID
# %% [markdown]
# One more check needs to be done before updates can be performed.
# If the cell below lists any entries, these entries **needs to be fixed manually**. If in doubt, ask Barbara Kroeger!
# After fixing any entries, you need to **start again** from step 1, to make sure all problems have been fixed and no updates are incorrectly done because of incorrect data.
# These WIAG-entries link to FactGrid-entries, but the FG-entries don't link back, but instead link to a different WIAG-entry.
#%%
different_fgID_df
# %% [markdown]
# The following entries point to outdated WIAG-IDs and will be updated automatically. You should **check a sample** of the output and also make sure that the amount of entries isn't absurdly high.
# %%
if len(entries_to_be_updated) > 0:
    _to_be_updated_df = pd.DataFrame(entries_to_be_updated)
    _to_be_updated_df.columns = ["fg_id", "fg_wiag_id", "fg_wiag_id"]
    _to_be_updated_df
# %% [markdown]
# ### Check FactGrid-IDs that WIAG-entries point to
# Once again using the data imported at the beginning, entries are found that also need to be updated.
# %%
# merge dataframes (inner join on FactGrid-ID)
merged_df = fg_wiag_ids_df.merge(wiag_persons_df, on='FactGrid_ID')

# check for entries where the WIAG-ID in FactGrid is different from the one in WIAG
fg_diff_wiag_id = merged_df[merged_df['factgrid_wiag_id'] != merged_df['id']]

# %% [markdown]
# The output of the following code block **needs to be checked fully** (if there is any). For all listed FactGrid IDs a WIAG entry is linking to them but the corresponding FactGrid entry links to a different WIAG entry. The expected solution (which will be carried out automatically) is to update the FactGrid-entry with the listed WIAG-ID, however it's a good idea to manually check this, even though all weird data constellations should have been filtered out before this step.

# %%
fg_diff_wiag_id
# %% [markdown]
# ## 5. Update FactGrid
# If no entries are listed by the cell below, you can skip this entire step and go to step 6. You should have checked both lists of updates before this step, if not, check the list now.
# The `qid` is the FactGrid-ID for which an update should be performed. The `-P601` column shows the WIAG-ID which will be removed from the FactGrid-entry. The `P601` column shows the WIAG-ID which will be added to the entry.
# %%
# List the entries in a format that FactGrid understands. (used for updating FactGrid automatically)
fg_qs_csv = fg_diff_wiag_id
fg_qs_csv.columns = ['qid', '-P601', 'P601']
fg_qs_csv = fg_qs_csv.set_index('qid')

# add the updates from the first half of step 4
final_fg_qs_csv = pd.concat([fg_qs_csv, additional_updates_df], ignore_index=True)
final_fg_qs_csv # list some entries
# %% [markdown]
# ### Generate update-file
# Once all (if any popped up) problematic entries have been taken care of, the file for updating FactGrid can be generated.
#
# You can change `output_path` below to where you want the generated file to be saved to.
# %%
output_path = r"C:\Users\Public\WIAGweb2\notebooks\sync_notebooks\output_files" # change this to output the file somewhere else

from datetime import datetime
today_string = datetime.now().strftime('%Y-%m-%d') # create a timestamp for the name of the output file

final_fg_qs_csv["-P601"] = final_fg_qs_csv["-P601"].apply(lambda x: f'"{x}"') # putting quotes around the value
final_fg_qs_csv["P601"] = final_fg_qs_csv["P601"].apply(lambda x: f'"{x}"') # putting quotes around the value
final_fg_qs_csv.to_csv( # generate csv file
    os.path.join(
        output_path,
        f'factgrid_wiag_id_update_{today_string}.csv'
    ),
    index=False
)

# %% [markdown]
# ### Upload the file
#
# The generated Factgrid file can be uploaded on to quick statements here https://database.factgrid.de/quickstatements/#/batch. More details to perform this [can be found here](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/docs/Run_factgrid_csv.md)
#
# %% [markdown]
# ## 6. Retrieve updated online data
# Now that FactGrid has been updated, the data has to be redownloaded. Consequently this is almost the same code as in step 2 (the url and query variables from above are also reused)
# %%
r = requests.get(fg_url, params={'query': fg_query}, headers={"Accept": "application/json"})
data = r.json()
new_fg_wiag_ids_df = pd.json_normalize(data['results']['bindings'])

print(str(len(new_fg_wiag_ids_df)) + " entries were imported.")

# drop irrelevant columns
new_fg_wiag_ids_df.drop(columns=[column for column in new_fg_wiag_ids_df.columns if column.endswith('type')], inplace=True)
new_fg_wiag_ids_df.drop(columns=[column for column in new_fg_wiag_ids_df.columns if column.endswith('xml:lang')], inplace=True)

# extract q ID
new_fg_wiag_ids_df['person.value'] = new_fg_wiag_ids_df['person.value'].map(lambda x: x.strip('https://database.factgrid.de/entity/'))

# set column names
new_fg_wiag_ids_df.columns = ['fg_fg_id', 'wiag_id']

# %% [markdown]
# ## 7. Rerunning checks
# To make sure that no mistakes have been introduced by updating FactGrid, the checks from before are run again.
# This checks whether any FactGrid-entries link to multiple WIAG-IDs and lists them.
# %%
new_fg_wiag_ids_df[new_fg_wiag_ids_df.duplicated(subset = ['fg_fg_id'], keep = False)].sort_values(by = 'fg_fg_id')
# %% [markdown]
# This checks whether any FactGrid-entries link to the same WIAG-ID.
# %%
new_fg_wiag_ids_df[new_fg_wiag_ids_df.duplicated(subset = ['wiag_id'], keep = False)].sort_values(by = 'wiag_id') 
# %% [markdown]
# For the listed IDs, a WIAG-entry links to a FactGrid-entry, which does not yet link to any WIAG-entry
# %%
# merge dataframes (outer join on WIAG-ID) - reusing WIAG-dataframe from Step 1
# renaming the columns for joining on the WIAG-ID
wiag_persons_df.columns = ['wiag_fg_id', 'wiag_id']
outer_df = new_fg_wiag_ids_df.merge(wiag_persons_df, how='outer' ,on='wiag_id')
outer_df = outer_df[~outer_df['wiag_fg_id'].isna() & outer_df['fg_fg_id'].isna()]

outer_df[['wiag_id', 'wiag_fg_id']]

# %% [markdown]
# ## 8. Find entries to update in WIAG
# For the following list, the FactGrid-entry is linking to the WIAG-entry, but the WIAG-entry links to no the FG-entry. These entries will be updated automatically to link back. You should **check a sample** and make sure the number of updates is not absurdly high (greater than 500).
# %%
# merge dataframes - reusing WIAG-dataframe from Step 1
new_merged_df = new_fg_wiag_ids_df.merge(wiag_persons_df, on='wiag_id')
new_merged_df = new_merged_df[~new_merged_df['wiag_id'].str.startswith('WIAG-Pers-EPISCGatz')] # don't update bishops

# find WIAG-entries which do not link to an FG-ID, but an FG-entry links to the WIAG-ID => update WIAG-entries with FG-ID
to_be_updated_df = new_merged_df[new_merged_df['wiag_fg_id'].isna() & ~new_merged_df['fg_fg_id'].isna()]
to_be_updated_df = to_be_updated_df[['fg_fg_id', 'wiag_id']]
to_be_updated_df
# %% [markdown]
# Exporting the list to a CSV-file, so the entirety of proposed updates can be checked easily.
# %%
from datetime import datetime

output_path = r"C:\Users\Public\WIAGweb2\notebooks\sync_notebooks\output_files"
today_string = datetime.now().strftime('%Y-%m-%d')

to_be_updated_df.to_csv( # generate csv file
    os.path.join(
        output_path,
        f'wiag_ids_to_be_updated_{today_string}.csv'
    ),
    index=False
)
# %% [markdown]
# ## 9. Update WIAG
# ### Generate SQL file
# From the list above an SQL-file will be generated, which then needs to be uploaded. The same `output path` as above will be used.
# %%
from datetime import datetime

query = "LOCK TABLES url_external WRITE, item_corpus WRITE;\n"
for row in to_be_updated_df.itertuples():
    query += f"""
INSERT INTO url_external (item_id, value, authority_id)
SELECT item_id, '{row.fg_fg_id}', 42 FROM item_corpus
WHERE id_public = "{row.wiag_id}";
"""
query += "\nUNLOCK TABLES;"

today_string = datetime.now().strftime('%Y-%m-%d')
with open(os.path.join(output_path, f'insert-uext-can_{today_string}.sql'), 'w') as file:
    file.write(query)
# %% [markdown]
# ### Upload file

# Now that the file has been generated, you need to upload the file to the WIAG database. As always go to phpMyAdmin, then first select the database (wiagvokabulare) and then either go to the `Import` tab and choose the file to run or paste the contents of the SQL-file into the textfield (more details here [Run_SQL_Query_and_Export_CSV.md](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/docs/Run_SQL_Query_and_Export_CSV.md)).
#
# After that is completed, you can continue with the notebook for step 5 (this notebook combined steps 3 and 4).