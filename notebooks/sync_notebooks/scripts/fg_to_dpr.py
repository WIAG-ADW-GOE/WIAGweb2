#%% [markdown]
## 7. FactGrid to Digitales Personenregister
### Add Factgrid IDs in Personenregister

# %% [markdown]
#This notebook helps ensure that the FactGrid IDs stored in the local "Digitales Personenregister" (DPr, digital index of persons) are correct and up to date.
# 
#By checking which IDs match and which do not, the notebook identifies differences, such as records in FactGrid that aren’t in the Digitales Personenregister, or people in the DPr whose FactGrid ID is incorrect or outdated. After finding these differences, it creates an easy-to-use set of SQL commands, that can be run on the DPr database to fix the FactGrid IDs and ensure both sources of information match.

# %% [markdown]
### 1. Export data from DPr
#
#For this step you have to **manually export the dataset** by opening and [phpMyAdmin (DPr)](https://personendatenbank.germania-sacra.de/phpmyadmin/):
# 1. log in 
# 2. select the gso database
# 3. switch to the 'SQL' tab
# 4. copy the query below, paste it in the text field and click 'Go'
# 5. export the result to a csv file
# 
#A detailed description can be found here: [Run_SQL_Query_and_Export_CSV.md](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/docs/Run_SQL_Query_and_Export_CSV.md)
# %% [markdown]
#```
#SELECT persons.factgrid, persons.id, gsn.nummer
#FROM items
#INNER JOIN persons ON persons.item_id = items.id AND persons.deleted=0 AND items.deleted=0 AND items.status = "online" 
#INNER JOIN gsn ON gsn.item_id = items.id AND gsn.deleted=0
#```
# %% [markdown]
### 2. Import the file
#Please **move the downloaded files** to the `input_path` directory defined below or **change the `input_path`** to where the files are located.
# %%
import requests
import csv
import os
import pandas as pd
import json
import re
import time
from datetime import datetime, timedelta
import math
import traceback

input_path = r"C:\Users\Public\WIAGweb2\notebooks\sync_notebooks\input_files"
filename = 'persons.csv'
# %%
pr_df = pd.read_csv(os.path.join(input_path, filename), header = 0, names=["fg_id", "id", "gsn"])
# %% [markdown]
### 3. Import data from FactGrid
#Data is downloaded and and cleaned for further processing automatically.
# %%
url = 'https://database.factgrid.de/sparql'
query = (
"""SELECT ?item ?gsn WHERE {
  ?item wdt:P472 ?gsn.
}""")

r = requests.get(url, params={'query': query}, headers={"Accept": "application/json"})
data = r.json()
factgrid_df = pd.json_normalize(data['results']['bindings'])

len(factgrid_df)
# %%
#extract out q id
def extract_qid(df, column):
    df[column] = df[column].map(lambda x: x.strip('https://database.factgrid.de/entity/'))

#drop irrelevant columns
def drop_type_columns(df):
    df.drop(columns=[column for column in df.columns if column.endswith('type')], inplace=True)
    df.drop(columns=[column for column in df.columns if column.endswith('xml:lang')], inplace=True)
# %%
drop_type_columns(factgrid_df)
extract_qid(factgrid_df, 'item.value')
factgrid_df.columns = ['FactGrid_ID', 'gsn']
# %% [markdown]
### 4. Compare data from DPr and FG
#First the data is joined. Then two checks will be performed. These two cases need to be **handled manually** and will **not be updated automatically**. Generally it's a good idea to take care of these cases right away, but if that's not possible, you can also first let the notebook finish and later take care of the other cases.
#Joining the data and showing a sample to give an idea of what the data looks like.
# %%
joined_df = factgrid_df.merge(pr_df, how='outer', on='gsn', suffixes=('_wiag', '_pd'), indicator=True)
joined_df
# %% [markdown]
#### Entries only in FG
#The output of the cell below shows entries in FG which point to entries that were not found in the DPr. These entries need to be **fixed manually**.
# %%
joined_df[joined_df['_merge'] == 'left_only']
# %% [markdown]
#From now on only entries that were found both in the DPr and FG and don't point to each other are considered, because these are the cases that need to be updated.
# %%
unequal_df = joined_df[(joined_df['_merge'] == 'both') & (joined_df['FactGrid_ID'] != joined_df['fg_id'])]
unequal_df
# %% [markdown]
#### Finding possible duplicates
#Should any entries be shown, these need to be **fixed manually**. For this, the cell one further down will generate links to speed up the process.
# %%
possible_dup = unequal_df[unequal_df['fg_id'].notna()]
possible_dup
# %% [markdown]
#generating links to check on FactGrid
# %%
linkify = lambda x : 'https://database.factgrid.de/wiki/Item:' + x 
for _, row in possible_dup.iterrows(): # if the DPr-entry points to a FactGrid-entry, but a different FG-entry points to the DPr-entry
    print(linkify (row['FactGrid_ID']), linkify (row['fg_id']))
# %% [markdown]
#once again ignoring the special cases and continuing on with the rest
# %%
to_be_updated_df = unequal_df[unequal_df['fg_id'].isna()]
# %% [markdown]
### 5. Update DPr
#### Generate SQL to update DPr
#Please **change the path** to where you want the SQL-file to be saved to.
# %%
output_path = r"C:\Users\Public\WIAGweb2\notebooks\sync_notebooks\output_files"
# %%
today_string = datetime.now().strftime('%Y-%m-%d')
query = "LOCK TABLES persons WRITE;\n"
for _, row in to_be_updated_df.iterrows():
    query += f"""
    UPDATE persons
    SET factgrid = '{row['FactGrid_ID']}'
    WHERE id = {row['id']}; -- id: {row['gsn']}
"""
query += "\nUNLOCK TABLES;"
with open(os.path.join(output_path, f'update_pr_fg_ids_{today_string}.sql'), 'w') as file:
    file.write(query)

# %% [markdown]
#### Upload the file
#Once the file has been generated, please open [phpMyAdmin DPr](https://personendatenbank.germania-sacra.de/phpmyadmin/) and **run the SQL** there. First you need to select the database (gso) and then either:
# - go to the Import tab -> select the file -> click 'Ok' to run it
# - go to the SQL tab -> copy the contents of the file and paste them into the interface -> click 'Ok' to run it
