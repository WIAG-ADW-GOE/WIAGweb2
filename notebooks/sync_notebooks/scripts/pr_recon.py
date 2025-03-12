# %% [markdown]
# # 2. Update wiag id (in table persons) in Digitales Personenregister (DPr)
# 
# Updates DPr with the WIAG IDs assigned to each person, ensuring DPr remains the primary and up-to-date source of data.

# ## Requirements
# You need access to the following sql databases at https://vwebfile.gwdg.de/phpmyadmin/:
# * wiag database
# * Digitales Personenregister database
# 
# In you can't access the database at the link above, please check if you are in the GWDG network. You can fix this by using the GWDG VPN.
#
# ## Steps in the notebook
# 1. Export data from WIAG and DPr
#
# 2. Import the files
# 
# 3. Check for problematic entries
# 
# 4. Check for outdated entries
# 
# 5. Generate SQL file to update the outdated entries in the Digitales Personenregister

# %% [markdown]
# ## 1. Export data from WIAG and DPr
#
# For this step you have to manually export the datasets by opening [phpMyAdmin](https://vwebfile.gwdg.de/phpmyadmin/) and then for WIAG and DPr each:
# 1. log in 
# 2. run the saved "Step 2 of the sync notebooks for WIAG" sql query
# 3. export the result to a csv file
# 
# A detailed description can be found here: [Run_SQL_Query_and_Export_CSV.md](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/docs/Run_SQL_Query_and_Export_CSV.md) (As a backup, here are the saved queries: [WIAG](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/scripts/get_wiag_data.sql) -- [DPr](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/scripts/get_dpr_data.sql))
#
#
# ## 2. Import the files
# Please move the downloaded files to the `input_path` directory defined below or change the `input_path` to where the files are located.

# %%
import csv # first loading necessary libraries
import os
import pandas as pd
import os

# change this to where the csv file is located (e.g. C:\Users\<your_username_here>\Downloads\) or move the csv file to this directory
input_path = r"C:\Users\Public\WIAGweb2\notebooks\sync_notebooks\input_files"

wiag_file = 'item.csv' # change this in case you renamed the file
dpr_file = 'persons.csv' # change this in case you renamed the file

ic_df = pd.read_csv(os.path.join(input_path, wiag_file), names=["id", "wiag_id", "gsn"])
dpr_df = pd.read_csv(os.path.join(input_path, dpr_file), names=["wiag_id", "id", "gsn_table_id", "gsn"])

# %% [markdown]
# ## 3. Check for problematic entries
# Any listed entries **need to be fixed manually** before once again exporting the updated data from WIAG and DPr
# 
# First a list of known problematic entries (by GSN) in the DPr is created. These entries are linked to more than one entry in WIAG and it is unclear whether the different entries reference the same person or not, so they should simply be ignored for the rest of the script.
# %%
gsns_of_known_problematic_wiag_entries = ['046-02872-001', '007-00413-001']
# %% [markdown]
# ### Check data from WIAG
# %%
ic_df[ic_df['gsn'].isna()] # checking for entries with an empty Germania Sacra Number field
# %% [markdown]
# checking for entries that reference the same GSN
# %%
_duplicates = ic_df[ic_df.duplicated(subset = ['gsn'], keep = False)]
_duplicates[~_duplicates['gsn'].isin(gsns_of_known_problematic_wiag_entries)].sort_values(by=['gsn']) # ignoring known entries
# %% [markdown]
# ### Check data from DPr
# %%
dpr_df[dpr_df['gsn'].isna()] # checking for entries with an empty Germania Sacra Number field
# %%
ic_df[ic_df.duplicated(subset = ['wiag_id'], keep = False)].sort_values(by = ['wiag_id']) # checking for entries with the same WIAG-ID

# %% [markdown]
# ## 4. Check for entries with (probably) outdated WIAG-IDs in DPr
#
# Compares the records downloaded from WIAG and DPr. The output lists entries with differing WIAG-IDs in WIAG and DPr. **Check a sample** to make sure the listed entries simply need their WIAG-ID updated and a reasonable amount of entries are listed! For this purpose the list is also saved as a csv-file. Should you be unsure, do not proceed, but contact Barbara Kroeger!
# 
# Should the output be empty, there is nothing to be updated and you can proceed with the third notebook. Otherwise, proceed below.

# %%
# Join the dataframes from WIAG and DPr
joined_df = ic_df.merge(dpr_df, on='gsn', suffixes=('_wiag', '_dpr'))

# Check for linked entries that don't have the same WIAG ID
unequal_df = joined_df[joined_df['wiag_id_wiag'] != joined_df['wiag_id_dpr']]

# remove known entries that should be ignored (defined in step "Multiple records in WIAG for one GSN (manual fix)")
unequal_df = unequal_df[~unequal_df['gsn'].isin(gsns_of_known_problematic_wiag_entries)]

unequal_df # print entries that will be updated
# %% [markdown]
# Saving the list of entries to be updated as a csv-file for easier checking of proposed updates.
# Change the `output_path` to where you want the csv-file to be output.
# %%
from datetime import datetime

output_path = r"C:\Users\Public\WIAGweb2\notebooks\sync_notebooks\output_files"
today_string = datetime.now().strftime('%Y-%m-%d')

unequal_df.to_csv( 
    os.path.join(
        output_path,
        f'dpr_entries_to_be_updated_{today_string}.csv'
    ),
    index=False
)

# %% [markdown]
# ## 5. Updating Digitales Personenregister
# ### Generating the SQL-file
# Using the same `output_path` as above.
#
# %%
query = "LOCK TABLES persons WRITE;\n"
for row in unequal_df.itertuples():
    query += f"""
    UPDATE persons
    SET wiag = '{row.wiag_id_wiag}'
    WHERE id = {row.id_dpr}; -- id: {row.gsn}
"""
query += "\nUNLOCK TABLES;"

today_string = datetime.now().strftime('%Y-%m-%d')
with open(os.path.join(output_path, f'update_dpr_{today_string}.sql'), 'w') as file:
    file.write(query)

# %% [markdown]
# ### Upload the file
# Once the file has been generated, please open the DPr database on phpmyAdmin and run the SQL file there. First you need to select the database (gsdatenbank) and then either:
# - go to the Import tab -> select the file -> click 'Ok' to run it
# - go to the SQL tab -> copy the text in the file and paste it into the interface -> click 'Ok' to run it
# 
#
# Once the update is done, you can continue with the next notebook (fg_and_WIAG-recon - combining former steps 3 and 4)
